<?php

namespace App\Http\Controllers;

use App\Models\Fund;
use App\Models\InvestmentValuation;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Services\LedgerService;
use App\Services\TreasuryService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LedgerController extends Controller
{
    public function __construct(private readonly LedgerService $ledger, private readonly TreasuryService $treasury) {}

    public function index(Request $request): View
    {
        $txns = LedgerTransaction::with(['entries.account', 'entries.fund', 'poster.member'])
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('from'), fn ($q, $d) => $q->where('txn_date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->where('txn_date', '<=', $d))
            ->orderByDesc('txn_date')->orderByDesc('id')->paginate(30)->withQueryString();

        return view('treasury.ledger', ['txns' => $txns, 'categories' => LedgerTransaction::distinct()->pluck('category')]);
    }

    public function show(LedgerTransaction $transaction): View
    {
        return view('treasury.transaction', ['txn' => $transaction->load('entries.account', 'entries.fund', 'entries.member', 'poster.member', 'reverses', 'reversal', 'source')]);
    }

    public function reverse(Request $request, LedgerTransaction $transaction): RedirectResponse
    {
        // Payments must be reversed as payments (so allocations unwind too).
        if (in_array($transaction->source_type, ['payment'], true)) {
            return back()->with('error', __('Muamala huu ni wa malipo ya mwanachama — urudishe kupitia ukurasa wa mwanachama ili michango irekebishwe pia.'));
        }
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->ledger->reverse($transaction, $data['reason'], $request->user());
        if ($transaction->source_type === 'expense') {
            $transaction->source?->update(['status' => 'approved', 'ledger_transaction_id' => null, 'paid_on' => null]);
        }

        return back()->with('status', __('Muamala umerudishwa.'));
    }

    public function accounts(): View
    {
        return view('treasury.accounts', [
            'accounts' => LedgerAccount::orderBy('code')->get()->map(fn ($a) => ['account' => $a, 'balance' => $this->ledger->accountBalance($a)]),
            'funds' => Fund::orderBy('sort')->get(),
            'assets' => LedgerAccount::where('type', 'asset')->where('is_active', true)->get(),
            'valuations' => InvestmentValuation::with('account', 'recorder.member')->latest('valued_on')->limit(10)->get(),
        ]);
    }

    public function transfer(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'from' => ['required', 'exists:ledger_accounts,id'], 'to' => ['required', 'exists:ledger_accounts,id', 'different:from'],
            'fund' => ['required', 'exists:funds,id'], 'amount' => ['required', 'string'], 'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:200'], 'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $this->treasury->transfer(LedgerAccount::findOrFail($d['from']), LedgerAccount::findOrFail($d['to']), Fund::findOrFail($d['fund']),
            (int) Money::parse($d['amount']), $d['date'], $d['description'], $d['reference'] ?? null, $request->user());

        return back()->with('status', __('Uhamisho umerekodiwa.'));
    }

    public function interfund(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'account' => ['required', 'exists:ledger_accounts,id'], 'from_fund' => ['required', 'exists:funds,id'], 'to_fund' => ['required', 'exists:funds,id', 'different:from_fund'],
            'amount' => ['required', 'string'], 'date' => ['required', 'date'], 'reason' => ['required', 'string', 'min:5', 'max:300'],
        ]);
        $this->treasury->interfundTransfer(LedgerAccount::findOrFail($d['account']), Fund::findOrFail($d['from_fund']), Fund::findOrFail($d['to_fund']),
            (int) Money::parse($d['amount']), $d['date'], $d['reason'], $request->user());

        return back()->with('status', __('Fedha zimehamishwa kati ya mifuko.'));
    }

    public function opening(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'account' => ['required', 'exists:ledger_accounts,id'], 'fund' => ['required', 'exists:funds,id'],
            'amount' => ['required', 'string'], 'date' => ['required', 'date'], 'note' => ['required', 'string', 'max:200'],
        ]);
        $this->treasury->openingBalance(LedgerAccount::findOrFail($d['account']), Fund::findOrFail($d['fund']), (int) Money::parse($d['amount']), $d['date'], $d['note'], $request->user());

        return back()->with('status', __('Salio la kuanzia limerekodiwa.'));
    }

    public function investmentReturn(Request $request): RedirectResponse
    {
        $d = $request->validate(['account' => ['required', 'exists:ledger_accounts,id'], 'fund' => ['required', 'exists:funds,id'], 'amount' => ['required', 'string'], 'date' => ['required', 'date'], 'note' => ['required', 'string', 'max:200']]);
        $this->treasury->investmentReturn(LedgerAccount::findOrFail($d['account']), Fund::findOrFail($d['fund']), (int) Money::parse($d['amount']), $d['date'], $d['note'], $request->user());

        return back()->with('status', __('Faida ya uwekezaji imerekodiwa.'));
    }

    public function valuation(Request $request): RedirectResponse
    {
        $d = $request->validate(['account' => ['required', 'exists:ledger_accounts,id'], 'value' => ['required', 'string'], 'date' => ['required', 'date'], 'source' => ['nullable', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:300']]);
        $this->treasury->recordValuation(LedgerAccount::findOrFail($d['account']), (int) Money::parse($d['value']), $d['date'], $d['source'] ?? null, $d['notes'] ?? null, $request->user());

        return back()->with('status', __('Thamani ya uwekezaji imehifadhiwa (haiathiri fedha taslimu).'));
    }
}
