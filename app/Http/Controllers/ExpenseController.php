<?php

namespace App\Http\Controllers;

use App\Models\CharityActivity;
use App\Models\Event;
use App\Models\Expense;
use App\Models\Fund;
use App\Models\LedgerAccount;
use App\Services\TreasuryService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(private readonly TreasuryService $treasury) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasAnyPermission('finance.view_all', 'finance.request_expense', 'finance.approve_expense', 'finance.pay_expense'), 403);
        $seeAll = $user->hasAnyPermission('finance.view_all', 'finance.approve_expense', 'finance.pay_expense');

        return view('treasury.expenses', [
            'expenses' => Expense::with('fund', 'requester.member', 'approver.member', 'event')
                ->when(! $seeAll, fn ($q) => $q->where('requested_by', $user->id))
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->latest()->paginate(25)->withQueryString(),
            'funds' => Fund::where('is_active', true)->orderBy('sort')->get(),
            'events' => Event::whereIn('status', ['planned', 'confirmed', 'done'])->latest('starts_at')->limit(20)->get(),
            'activities' => CharityActivity::whereIn('status', ['proposed', 'approved', 'done'])->latest()->limit(20)->get(),
            'accounts' => LedgerAccount::where('type', 'asset')->where('subtype', '!=', 'investment')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(array_keys(TreasuryService::EXPENSE_ACCOUNT))],
            'amount' => ['required', 'string'],
            'expense_date' => ['required', 'date'],
            'purpose' => ['required', 'string', 'max:1000'],
            'fund_id' => ['required', 'exists:funds,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'charity_activity_id' => ['nullable', 'exists:charity_activities,id'],
            'document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.config('family.uploads.document_max_kb')],
        ]);
        $amount = Money::parse($d['amount']);
        if (! $amount) {
            return back()->withInput()->withErrors(['amount' => __('Weka kiasi sahihi.')]);
        }
        $path = $request->file('document')?->storeAs('expenses', Str::random(40).'.'.$request->file('document')->extension(), 'private');
        $this->treasury->submitExpense([
            'title' => $d['title'], 'category' => $d['category'], 'amount' => $amount, 'expense_date' => $d['expense_date'], 'purpose' => $d['purpose'],
            'fund_id' => $d['fund_id'], 'event_id' => $d['event_id'] ?? null, 'charity_activity_id' => $d['charity_activity_id'] ?? null, 'document_path' => $path,
        ], $request->user());

        return back()->with('status', __('Ombi la matumizi limewasilishwa kwa idhini.'));
    }

    public function decide(Request $request, Expense $expense): RedirectResponse
    {
        $d = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'reason' => ['nullable', 'string', 'max:500']]);
        $this->treasury->decideExpense($expense, $d['decision'] === 'approve', $request->user(), $d['reason'] ?? null);

        return back()->with('status', $d['decision'] === 'approve' ? __('Matumizi yameidhinishwa.') : __('Matumizi yamekataliwa.'));
    }

    public function pay(Request $request, Expense $expense): RedirectResponse
    {
        $d = $request->validate(['account' => ['required', 'exists:ledger_accounts,id'], 'paid_on' => ['required', 'date'], 'reference' => ['nullable', 'string', 'max:100']]);
        $this->treasury->payExpense($expense, LedgerAccount::findOrFail($d['account']), $d['paid_on'], $d['reference'] ?? null, $request->user());

        return back()->with('status', __('Malipo ya matumizi yamerekodiwa kwenye ledger.'));
    }

    public function document(Request $request, Expense $expense)
    {
        $u = $request->user();
        abort_unless($expense->requested_by === $u->id || $u->hasAnyPermission('finance.view_all', 'finance.approve_expense'), 404);
        abort_unless($expense->document_path && Storage::disk('private')->exists($expense->document_path), 404);

        return Storage::disk('private')->response($expense->document_path);
    }
}
