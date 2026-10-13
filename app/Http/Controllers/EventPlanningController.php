<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventBudgetLine;
use App\Models\EventCommittee;
use App\Models\EventTask;
use App\Models\NotificationTemplate;
use App\Models\Vendor;
use App\Services\NotificationService;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventPlanningController extends Controller
{
    public function storeCommittee(Request $request, Event $event): RedirectResponse
    {
        $d = $request->validate(['name' => ['required', 'string', 'max:100'], 'lead_member_id' => ['nullable', 'exists:members,id'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $event->committees()->create($d + ['sort' => $event->committees()->count()]);

        return back()->with('status', __('Kamati imeongezwa.'));
    }

    public function committeeMembers(Request $request, Event $event, EventCommittee $committee): RedirectResponse
    {
        abort_unless($committee->event_id === $event->id, 404);
        $d = $request->validate(['members' => ['array'], 'members.*' => ['integer', 'exists:members,id'], 'lead_member_id' => ['nullable', 'exists:members,id']]);
        $committee->members()->sync($d['members'] ?? []);
        $committee->update(['lead_member_id' => $d['lead_member_id'] ?? $committee->lead_member_id]);

        return back()->with('status', __('Wanakamati wamehifadhiwa.'));
    }

    public function storeTask(Request $request, Event $event, NotificationService $notifications): RedirectResponse
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'event_committee_id' => ['nullable', 'exists:event_committees,id'],
            'owner_member_id' => ['nullable', 'exists:members,id'], 'due_at' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:2000'],
            'notify' => ['boolean'],
        ]);
        abort_if(($d['event_committee_id'] ?? null) && EventCommittee::find($d['event_committee_id'])->event_id !== $event->id, 422);
        $task = $event->tasks()->create(collect($d)->except('notify')->all() + ['sort' => $event->tasks()->count()]);

        // Optional WhatsApp to the task owner, only if the template is approved.
        $tpl = NotificationTemplate::where('key', 'task_assignment')->first();
        if ($request->boolean('notify') && $task->owner_member_id && $tpl?->isSendable()) {
            $notifications->schedule($tpl, 'Jukumu: '.$task->title, ['type' => 'members', 'member_ids' => [$task->owner_member_id]], now(), [
                'title' => $task->title, 'event' => $event->title, 'date' => $task->due_at?->format('d/m/Y') ?? '—', 'portal_link' => route('events.show', $event),
            ], $request->user(), $task, 'task:'.$task->id, true);
        }

        return back()->with('status', __('Jukumu limeongezwa.'));
    }

    public function taskStatus(Request $request, Event $event, EventTask $task): RedirectResponse
    {
        abort_unless($task->event_id === $event->id, 404);
        $user = $request->user();
        $isLead = $task->committee && $task->committee->lead_member_id === $user->member_id;
        abort_unless($task->owner_member_id === $user->member_id || $isLead || $user->hasAnyPermission('events.manage', 'events.manage_tasks'), 403);
        $d = $request->validate(['status' => ['required', Rule::in(EventTask::STATUSES)], 'notes' => ['nullable', 'string', 'max:2000']]);
        $task->update(['status' => $d['status'], 'notes' => $d['notes'] ?? $task->notes, 'completed_at' => $d['status'] === 'done' ? now() : null]);

        return back();
    }

    public function storeBudgetLine(Request $request, Event $event): RedirectResponse
    {
        $d = $request->validate([
            'label' => ['required', 'string', 'max:160'], 'estimated_amount' => ['required', 'string'],
            'event_committee_id' => ['nullable', 'exists:event_committees,id'], 'vendor_id' => ['nullable', 'exists:vendors,id'], 'responsible_member_id' => ['nullable', 'exists:members,id'],
        ]);
        $d['estimated_amount'] = (int) Money::parse($d['estimated_amount']);
        $event->budgetLines()->create($d);

        return back()->with('status', __('Kipengele cha bajeti kimeongezwa.'));
    }

    public function updateBudgetLine(Request $request, Event $event, EventBudgetLine $line): RedirectResponse
    {
        abort_unless($line->event_id === $event->id, 404);
        $d = $request->validate(['actual_amount' => ['nullable', 'string'], 'is_done' => ['boolean']]);
        $line->update(['actual_amount' => filled($d['actual_amount'] ?? null) ? Money::parse($d['actual_amount']) : null, 'is_done' => $request->boolean('is_done')]);

        return back();
    }

    public function storeVendor(Request $request): RedirectResponse
    {
        $d = $request->validate(['name' => ['required', 'string', 'max:120'], 'service' => ['required', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:30'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $d['phone'] = Phone::normalize($d['phone'] ?? null) ?? ($d['phone'] ?? null);
        Vendor::create($d + ['created_by' => $request->user()->id]);

        return back()->with('status', __('Mtoa huduma ameongezwa.'));
    }
}
