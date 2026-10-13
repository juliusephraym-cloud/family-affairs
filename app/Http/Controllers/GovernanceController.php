<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainRuleException;
use App\Models\ConstitutionAmendment;
use App\Models\ConstitutionVersion;
use App\Models\Decision;
use App\Models\Meeting;
use App\Models\PolicyRule;
use App\Models\PolicyRuleVersion;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GovernanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('governance.index', [
            'current' => ConstitutionVersion::where('status', 'approved')->latest('effective_from')->first(),
            'versions' => ConstitutionVersion::with('approver.member', 'decision.meeting')->latest()->get(),
            'amendments' => ConstitutionAmendment::with('proposer.member', 'meeting', 'decision')->latest()->get(),
            'rules' => PolicyRule::with('confirmer.member', 'versions.changer.member')->orderByRaw("CASE status WHEN 'source_unconfirmed' THEN 0 WHEN 'proposed' THEN 1 WHEN 'confirmed' THEN 2 ELSE 3 END")->orderBy('key')->get(),
            'decisions' => Decision::with('meeting')->latest()->limit(40)->get(),
            'meetings' => Meeting::latest('starts_at')->limit(20)->get(),
            'canConfirm' => $user->hasPermission('governance.confirm_rules'),
        ]);
    }

    public function version(ConstitutionVersion $version): View
    {
        $previous = ConstitutionVersion::where('id', '<', $version->id)->latest('id')->first();

        return view('governance.version', ['version' => $version->load('approver.member', 'decision.meeting'), 'previous' => $previous]);
    }

    public function storeVersion(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'version_label' => ['required', 'string', 'max:32'],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:200000'],
            'change_summary' => ['nullable', 'string', 'max:2000'],
        ]);
        $v = ConstitutionVersion::create($d + ['status' => 'draft', 'created_by' => $request->user()->id]);
        Audit::record('governance.constitution_draft', $v, 'Constitution draft saved: '.$v->version_label);

        return redirect()->route('governance.version', $v)->with('status', __('Rasimu ya katiba imehifadhiwa. Inahitaji kuidhinishwa na kikao.'));
    }

    public function approveVersion(Request $request, ConstitutionVersion $version): RedirectResponse
    {
        $d = $request->validate(['effective_from' => ['required', 'date'], 'decision_id' => ['required', 'exists:decisions,id']]);
        if ($version->created_by === $request->user()->id) {
            throw new DomainRuleException(__('finance.no_self_approve'));
        }
        DB::transaction(function () use ($version, $d, $request) {
            ConstitutionVersion::where('status', 'approved')->update(['status' => 'superseded']);
            $version->update(['status' => 'approved', 'effective_from' => $d['effective_from'], 'decision_id' => $d['decision_id'], 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        });
        Audit::record('governance.constitution_approved', $version, 'Constitution version approved: '.$version->version_label);

        return back()->with('status', __('Toleo hili la katiba sasa ndilo linalotumika.'));
    }

    public function propose(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'clause_ref' => ['required', 'string', 'max:32'],
            'current_text' => ['nullable', 'string', 'max:5000'],
            'proposed_text' => ['required', 'string', 'max:5000'],
            'rationale' => ['nullable', 'string', 'max:3000'],
        ]);
        $a = ConstitutionAmendment::create($d + ['proposed_by' => $request->user()->id, 'status' => 'proposed']);
        Audit::record('governance.amendment_proposed', $a, 'Amendment proposed for clause '.$a->clause_ref);

        return back()->with('status', __('Pendekezo lako limepokelewa na litajadiliwa kwenye kikao.'));
    }

    public function review(Request $request, ConstitutionAmendment $amendment): RedirectResponse
    {
        $d = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'approved', 'rejected', 'withdrawn'])],
            'meeting_id' => ['nullable', 'exists:meetings,id', 'required_if:status,approved'],
            'decision_id' => ['nullable', 'exists:decisions,id', 'required_if:status,approved'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $amendment->update($d + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        Audit::record('governance.amendment_reviewed', $amendment, 'Amendment status: '.$d['status']);

        return back()->with('status', __('Hali ya pendekezo imesasishwa.'));
    }

    /** Confirm (possibly with a corrected value), mark as proposed, or retire a family rule. Every change is versioned. */
    public function confirmRule(Request $request, PolicyRule $rule): RedirectResponse
    {
        $d = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'retire', 'propose'])],
            'value' => ['nullable', 'string', 'max:2000'],
            'effective_from' => ['nullable', 'date'],
            'decision_id' => ['nullable', 'exists:decisions,id'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $value = $rule->value;
        if (filled($d['value'] ?? null)) {
            $value = match ($rule->value_type) {
                'money' => ['amount' => Money::parse($d['value'])],
                'number' => ['value' => (int) $d['value']],
                'boolean' => ['value' => in_array(strtolower($d['value']), ['1', 'true', 'ndiyo', 'yes'], true)],
                'list' => ['value' => array_values(array_filter(array_map('trim', explode(',', $d['value']))))],
                default => ['value' => $d['value']],
            };
        }
        if ($d['action'] === 'confirm' && (($value['amount'] ?? $value['value'] ?? null) === null)) {
            throw new DomainRuleException(__('Kanuni haiwezi kuthibitishwa bila thamani. Weka thamani kwanza.'));
        }
        $status = ['confirm' => 'confirmed', 'retire' => 'retired', 'propose' => 'proposed'][$d['action']];

        DB::transaction(function () use ($rule, $value, $status, $d, $request) {
            PolicyRuleVersion::create(['policy_rule_id' => $rule->id, 'value' => $rule->value, 'status' => $rule->status, 'reason' => $d['reason'], 'changed_by' => $request->user()->id]);
            $rule->update([
                'value' => $value, 'status' => $status,
                'effective_from' => $d['effective_from'] ?? $rule->effective_from,
                'decision_id' => $d['decision_id'] ?? $rule->decision_id,
                'confirmed_by' => $status === 'confirmed' ? $request->user()->id : $rule->confirmed_by,
                'confirmed_at' => $status === 'confirmed' ? now() : $rule->confirmed_at,
            ]);
        });
        Audit::record('governance.rule_'.$status, $rule, "Rule {$rule->key} → {$status}", ['value' => $value, 'reason' => $d['reason']]);

        return back()->with('status', __('Kanuni imesasishwa na historia yake imehifadhiwa.'));
    }
}
