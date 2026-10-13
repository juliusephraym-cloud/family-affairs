<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Fund;
use App\Models\LedgerAccount;
use App\Models\Member;
use App\Models\PolicyRule;
use App\Models\User;
use App\Models\WelfareCase;
use App\Models\WelfareDisbursement;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Bereavement and welfare cases. Amounts are computed ONLY from confirmed
 * policy rules. The rule the secretary quoted from the constitution
 * ("fund pays 200,000; each member contributes 30,000 within 7 days, for a
 * parent, own child or spouse") is seeded as source_unconfirmed and must be
 * confirmed by leadership before this service will use it.
 */
class WelfareService
{
    public function __construct(
        private readonly ContributionService $contributions,
        private readonly LedgerService $ledger,
    ) {}

    /**
     * @return array{eligible: bool, fund_payout: ?int, per_member: ?int, days: ?int, missing: array<string>, reason: ?string}
     */
    public function proposeRequirement(WelfareCase $case): array
    {
        if ($case->type !== 'bereavement') {
            return ['eligible' => true, 'fund_payout' => null, 'per_member' => null, 'days' => null, 'missing' => [], 'reason' => __('welfare.manual_amounts')];
        }
        $keys = ['bereavement.fund_payout', 'bereavement.member_levy', 'bereavement.levy_days', 'bereavement.eligible_relationships'];
        $rules = PolicyRule::whereIn('key', $keys)->get()->keyBy('key');
        $missing = collect($keys)->reject(fn ($k) => $rules->get($k)?->isConfirmed())->values()->all();
        $eligibleRel = $rules->get('bereavement.eligible_relationships')?->value['value'] ?? [];

        return [
            'eligible' => in_array($case->relationship, (array) $eligibleRel, true),
            'fund_payout' => $missing ? null : (int) $rules['bereavement.fund_payout']->scalar(),
            'per_member' => $missing ? null : (int) $rules['bereavement.member_levy']->scalar(),
            'days' => $missing ? null : (int) $rules['bereavement.levy_days']->scalar(),
            'missing' => $missing,
            'reason' => $missing ? __('welfare.rules_unconfirmed') : null,
        ];
    }

    /**
     * Approve a case. For bereavement the amounts come from confirmed rules;
     * for other types the approver states amounts explicitly (may be zero).
     */
    public function approve(WelfareCase $case, User $by, ?int $fundPayout = null, ?int $perMember = null, ?string $deadline = null): void
    {
        if ($case->submitted_by === $by->id) {
            throw new DomainRuleException(__('welfare.no_self_approve'));
        }
        if ($case->status !== 'submitted') {
            throw new DomainRuleException(__('welfare.not_pending'));
        }

        $proposal = $this->proposeRequirement($case);
        if ($case->type === 'bereavement') {
            if ($proposal['missing']) {
                throw new DomainRuleException(__('welfare.rules_unconfirmed'));
            }
            if (! $proposal['eligible']) {
                throw new DomainRuleException(__('welfare.not_eligible'));
            }
            $fundPayout = $proposal['fund_payout'];
            $perMember = $proposal['per_member'];
            $deadline = now()->addDays($proposal['days'])->toDateString();
        }

        DB::transaction(function () use ($case, $by, $fundPayout, $perMember, $deadline, $proposal) {
            $case->update([
                'status' => $perMember > 0 ? 'collecting' : 'approved',
                'approved_by' => $by->id,
                'approved_at' => now(),
                'fund_payout_amount' => (int) $fundPayout,
                'per_member_amount' => (int) $perMember,
                'contribution_deadline' => $deadline,
                'policy_snapshot' => $proposal + ['approved_at' => now()->toIso8601String()],
            ]);

            if ($perMember > 0) {
                Member::contributing()->where('id', '!=', $case->beneficiary_member_id)->get()
                    ->each(fn (Member $m) => $this->contributions->addObligation(
                        $m, 'welfare_levy', $perMember, __('welfare.levy_for', ['title' => $case->title]),
                        $deadline ?? now()->addDays(7)->toDateString(),
                        [['fund' => 'welfare', 'amount' => $perMember]],
                        'welfare:'.$case->id, $by, ['welfare_case_id' => $case->id],
                    ));
            }
            Audit::record('welfare.approved', $case, 'Welfare case approved', ['fund_payout' => $fundPayout, 'per_member' => $perMember]);
        });
    }

    public function reject(WelfareCase $case, string $reason, User $by): void
    {
        if (trim($reason) === '' || $case->status !== 'submitted') {
            throw new DomainRuleException(__('finance.reason_required'));
        }
        $case->update(['status' => 'rejected', 'rejection_reason' => $reason, 'approved_by' => $by->id]);
        Audit::record('welfare.rejected', $case, 'Welfare case rejected', ['reason' => $reason]);
    }

    public function disburse(WelfareCase $case, int $amount, string $paidOn, string $recipient, ?string $reference, LedgerAccount $from, User $by, ?string $notes = null): WelfareDisbursement
    {
        if (! in_array($case->status, ['approved', 'collecting', 'disbursed'], true)) {
            throw new DomainRuleException(__('welfare.not_approved'));
        }
        if ($amount <= 0) {
            throw new DomainRuleException(__('finance.amount_positive'));
        }
        $fund = Fund::byKey('welfare');
        $available = $this->ledger->accountBalance($from, $fund->id);
        if ($available < $amount) {
            throw new DomainRuleException(__('finance.insufficient', ['fund' => $fund->label(), 'available' => Money::format($available)]));
        }

        return DB::transaction(function () use ($case, $amount, $paidOn, $recipient, $reference, $from, $by, $notes, $fund) {
            $d = WelfareDisbursement::create([
                'welfare_case_id' => $case->id, 'amount' => $amount, 'paid_on' => $paidOn, 'recipient_name' => $recipient,
                'reference' => $reference, 'from_account_id' => $from->id, 'fund_id' => $fund->id, 'recorded_by' => $by->id, 'notes' => $notes,
            ]);
            $txn = $this->ledger->post($paidOn, 'welfare_payout', 'Welfare support: '.$case->title, [
                ['account' => '5100', 'fund' => $fund, 'debit' => $amount, 'member_id' => $case->beneficiary_member_id],
                ['account' => $from, 'fund' => $fund, 'credit' => $amount],
            ], $by, $d, $reference, 'welfare-disbursement:'.$d->id);
            $d->update(['ledger_transaction_id' => $txn->id]);
            $case->update(['status' => 'disbursed']);
            Audit::record('welfare.disbursed', $case, 'Welfare support paid: '.Money::format($amount));

            return $d;
        });
    }

    public function close(WelfareCase $case, string $summary, User $by): void
    {
        if (trim($summary) === '') {
            throw new DomainRuleException(__('finance.reason_required'));
        }
        $case->update(['status' => 'closed', 'closed_at' => now(), 'closing_summary' => $summary]);
        Audit::record('welfare.closed', $case, 'Welfare case closed');
    }

    /** @return array{expected: int, collected: int, members_paid: int, members_total: int} */
    public function collection(WelfareCase $case): array
    {
        $obs = $case->obligations()->with('allocations')->get();

        return [
            'expected' => (int) $obs->whereNotIn('status', ['waived', 'cancelled'])->sum('amount'),
            'collected' => (int) $obs->sum(fn ($o) => $o->allocations->sum('amount')),
            'members_paid' => $obs->where('status', 'paid')->count(),
            'members_total' => $obs->whereNotIn('status', ['waived', 'cancelled'])->count(),
        ];
    }
}
