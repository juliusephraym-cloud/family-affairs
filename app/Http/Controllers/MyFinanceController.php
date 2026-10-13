<?php

namespace App\Http\Controllers;

use App\Models\Obligation;
use App\Models\PaymentSubmission;
use App\Models\VerifiedPayment;
use App\Services\ContributionService;
use App\Services\ReportService;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** A member's own money: only ever the signed-in member's records. */
class MyFinanceController extends Controller
{
    public function index(Request $request, ContributionService $contributions): View
    {
        $member = $request->user()->member;

        return view('finance.mine', [
            'member' => $member,
            'summary' => $contributions->summary($member),
            'obligations' => Obligation::where('member_id', $member->id)->with('allocations')->orderByDesc('due_date')->limit(36)->get(),
            'payments' => VerifiedPayment::where('member_id', $member->id)->with('allocations.obligation')->orderByDesc('received_on')->limit(24)->get(),
            'submissions' => PaymentSubmission::where('member_id', $member->id)->latest()->limit(12)->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('finance.pay', [
            'instructions' => Settings::get('payments.instructions'),
            'summary' => app(ContributionService::class)->summary($request->user()->member),
        ]);
    }

    /** A payment NOTIFICATION. Nothing is marked paid until a finance officer verifies it. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'string', 'max:20'],
            'paid_on' => ['required', 'date', 'before_or_equal:today', 'after:2020-01-01'],
            'method' => ['required', Rule::in(['bank_transfer', 'mobile_money', 'cash', 'other'])],
            'channel_detail' => ['nullable', 'string', 'max:64'],
            'transaction_reference' => ['nullable', 'string', 'max:100', 'required_unless:method,cash'],
            'purpose' => ['required', Rule::in(['contributions', 'sadaka', 'donation', 'welfare', 'event'])],
            'note' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.config('family.uploads.receipt_max_kb')],
        ]);
        $amount = Money::parse($data['amount']);
        if (! $amount || $amount < 100 || $amount > 100_000_000) {
            return back()->withInput()->withErrors(['amount' => __('Weka kiasi sahihi kwa shilingi.')]);
        }

        $receipt = null;
        if ($file = $request->file('receipt')) {
            $receipt = $file->storeAs('receipts/'.now()->format('Y/m'), Str::random(40).'.'.$file->extension(), 'private');
        }
        $member = $request->user()->member;
        $sub = PaymentSubmission::create([
            'member_id' => $member->id,
            'submitted_by' => $request->user()->id,
            'amount' => $amount,
            'paid_on' => $data['paid_on'],
            'method' => $data['method'],
            'channel_detail' => $data['channel_detail'] ?? null,
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'reference_normalized' => ContributionService::normalizeReference($data['transaction_reference'] ?? null),
            'purpose' => $data['purpose'],
            'note' => $data['note'] ?? null,
            'receipt_path' => $receipt,
            'status' => 'pending',
        ]);
        Audit::record('finance.submission_created', $sub, 'Payment notification submitted: '.Money::format($amount));

        return redirect()->route('finance.mine')->with('status', __('Asante! Taarifa ya malipo imetumwa kwa Mhazini. Itaonekana kama "imethibitishwa" baada ya kukaguliwa.'));
    }

    public function withdraw(Request $request, PaymentSubmission $submission): RedirectResponse
    {
        abort_unless($submission->member_id === $request->user()->member_id && $submission->status === 'pending', 404);
        $submission->update(['status' => 'withdrawn']);

        return back()->with('status', __('Taarifa imeondolewa.'));
    }

    public function receipt(Request $request, PaymentSubmission $submission)
    {
        $user = $request->user();
        abort_unless($submission->member_id === $user->member_id || $user->hasAnyPermission('finance.verify', 'finance.view_all'), 404);
        abort_unless($submission->receipt_path && Storage::disk('private')->exists($submission->receipt_path), 404);

        return Storage::disk('private')->response($submission->receipt_path, null, ['Content-Disposition' => 'inline', 'Cache-Control' => 'private, no-store']);
    }

    public function statementPdf(Request $request, ReportService $reports)
    {
        return $reports->memberStatementPdf($request->user()->member);
    }
}
