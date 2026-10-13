<?php

namespace App\Http\Controllers;

use App\Models\ComplianceCase;
use App\Models\Decision;
use App\Models\Member;
use App\Models\MembershipStatusHistory;
use App\Models\PolicyRule;
use App\Services\ContributionService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Arrears process required by the brief: warning → reminder → authorised
 * review → recorded decision → (only then, if decided) a controlled status
 * change. Nothing here runs automatically.
 */
class ComplianceController extends Controller
{
    public function index(ContributionService $contributions): View
    {
        $rule = PolicyRule::where('key', 'membership.arrears_months_exit')->first();
        $threshold = (int) ($rule?->scalar() ?? 3);
        $open = ComplianceCase::with('member', 'decider.member')->whereNotIn('stage', ['closed'])->latest()->get();
        $candidates = Member::contributing()->whereNotIn('id', $open->pluck('member_id'))->get()
            ->map(fn ($m) => ['member' => $m, 'summary' => $contributions->summary($m)])
            ->filter(fn ($r) => $r['summary']['months_in_arrears'] >= max(1, $threshold - 1))
            ->sortByDesc(fn ($r) => $r['summary']['months_in_arrears'])->values();

        return view('governance.compliance', [
            'rule' => $rule, 'threshold' => $threshold, 'open' => $open, 'candidates' => $candidates,
            'closed' => ComplianceCase::with('member')->where('stage', 'closed')->latest()->limit(20)->get(),
            'decisions' => Decision::latest()->limit(30)->get(),
        ]);
    }

    public function open(Request $request, ContributionService $contributions): RedirectResponse
    {
        $d = $request->validate(['member_id' => ['required', 'exists:members,id'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $member = Member::findOrFail($d['member_id']);
        $s = $contributions->summary($member);
        $case = ComplianceCase::create([
            'member_id' => $member->id, 'stage' => 'warning', 'arrears_snapshot' => $s['overdue'], 'months_in_arrears' => $s['months_in_arrears'],
            'notes' => $d['notes'] ?? null, 'opened_by' => $request->user()->id,
        ]);
        Audit::record('compliance.opened', $case, 'Arrears process opened for '.$member->full_name, ['months' => $s['months_in_arrears']]);

        return back()->with('status', __('Hatua ya onyo imeanzishwa. Mjulishe mwanachama kwa upole na binafsi.'));
    }

    public function advance(Request $request, ComplianceCase $case): RedirectResponse
    {
        $d = $request->validate([
            'stage' => ['required', Rule::in(['reminder', 'review', 'decided', 'closed'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'outcome' => ['nullable', 'required_if:stage,decided', Rule::in(['no_action', 'payment_plan', 'status_change'])],
            'outcome_status' => ['nullable', 'required_if:outcome,status_change', Rule::in(['suspended', 'resigned', 'archived', 'active'])],
            'decision_text' => ['nullable', 'required_if:stage,decided', 'string', 'max:3000'],
            'decision_id' => ['nullable', 'exists:decisions,id'],
        ]);
        $order = array_flip(ComplianceCase::STAGES);
        abort_if($order[$d['stage']] <= $order[$case->stage], 422);
        // A decision can only be recorded after an authorised review stage.
        abort_if($d['stage'] === 'decided' && $case->stage !== 'review', 422);

        $case->update(array_filter([
            'stage' => $d['stage'],
            'notes' => trim(($case->notes ? $case->notes."\n" : '').'['.now()->format('d/m/Y').'] '.($d['notes'] ?? '')),
            'outcome' => $d['outcome'] ?? null,
            'outcome_status' => $d['outcome_status'] ?? null,
            'decision_text' => $d['decision_text'] ?? null,
            'decision_id' => $d['decision_id'] ?? null,
            'decided_by' => $d['stage'] === 'decided' ? $request->user()->id : null,
            'decided_at' => $d['stage'] === 'decided' ? now() : null,
        ], fn ($v) => $v !== null));

        if ($d['stage'] === 'decided' && ($d['outcome'] ?? null) === 'status_change') {
            $m = $case->member;
            MembershipStatusHistory::create(['member_id' => $m->id, 'from_status' => $m->membership_status, 'to_status' => $d['outcome_status'], 'reason' => 'Compliance decision: '.$d['decision_text'], 'decision_id' => $d['decision_id'] ?? null, 'changed_by' => $request->user()->id]);
            $m->update(['membership_status' => $d['outcome_status']]);
        }
        Audit::record('compliance.'.$d['stage'], $case, 'Arrears process → '.$d['stage'], ['outcome' => $d['outcome'] ?? null]);

        return back()->with('status', __('Hatua imesasishwa.'));
    }
}
