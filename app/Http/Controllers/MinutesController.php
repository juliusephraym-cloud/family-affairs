<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainRuleException;
use App\Models\Approval;
use App\Models\Meeting;
use App\Support\Audit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Minutes: draft → submitted → approved (by someone other than the preparer) → archived. Approved minutes are frozen. */
class MinutesController extends Controller
{
    public function edit(Request $request, Meeting $meeting): View
    {
        $minutes = $meeting->minutes ?? $meeting->minutes()->create(['status' => 'draft', 'prepared_by' => $request->user()->id]);
        abort_if(in_array($minutes->status, ['approved', 'archived'], true), 403);

        return view('meetings.minutes', ['meeting' => $meeting->load('agendaItems.decisions', 'attendances.member', 'actionItems.assignee'), 'minutes' => $minutes]);
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        $minutes = $meeting->minutes;
        abort_unless($minutes && in_array($minutes->status, ['draft', 'submitted'], true), 403);
        $d = $request->validate([
            'opening_notes' => ['nullable', 'string', 'max:20000'],
            'closing_notes' => ['nullable', 'string', 'max:20000'],
            'next_meeting_note' => ['nullable', 'string', 'max:255'],
            'discussion' => ['array'],
            'discussion.*' => ['nullable', 'string', 'max:50000'],
        ]);
        foreach ($d['discussion'] ?? [] as $itemId => $text) {
            $meeting->agendaItems()->whereKey($itemId)->update(['discussion' => $text]);
        }
        // Editing after submission sends it back to draft.
        $minutes->update(collect($d)->except('discussion')->all() + ['status' => 'draft']);

        return back()->with('status', __('Rasimu imehifadhiwa.'));
    }

    public function submit(Request $request, Meeting $meeting): RedirectResponse
    {
        $minutes = $meeting->minutes;
        abort_unless($minutes && $minutes->status === 'draft', 422);
        $minutes->update(['status' => 'submitted', 'submitted_at' => now(), 'prepared_by' => $minutes->prepared_by ?? $request->user()->id]);
        Approval::create(['approvable_type' => $minutes->getMorphClass(), 'approvable_id' => $minutes->id, 'step' => 'submit', 'decision' => 'submitted', 'decided_by' => $request->user()->id]);
        Audit::record('minutes.submitted', $minutes, 'Minutes submitted for approval');

        return redirect()->route('meetings.show', $meeting)->with('status', __('Kumbukumbu zimetumwa kwa Mwenyekiti/Makamu kuidhinishwa.'));
    }

    public function decide(Request $request, Meeting $meeting): RedirectResponse
    {
        $minutes = $meeting->minutes;
        $d = $request->validate(['decision' => ['required', Rule::in(['approve', 'return', 'archive'])], 'reason' => ['nullable', 'string', 'max:2000']]);
        abort_unless($minutes, 404);
        if ($d['decision'] === 'archive') {
            abort_unless($minutes->status === 'approved', 422);
            $minutes->update(['status' => 'archived']);

            return back();
        }
        abort_unless($minutes->status === 'submitted', 422);
        if ($minutes->prepared_by === $request->user()->id) {
            throw new DomainRuleException(__('Huwezi kuidhinisha kumbukumbu ulizoandaa mwenyewe.'));
        }
        if ($d['decision'] === 'approve') {
            $minutes->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'review_note' => $d['reason'] ?? null]);
            $meeting->update(['status' => 'held']);
        } else {
            if (blank($d['reason'] ?? null)) {
                throw new DomainRuleException(__('finance.reason_required'));
            }
            $minutes->update(['status' => 'draft', 'review_note' => $d['reason']]);
        }
        Approval::create(['approvable_type' => $minutes->getMorphClass(), 'approvable_id' => $minutes->id, 'step' => 'approval', 'decision' => $d['decision'] === 'approve' ? 'approved' : 'returned', 'reason' => $d['reason'] ?? null, 'decided_by' => $request->user()->id]);
        Audit::record('minutes.'.$d['decision'], $minutes, 'Minutes '.$d['decision']);

        return back()->with('status', $d['decision'] === 'approve' ? __('Kumbukumbu zimeidhinishwa na sasa zinaonekana kwa wanachama.') : __('Kumbukumbu zimerudishwa kwa marekebisho.'));
    }

    public function pdf(Request $request, Meeting $meeting)
    {
        $minutes = $meeting->minutes;
        abort_unless($minutes && ($request->user()->hasPermission('governance.manage_meetings') || in_array($minutes->status, ['approved', 'archived'], true)), 404);
        $meeting->load('agendaItems.decisions', 'attendances.member', 'actionItems.assignee');

        return Pdf::loadView('meetings.minutes-pdf', ['meeting' => $meeting, 'minutes' => $minutes->load('preparer.member', 'approver.member')])
            ->download('kumbukumbu-'.Str::slug($meeting->title).'-'.$meeting->starts_at->format('Ymd').'.pdf');
    }
}
