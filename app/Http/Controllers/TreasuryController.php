<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainRuleException;
use App\Models\Expense;
use App\Models\InvestmentValuation;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\Member;
use App\Models\Obligation;
use App\Models\PaymentSubmission;
use App\Models\PolicyRule;
use App\Models\VerifiedPayment;
use App\Services\ContributionService;
use App\Services\LedgerService;
use App\Services\ReportService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TreasuryController extends Controller
{
    public function __construct(private readonly ContributionService $contributions, private readonly LedgerService $ledger) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasAnyPermission('finance.view_all', 'finance.verify', 'finance.view_summary'), 403);
        $full = $user->hasAnyPermission('finance.view_all', 'finance.verify');

        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $trend = $months->map(function (Carbon $m) {
            $income = (int) \DB::table('ledger_entries')->join('ledger_transactions as t', 't.id', '=', 'ledger_entries.ledger_transaction_id')
                ->join('ledger_accounts as a', 'a.id', '=', 'ledger_entries.ledger_account_id')->where('a.type', 'income')
                ->whereBetween('t.txn_date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->selectRaw('COALESCE(SUM(credit)-SUM(debit),0) v')->value('v');
            $expense = (int) \DB::table('ledger_entries')->join('ledger_transactions as t', 't.id', '=', 'ledger_entries.ledger_transaction_id')
                ->join('ledger_accounts as a', 'a.id', '=', 'ledger_entries.ledger_account_id')->where('a.type', 'expense')
                ->whereBetween('t.txn_date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->selectRaw('COALESCE(SUM(debit)-SUM(credit),0) v')->value('v');
            $obs = Obligation::where('kind', 'monthly_contribution')->where('period', $m->format('Y-m'))->whereNotIn('status', ['waived', 'cancelled'])->get();

            return ['label' => $m->translatedFormat('M'), 'income' => $income, 'expense' => $expense,
                'rate' => $obs->count() ? (int) round($obs->where('status', 'paid')->count() / $obs->count() * 100) : null];
        });

        $investments = LedgerAccount::where('subtype', 'investment')->get()->map(fn ($a) => [
            'account' => $a,
            'cost' => $this->ledger->accountBalance($a),
            'valuation' => InvestmentValuation::where('ledger_account_id', $a->id)->latest('valued_on')->first(),
        ]);

        return view('treasury.index', [
            'full' => $full,
            'funds' => $this->ledger->fundBalances(),
            'accounts' => LedgerAccount::where('type', 'asset')->get()->map(fn ($a) => ['account' => $a, 'balance' => $this->ledger->accountBalance($a)]),
            'trend' => $trend,
            'investments' => $investments,
            'pendingSubmissions' => $full ? PaymentSubmission::where('status', 'pending')->count() : null,
            'expenses' => $full ? Expense::whereIn('status', ['submitted', 'approved'])->with('fund', 'requester.member')->latest()->limit(6)->get() : collect(),
            'recent' => $full ? LedgerTransaction::with('entries')->latest('id')->limit(8)->get() : collect(),
            'outstanding' => $full ? (int) Obligation::outstanding()->where('due_date', '<', now()->toDateString())->get()->sum(fn ($o) => $o->balance()) : null,
            'currentPeriod' => now()->format('Y-m'),
            'periodGenerated' => Obligation::where('kind', 'monthly_contribution')->where('period', now()->format('Y-m'))->exists(),
        ]);
    }

    public function members(Request $request): View
    {
        $rows = Member::contributing()->orderBy('full_name')->get()->map(fn (Member $m) => ['member' => $m, 'summary' => $this->contributions->summary($m)]);
        if ($request->query('filter') === 'arrears') {
            $rows = $rows->filter(fn ($r) => $r['summary']['overdue'] > 0);
        }

        return view('treasury.members', ['rows' => $rows->values(), 'arrearsRule' => PolicyRule::where('key', 'membership.arrears_months_exit')->first()]);
    }

    public function member(Member $member): View
    {
        return view('treasury.member', [
            'member' => $member,
            'summary' => $this->contributions->summary($member),
            'obligations' => Obligation::where('member_id', $member->id)->with('allocations')->orderByDesc('due_date')->get(),
            'payments' => VerifiedPayment::where('member_id', $member->id)->with(['allocations.obligation', 'verifier.member', 'depositAccount'])->orderByDesc('received_on')->get(),
        ]);
    }

    public function memberPdf(Member $member, ReportService $reports)
    {
        return $reports->memberStatementPdf($member);
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        if (! $this->contributions->ruleFor($data['period'])) {
            return back()->with('error', __('Hakuna kanuni ya mchango iliyoidhinishwa kwa kipindi hiki. Weka na uidhinishe kanuni kwanza (Hazina → Kanuni za michango).'));
        }
        $r = $this->contributions->generateMonthly($data['period'], $request->user());

        return back()->with('status', __('Michango ya :p: mipya :c, iliyokuwepo :e.', ['p' => $data['period'], 'c' => $r['created'], 'e' => $r['existing']]));
    }

    /** One-off obligation: entry fee, historical arrears carried over at cut-over, other. */
    public function addObligation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'kind' => ['required', Rule::in(['entry_fee', 'other'])],
            'amount' => ['required', 'string'],
            'fund' => ['required', 'exists:funds,key'],
            'description' => ['required', 'string', 'max:200'],
            'due_date' => ['required', 'date'],
            'is_historical' => ['boolean'],
        ]);
        $amount = Money::parse($data['amount']);
        $member = Member::findOrFail($data['member_id']);
        $this->contributions->addObligation($member, $data['kind'], (int) $amount, $data['description'], $data['due_date'],
            [['fund' => $data['fund'], 'amount' => (int) $amount]], $data['kind'].':'.md5($data['description'].$data['due_date']), $request->user(),
            ['is_historical' => $request->boolean('is_historical')]);

        return back()->with('status', __('Deni limeongezwa.'));
    }

    public function waive(Request $request, Obligation $obligation): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $this->contributions->waive($obligation, $data['reason'], $request->user());

        return back()->with('status', __('Deni limesamehewa na sababu imehifadhiwa.'));
    }

    public function penalties(Request $request): View
    {
        $period = $request->query('period', now()->subMonth()->format('Y-m'));
        $rule = PolicyRule::where('key', 'penalty.late_monthly_amount')->first();
        $late = Obligation::with('member')->where('kind', 'monthly_contribution')->where('period', $period)
            ->outstanding()->where('due_date', '<', now()->toDateString())->get()
            ->filter(fn ($o) => ! Obligation::where('member_id', $o->member_id)->where('uniqueness_key', 'penalty:late:'.$period)->exists());

        return view('treasury.penalties', ['period' => $period, 'rule' => $rule, 'late' => $late]);
    }

    /** Penalties are never automatic: the treasurer previews and applies, and only with a confirmed amount. */
    public function applyPenalties(Request $request): RedirectResponse
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-\d{2}$/'], 'members' => ['required', 'array'], 'members.*' => ['integer']]);
        $amount = PolicyRule::confirmedValue('penalty.late_monthly_amount');
        if (! $amount) {
            throw new DomainRuleException(__('finance.rule_not_confirmed'));
        }
        $n = 0;
        foreach (Member::whereIn('id', $data['members'])->get() as $m) {
            $ob = $this->contributions->addObligation($m, 'penalty', (int) $amount, __('Faini ya kuchelewa mchango — :p', ['p' => $data['period']]),
                now()->toDateString(), [['fund' => 'general', 'amount' => (int) $amount]], 'penalty:late:'.$data['period'], $request->user(), ['penalty_rule_key' => 'penalty.late_monthly_amount']);
            $n += $ob->wasRecentlyCreated ? 1 : 0;
        }

        return redirect()->route('treasury.penalties', ['period' => $data['period']])->with('status', __('Faini :n zimewekwa.', ['n' => $n]));
    }
}
