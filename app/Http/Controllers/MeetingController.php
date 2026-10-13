<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainRuleException;
use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\MeetingAttendance;
use App\Models\Member;
use App\Models\PolicyRule;
use App\Services\ContributionService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MeetingController extends Controller
{
    public function index(Request $request): View
    {
        return view('meetings.index', [
            'upcoming' => Meeting::with('minutes')->where('starts_at', '>=', now()->startOfDay())->where('status', '!=', 'cancelled')->orderBy('starts_at')->get(),
            'past' => Meeting::with('minutes')->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at')->paginate(15),
            'openActions' => ActionItem::with('assignee', 'meeting')->whereIn('status', ['open', 'in_progress'])->orderBy('due_on')->limit(12)->get(),
            'members' => Member::contributing()->orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->validated($request);
        $meeting = Meeting::create($d + ['created_by' => $request->user()->id, 'status' => 'planned']);
        foreach (array_filter(array_map('trim', explode("\n", (string) $request->input('agenda')))) as $i => $title) {
            $meeting->agendaItems()->create(['position' => $i + 1, 'title' => mb_substr($title, 0, 255)]);
        }
        Audit::record('meetings.created', $meeting, 'Meeting scheduled: '.$meeting->title);

        return redirect()->route('meetings.show', $meeting)->with('status', __('Kikao kimepangwa.'));
    }

    public function show(Request $request, Meeting $meeting): View
    {
        $meeting->load(['agendaItems.decisions', 'attendances.member', 'minutes.preparer.member', 'minutes.approver.member', 'decisions', 'actionItems.assignee', 'organizer', 'documents']);
        $canManage = $request->user()->hasPermission('governance.manage_meetings');
        $minutesVisible = $canManage || in_array($meeting->minutes?->status, ['approved', 'archived'], true);

        return view('meetings.show', [
            'meeting' => $meeting,
            'canManage' => $canManage,
            'minutesVisible' => $minutesVisible,
            'members' => $canManage ? Member::whereIn('membership_status', ['active', 'suspended', 'invited'])->orderBy('full_name')->get() : collect(),
            'absenceRule' => PolicyRule::where('key', 'penalty.meeting_absence')->first(),
        ]);
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        $d = $this->validated($request) + $request->validate(['status' => ['required', Rule::in(['planned', 'held', 'cancelled'])]]);
        $timeChanged = $meeting->starts_at->ne(Carbon::parse($d['starts_at']));
        $meeting->update($d);
        Audit::record('meetings.updated', $meeting, 'Meeting updated', ['time_changed' => $timeChanged]);

        return back()->with('status', $timeChanged ? __('Muda wa kikao umebadilishwa. Tuma taarifa kwa wanachama kupitia Ujumbe wa WhatsApp.') : __('Kikao kimesasishwa.'));
    }

    public function addAgenda(Request $request, Meeting $meeting): RedirectResponse
    {
        $d = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000']]);
        $meeting->agendaItems()->create($d + ['position' => (int) $meeting->agendaItems()->max('position') + 1]);

        return back();
    }

    public function updateAgenda(Request $request, Meeting $meeting, MeetingAgendaItem $item): RedirectResponse
    {
        abort_unless($item->meeting_id === $meeting->id, 404);
        $this->assertMinutesEditable($meeting);
        $d = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'], 'discussion' => ['nullable', 'string', 'max:50000'], 'position' => ['nullable', 'integer', 'min:1']]);
        $item->update($d);

        return back()->with('status', __('Imehifadhiwa.'));
    }

    public function removeAgenda(Meeting $meeting, MeetingAgendaItem $item): RedirectResponse
    {
        abort_unless($item->meeting_id === $meeting->id, 404);
        $this->assertMinutesEditable($meeting);
        $item->delete();

        return back();
    }

    public function attendance(Request $request, Meeting $meeting): RedirectResponse
    {
        $d = $request->validate(['attendance' => ['array'], 'attendance.*' => [Rule::in(['present', 'apology', 'absent', 'excused', ''])], 'role' => ['array'], 'note' => ['array']]);
        foreach ($d['attendance'] ?? [] as $memberId => $status) {
            if ($status === '' || $status === null) {
                MeetingAttendance::where('meeting_id', $meeting->id)->where('member_id', $memberId)->delete();

                continue;
            }
            MeetingAttendance::updateOrCreate(['meeting_id' => $meeting->id, 'member_id' => (int) $memberId], [
                'status' => $status, 'role_at_meeting' => $request->input("role.$memberId"), 'note' => $request->input("note.$memberId"), 'recorded_by' => $request->user()->id,
            ]);
        }
        Audit::record('meetings.attendance', $meeting, 'Attendance register saved');

        return back()->with('status', __('Mahudhurio yamehifadhiwa.'));
    }

    public function addDecision(Request $request, Meeting $meeting): RedirectResponse
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:5000'],
            'type' => ['required', Rule::in(['resolution', 'amendment', 'financial', 'membership', 'other'])],
            'agenda_item_id' => ['nullable', 'exists:meeting_agenda_items,id'],
            'votes_for' => ['nullable', 'integer', 'min:0'], 'votes_against' => ['nullable', 'integer', 'min:0'], 'votes_abstain' => ['nullable', 'integer', 'min:0'],
            'effective_from' => ['nullable', 'date'], 'needs_confirmation' => ['boolean'],
        ]);
        $decision = $meeting->decisions()->create(collect($d)->except('needs_confirmation')->all() + [
            'source' => 'meeting', 'status' => $request->boolean('needs_confirmation') ? 'needs_confirmation' : 'recorded', 'recorded_by' => $request->user()->id,
        ]);
        Audit::record('meetings.decision', $decision, 'Decision recorded: '.$decision->title);

        return back()->with('status', __('Uamuzi umerekodiwa.'));
    }

    public function addAction(Request $request, Meeting $meeting): RedirectResponse
    {
        $d = $request->validate(['title' => ['required', 'string', 'max:255'], 'assignee_member_id' => ['nullable', 'exists:members,id'], 'due_on' => ['nullable', 'date'], 'decision_id' => ['nullable', 'exists:decisions,id'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $meeting->actionItems()->create($d + ['created_by' => $request->user()->id]);

        return back()->with('status', __('Jukumu limeongezwa.'));
    }

    public function actionStatus(Request $request, ActionItem $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($action->assignee_member_id === $user->member_id || $user->hasPermission('governance.manage_meetings'), 403);
        $d = $request->validate(['status' => ['required', Rule::in(['open', 'in_progress', 'done', 'cancelled'])]]);
        $action->update(['status' => $d['status'], 'completed_at' => $d['status'] === 'done' ? now() : null]);

        return back();
    }

    /** Apply the (confirmed) meeting-absence fine to members marked absent without an accepted apology. */
    public function absenceFines(Request $request, Meeting $meeting, ContributionService $contributions): RedirectResponse
    {
        $amount = PolicyRule::confirmedValue('penalty.meeting_absence');
        if (! $amount) {
            throw new DomainRuleException(__('finance.rule_not_confirmed'));
        }
        $n = 0;
        foreach ($meeting->attendances()->where('status', 'absent')->with('member')->get() as $a) {
            $ob = $contributions->addObligation($a->member, 'penalty', (int) $amount, __('Faini ya kutohudhuria: :m', ['m' => $meeting->title]), now()->toDateString(),
                [['fund' => 'general', 'amount' => (int) $amount]], 'penalty:meeting:'.$meeting->id, $request->user(), ['penalty_rule_key' => 'penalty.meeting_absence']);
            $n += $ob->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('status', __('Faini :n zimewekwa.', ['n' => $n]));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(['general', 'online', 'leadership', 'special'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'online_method' => ['nullable', 'string', 'max:255'],
            'organizer_member_id' => ['nullable', 'exists:members,id'],
            'attendance_required' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    private function assertMinutesEditable(Meeting $meeting): void
    {
        if (in_array($meeting->minutes?->status, ['approved', 'archived'], true)) {
            throw new DomainRuleException(__('Kumbukumbu zimeidhinishwa; haziwezi kubadilishwa.'));
        }
    }
}
