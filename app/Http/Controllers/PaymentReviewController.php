<?php

namespace App\Http\Controllers;

use App\Models\LedgerAccount;
use App\Models\Member;
use App\Models\PaymentSubmission;
use App\Models\VerifiedPayment;
use App\Services\ContributionService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentReviewController extends Controller
{
    public function __construct(private readonly ContributionService $contributions) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $subs = PaymentSubmission::with('member', 'reviewer.member')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderBy($status === 'pending' ? 'created_at' : 'reviewed_at', $status === 'pending' ? 'asc' : 'desc')->limit(100)->get();

        return view('treasury.submissions', [
            'submissions' => $subs,
            'status' => $status,
            'warnings' => $subs->mapWithKeys(fn ($s) => [$s->id => $this->contributions->duplicateWarnings($s)]),
            'summaries' => $subs->where('status', 'pending')->mapWithKeys(fn ($s) => [$s->id => $this->contributions->summary($s->member)]),
            'accounts' => LedgerAccount::where('accepts_deposits', true)->where('is_active', true)->get(),
        ]);
    }

    public function verify(Request $request, PaymentSubmission $submission): RedirectResponse
    {
        $data = $request->validate([
            'deposit_account_id' => ['required', 'exists:ledger_accounts,id'],
            'amount' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $amount = filled($data['amount'] ?? null) ? Money::parse($data['amount']) : null;
        $this->contributions->verifySubmission($submission, LedgerAccount::findOrFail($data['deposit_account_id']), $request->user(), $amount, $data['note'] ?? null);

        return back()->with('status', __('Malipo ya :n yamethibitishwa na kugawanywa kwenye michango.', ['n' => $submission->member->full_name]));
    }

    public function reject(Request $request, PaymentSubmission $submission): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $this->contributions->rejectSubmission($submission, $data['reason'], $request->user());

        return back()->with('status', __('Taarifa imekataliwa. Mwanachama ataona sababu.'));
    }

    public function createDirect(): View
    {
        return view('treasury.record-payment', [
            'members' => Member::whereIn('membership_status', ['active', 'suspended', 'invited', 'resigned'])->orderBy('full_name')->get(),
            'accounts' => LedgerAccount::where('accepts_deposits', true)->where('is_active', true)->get(),
        ]);
    }

    /** Treasurer records a payment they received directly (cash, bank statement line). */
    public function storeDirect(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'amount' => ['required', 'string'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(['bank_transfer', 'mobile_money', 'cash', 'other'])],
            'deposit_account_id' => ['required', 'exists:ledger_accounts,id'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'purpose' => ['required', Rule::in(['contributions', 'sadaka', 'donation', 'welfare', 'event'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $member = Member::findOrFail($data['member_id']);
        if ($member->id === $request->user()->member_id) {
            return back()->withInput()->with('error', __('finance.no_self_verify'));
        }
        $this->contributions->recordPayment($member, (int) Money::parse($data['amount']), $data['received_on'], $data['method'],
            LedgerAccount::findOrFail($data['deposit_account_id']), $data['transaction_reference'] ?? null, $data['purpose'], null, $request->user(), $data['notes'] ?? null);

        return redirect()->route('treasury.member', $member)->with('status', __('Malipo yamerekodiwa.'));
    }

    public function reverse(Request $request, VerifiedPayment $payment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->contributions->reversePayment($payment, $data['reason'], $request->user());

        return back()->with('status', __('Malipo yamerudishwa (reversed). Historia ya awali imebaki kwenye kumbukumbu.'));
    }
}
