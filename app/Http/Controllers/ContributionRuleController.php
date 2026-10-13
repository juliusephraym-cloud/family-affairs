<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainRuleException;
use App\Models\ContributionRule;
use App\Models\Decision;
use App\Models\Fund;
use App\Models\PolicyRule;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Contribution amounts are drafted by finance and only take effect after governance approval. */
class ContributionRuleController extends Controller
{
    public function index(): View
    {
        return view('treasury.rules', [
            'rules' => ContributionRule::with('approver.member', 'decision')->orderByDesc('effective_from')->get(),
            'funds' => Fund::where('is_active', true)->orderBy('sort')->get(),
            'decisions' => Decision::latest()->limit(30)->get(),
            'sourceRules' => PolicyRule::whereIn('key', ['contribution.monthly_amount', 'contribution.welfare_portion', 'contribution.due_day'])->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'membership_category' => ['nullable', 'string', 'max:32'],
            'effective_from' => ['required', 'date'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'splits' => ['required', 'array'],
            'splits.*' => ['nullable', 'string'],
            'source_reference' => ['nullable', 'string', 'max:1000'],
            'decision_id' => ['nullable', 'exists:decisions,id'],
        ]);
        $splits = collect($d['splits'])->map(fn ($v, $fund) => ['fund' => $fund, 'amount' => (int) Money::parse($v)])->filter(fn ($s) => $s['amount'] > 0)->values()->all();
        if (! $splits || ! Fund::whereIn('key', array_column($splits, 'fund'))->count()) {
            throw new DomainRuleException(__('Weka kiasi kwa angalau mfuko mmoja.'));
        }
        $rule = ContributionRule::create([
            'name' => $d['name'], 'membership_category' => $d['membership_category'] ?: 'standard', 'amount' => array_sum(array_column($splits, 'amount')),
            'fund_splits' => $splits, 'due_day' => $d['due_day'], 'effective_from' => $d['effective_from'], 'status' => 'proposed',
            'source_reference' => $d['source_reference'] ?? null, 'decision_id' => $d['decision_id'] ?? null, 'created_by' => $request->user()->id,
        ]);
        Audit::record('finance.rule_proposed', $rule, 'Contribution rule proposed: '.Money::format($rule->amount));

        return back()->with('status', __('Kanuni imependekezwa. Inahitaji kuidhinishwa na uongozi kabla ya kutumika.'));
    }

    public function approve(Request $request, ContributionRule $rule): RedirectResponse
    {
        if ($rule->created_by === $request->user()->id) {
            throw new DomainRuleException(__('finance.no_self_approve'));
        }
        abort_unless($rule->status === 'proposed', 422);
        // Close the previous open-ended rule the day before this one starts.
        ContributionRule::where('status', 'approved')->where('membership_category', $rule->membership_category)->whereNull('effective_to')
            ->where('effective_from', '<', $rule->effective_from)->update(['effective_to' => $rule->effective_from->copy()->subDay()]);
        $rule->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        Audit::record('finance.rule_approved', $rule, 'Contribution rule approved');

        return back()->with('status', __('Kanuni ya michango imeidhinishwa.'));
    }

    public function retire(Request $request, ContributionRule $rule): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:3']]);
        $rule->update(['status' => 'retired', 'effective_to' => $rule->effective_to ?? now()->toDateString()]);
        Audit::record('finance.rule_retired', $rule, 'Contribution rule retired', ['reason' => $request->input('reason')]);

        return back()->with('status', __('Kanuni imestaafishwa.'));
    }
}
