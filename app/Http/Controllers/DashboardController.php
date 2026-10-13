<?php

namespace App\Http\Controllers;

use App\Models\ActionItem;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\EventTask;
use App\Models\Expense;
use App\Models\Invitation;
use App\Models\MediaAsset;
use App\Models\Meeting;
use App\Models\MeetingMinute;
use App\Models\Member;
use App\Models\Obligation;
use App\Models\PaymentSubmission;
use App\Models\PolicyRule;
use App\Models\Poll;
use App\Models\SecretoSeason;
use App\Models\WelfareCase;
use App\Services\ContributionService;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ContributionService $contributions, LedgerService $ledger): View
    {
        $user = $request->user();
        $member = $user->member;
        $now = now();

        $nextEvent = Event::visibleTo($user)->whereIn('status', ['planned', 'confirmed'])->where('starts_at', '>=', $now->copy()->startOfDay())->orderBy('starts_at')->first();
        $nextMeeting = Meeting::where('status', 'planned')->where('starts_at', '>=', $now)->orderBy('starts_at')->first();

        $birthdays = Member::directory()->whereNotNull('date_of_birth')->get()
            ->filter(fn (Member $m) => $m->birthdayVisibleTo($user))
            ->map(fn (Member $m) => ['member' => $m, 'date' => $m->nextBirthday($now)])
            ->filter(fn ($b) => $b['date']->lte($now->copy()->addDays(30)))
            ->sortBy(fn ($b) => $b['date']->timestamp)->take(5)->values();

        $myTasks = EventTask::with('event')->where('owner_member_id', $member->id)->where('status', '!=', 'done')->orderBy('due_at')->limit(5)->get();
        $myActions = ActionItem::where('assignee_member_id', $member->id)->whereIn('status', ['open', 'in_progress'])->orderBy('due_on')->limit(5)->get();

        $pending = collect();
        if ($nextEvent && ! $nextEvent->rsvps()->where('member_id', $member->id)->exists()) {
            $pending->push(['label' => __('Thibitisha kuhudhuria: :t', ['t' => $nextEvent->title]), 'url' => route('events.show', $nextEvent)]);
        }
        Poll::where('opens_at', '<=', $now)->where('closes_at', '>', $now)->whereDoesntHave('votes', fn ($q) => $q->where('user_id', $user->id))->limit(3)->get()
            ->filter(fn ($p) => $p->isEligible($user))
            ->each(fn ($p) => $pending->push(['label' => __('Piga kura: :q', ['q' => $p->question]), 'url' => route('polls.show', $p)]));
        $season = SecretoSeason::where('status', 'registration')->latest('year')->first();
        if ($season && $season->registrationOpen() && ! $season->participants()->where('member_id', $member->id)->exists()) {
            $pending->push(['label' => __('Jiandikishe kwa Secreto :y', ['y' => $season->year]), 'url' => route('secreto.index')]);
        }

        $data = [
            'user' => $user,
            'member' => $member,
            'greeting' => $now->hour < 12 ? __('Habari za asubuhi') : ($now->hour < 17 ? __('Habari za mchana') : __('Habari za jioni')),
            'summary' => $user->hasPermission('finance.view_own') ? $contributions->summary($member) : null,
            'nextEvent' => $nextEvent,
            'nextMeeting' => $nextMeeting,
            'birthdays' => $birthdays,
            'announcements' => Announcement::visibleTo($user)->where('kind', 'official')->orderByDesc('is_pinned')->latest('published_at')->limit(3)->get(),
            'myTasks' => $myTasks,
            'myActions' => $myActions,
            'pending' => $pending,
            'memories' => MediaAsset::where('status', 'visible')->whereHas('album', fn ($a) => $a->visibleTo($user))->latest()->limit(6)->get(),
        ];

        if ($user->isLeader()) {
            $data['leadership'] = [
                'members' => Member::selectRaw('membership_status, count(*) c')->groupBy('membership_status')->pluck('c', 'membership_status'),
                'minutesToApprove' => MeetingMinute::where('status', 'submitted')->with('meeting')->get(),
                'rulesToConfirm' => PolicyRule::whereIn('status', ['source_unconfirmed', 'proposed'])->count(),
                'welfarePending' => WelfareCase::where('status', 'submitted')->count(),
                'invitesOpen' => Invitation::whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', $now)->count(),
                'overdueActions' => ActionItem::whereIn('status', ['open', 'in_progress'])->whereDate('due_on', '<', $now)->with('assignee')->limit(5)->get(),
                'upcomingEvents' => Event::with('tasks')->whereIn('status', ['planned', 'confirmed'])->where('starts_at', '>=', $now)->orderBy('starts_at')->limit(3)->get(),
            ];
        }

        if ($user->hasAnyPermission('finance.view_all', 'finance.verify')) {
            $period = $now->format('Y-m');
            $monthObs = Obligation::where('kind', 'monthly_contribution')->where('period', $period)->whereNotIn('status', ['waived', 'cancelled'])->get();
            $data['treasury'] = [
                'funds' => $ledger->fundBalances(),
                'pendingSubmissions' => PaymentSubmission::where('status', 'pending')->count(),
                'expensesToApprove' => Expense::where('status', 'submitted')->count(),
                'expensesToPay' => Expense::where('status', 'approved')->count(),
                'collectionRate' => $monthObs->count() ? (int) round($monthObs->where('status', 'paid')->count() / $monthObs->count() * 100) : null,
                'period' => $now->translatedFormat('F Y'),
            ];
        }

        return view('dashboard', $data);
    }
}
