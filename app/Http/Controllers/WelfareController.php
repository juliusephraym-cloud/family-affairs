<?php

namespace App\Http\Controllers;

use App\Models\CharityActivity;
use App\Models\LedgerAccount;
use App\Models\Member;
use App\Models\NotificationTemplate;
use App\Models\WelfareCase;
use App\Services\NotificationService;
use App\Services\WelfareService;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WelfareController extends Controller
{
    public function __construct(private readonly WelfareService $welfare) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('welfare.index', [
            'cases' => WelfareCase::visibleTo($user)->with('beneficiary')->latest()->limit(30)->get(),
            'activities' => CharityActivity::with('coordinator', 'volunteers')->latest('planned_for')->limit(12)->get(),
            'members' => Member::contributing()->orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'type' => ['required', Rule::in(['bereavement', 'illness', 'hardship', 'celebration', 'other'])],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:2000'],
            'private_details' => ['nullable', 'string', 'max:5000'],
            'beneficiary_member_id' => ['nullable', 'exists:members,id'],
            'relationship' => ['nullable', Rule::in(['parent', 'child', 'spouse', 'other'])],
            'visibility' => ['required', Rule::in(['members', 'leaders', 'private'])],
        ]);
        $case = WelfareCase::create($d + ['status' => 'submitted', 'submitted_by' => $request->user()->id]);
        Audit::record('welfare.submitted', $case, 'Welfare case submitted');

        return redirect()->route('welfare.show', $case)->with('status', __('Taarifa imepokelewa. Uongozi utaipitia na kutangaza kwa utaratibu.'));
    }

    public function show(Request $request, WelfareCase $case): View
    {
        $user = $request->user();
        abort_unless(WelfareCase::visibleTo($user)->whereKey($case->id)->exists(), 404);
        $case->load('beneficiary', 'disbursements.recorder.member', 'submitter.member');
        $canPrivate = $user->hasPermission('welfare.view_private');
        $myObligation = $case->obligations()->where('member_id', $user->member_id)->with('allocations')->first();

        return view('welfare.show', [
            'case' => $case,
            'canPrivate' => $canPrivate,
            'proposal' => $this->welfare->proposeRequirement($case),
            'collection' => $user->hasAnyPermission('welfare.approve', 'welfare.disburse', 'finance.view_all') ? $this->welfare->collection($case) : null,
            'contributors' => $user->hasAnyPermission('welfare.approve', 'welfare.disburse', 'finance.view_all') ? $case->obligations()->with('member', 'allocations')->get() : collect(),
            'myObligation' => $myObligation,
            'accounts' => LedgerAccount::where('type', 'asset')->where('subtype', '!=', 'investment')->get(),
            'noticeTemplate' => NotificationTemplate::where('key', 'welfare_notice')->first(),
        ]);
    }

    public function approve(Request $request, WelfareCase $case, NotificationService $notifications): RedirectResponse
    {
        $d = $request->validate(['fund_payout' => ['nullable', 'string'], 'per_member' => ['nullable', 'string'], 'deadline' => ['nullable', 'date'], 'announce' => ['boolean']]);
        $this->welfare->approve($case, $request->user(),
            filled($d['fund_payout'] ?? null) ? Money::parse($d['fund_payout']) : 0,
            filled($d['per_member'] ?? null) ? Money::parse($d['per_member']) : 0,
            $d['deadline'] ?? null);

        $tpl = NotificationTemplate::where('key', 'welfare_notice')->first();
        if ($request->boolean('announce') && $case->visibility === 'members' && $tpl?->isSendable()) {
            $notifications->schedule($tpl, $case->title, ['type' => 'all_active'], now(), ['title' => $case->title, 'portal_link' => route('welfare.show', $case)], $request->user(), $case, 'welfare:'.$case->id);
        }

        return back()->with('status', __('Kesi imeidhinishwa.'));
    }

    public function reject(Request $request, WelfareCase $case): RedirectResponse
    {
        $this->welfare->reject($case, (string) $request->input('reason'), $request->user());

        return back()->with('status', __('Kesi imekataliwa.'));
    }

    public function disburse(Request $request, WelfareCase $case): RedirectResponse
    {
        $d = $request->validate(['amount' => ['required', 'string'], 'paid_on' => ['required', 'date'], 'recipient_name' => ['required', 'string', 'max:160'], 'reference' => ['nullable', 'string', 'max:100'], 'account' => ['required', 'exists:ledger_accounts,id'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $this->welfare->disburse($case, (int) Money::parse($d['amount']), $d['paid_on'], $d['recipient_name'], $d['reference'] ?? null, LedgerAccount::findOrFail($d['account']), $request->user(), $d['notes'] ?? null);

        return back()->with('status', __('Msaada uliotolewa umerekodiwa kwenye ledger.'));
    }

    public function close(Request $request, WelfareCase $case): RedirectResponse
    {
        $this->welfare->close($case, (string) $request->input('reason'), $request->user());

        return back()->with('status', __('Kesi imefungwa.'));
    }
}
