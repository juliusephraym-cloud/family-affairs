<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Fund;
use App\Models\Member;
use App\Models\Vendor;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        $events = Event::visibleTo($user)->with('tasks')->whereBetween('starts_at', [$month->copy()->startOfWeek(), $month->copy()->endOfMonth()->endOfWeek()])->orderBy('starts_at')->get();

        // Birthdays the viewer may see appear on the calendar too (day + month only).
        $birthdays = Member::directory()->whereNotNull('date_of_birth')->whereMonth('date_of_birth', $month->month)->get()
            ->filter(fn ($m) => $m->birthdayVisibleTo($user));

        return view('events.index', [
            'month' => $month,
            'events' => $events,
            'birthdays' => $birthdays,
            'upcoming' => Event::visibleTo($user)->whereIn('status', ['planned', 'confirmed'])->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->limit(6)->get(),
            'past' => Event::visibleTo($user)->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at')->limit(6)->get(),
            'members' => Member::contributing()->orderBy('full_name')->get(['id', 'full_name']),
            'funds' => Fund::where('is_active', true)->orderBy('sort')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->validated($request);
        $event = Event::create($d + ['created_by' => $request->user()->id, 'status' => 'planned']);
        if ($event->visibility === 'invited') {
            $event->invitees()->sync($request->input('invitees', []));
        }
        foreach ((array) $request->input('committees', []) as $i => $name) {
            if (trim($name) !== '') {
                $event->committees()->create(['name' => trim($name), 'sort' => $i]);
            }
        }
        Audit::record('events.created', $event, 'Event created: '.$event->title);

        return redirect()->route('events.show', $event)->with('status', __('Tukio limeundwa. Ongeza kamati na majukumu.'));
    }

    public function show(Request $request, Event $event): View
    {
        $user = $request->user();
        abort_unless(Event::visibleTo($user)->whereKey($event->id)->exists(), 404);
        $event->load(['committees.lead', 'committees.members', 'tasks.owner', 'tasks.committee', 'budgetLines.vendor', 'budgetLines.responsible', 'budgetLines.committee', 'rsvps.member', 'organizer', 'expenses']);
        $canManage = $user->hasPermission('events.manage');

        return view('events.show', [
            'event' => $event,
            'canManage' => $canManage,
            'canTasks' => $user->hasPermission('events.manage_tasks'),
            'myRsvp' => $event->rsvps->firstWhere('member_id', $user->member_id),
            'members' => Member::contributing()->orderBy('full_name')->get(['id', 'full_name', 'preferred_name']),
            'vendors' => $canManage ? Vendor::orderBy('service')->get() : collect(),
            'view' => in_array($request->query('view'), ['board', 'list', 'timeline'], true) ? $request->query('view') : 'board',
            'funds' => Fund::where('is_active', true)->orderBy('sort')->get(),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $d = $this->validated($request) + $request->validate([
            'status' => ['required', Rule::in(['planned', 'confirmed', 'done', 'cancelled'])],
            'report' => ['nullable', 'string', 'max:10000'], 'lessons' => ['nullable', 'string', 'max:5000'],
        ]);
        $event->update($d);
        if ($event->visibility === 'invited') {
            $event->invitees()->sync($request->input('invitees', []));
        }
        Audit::record('events.updated', $event, 'Event updated');

        return back()->with('status', __('Tukio limesasishwa.'));
    }

    public function rsvp(Request $request, Event $event): RedirectResponse
    {
        abort_unless(Event::visibleTo($request->user())->whereKey($event->id)->exists(), 404);
        $d = $request->validate(['response' => ['required', Rule::in(['yes', 'no', 'maybe'])], 'guests' => ['nullable', 'integer', 'between:0,20'], 'note' => ['nullable', 'string', 'max:255']]);
        EventRsvp::updateOrCreate(['event_id' => $event->id, 'member_id' => $request->user()->member_id], ['response' => $d['response'], 'guests' => $d['guests'] ?? 0, 'note' => $d['note'] ?? null]);

        return back()->with('status', __('Asante! Jibu lako limehifadhiwa.'));
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'type' => ['required', Rule::in(Event::TYPES)],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'venue' => ['nullable', 'string', 'max:255'],
            'online_link' => ['nullable', 'url', 'max:255'],
            'organizer_member_id' => ['nullable', 'exists:members,id'],
            'visibility' => ['required', Rule::in(['members', 'leaders', 'invited'])],
            'budget_amount' => ['nullable', 'string'],
            'expected_attendance' => ['nullable', 'integer', 'min:0'],
            'fund_id' => ['nullable', 'exists:funds,id'],
        ]);
        $d['budget_amount'] = filled($d['budget_amount'] ?? null) ? Money::parse($d['budget_amount']) : null;

        return $d;
    }
}
