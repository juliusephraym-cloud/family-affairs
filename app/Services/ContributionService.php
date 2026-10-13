<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\ContributionRule;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\Member;
use App\Models\Obligation;
use App\Models\PaymentAllocation;
use App\Models\PaymentSubmission;
use App\Models\PolicyRule;
use App\Models\User;
use App\Models\VerifiedPayment;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Member obligations and payments.
 *
 * Ledger shape of a verified contribution payment of 25,000 against a
 * 20,000 obligation split general 10,000 / welfare 10,000:
 *   Dr Bank[general] 10,000   Cr 4000 Contributions[general] 10,000
 *   Dr Bank[welfare] 10,000   Cr 4000 Contributions[welfare] 10,000
 *   Dr Bank[general]  5,000   Cr 2100 Member credit[general]  5,000   (unallocated remainder)
 * When the remainder is later applied, it is moved out of 2100 and into the
 * right fund, so every transaction still balances per fund.
 */
class ContributionService
{
    public const INCOME_ACCOUNT = [
        'monthly_contribution' => '4000',
        'entry_fee' => '4010',
        'welfare_levy' => '4100',
        'penalty' => '4200',
        'other' => '4400',
    ];

    /** Non-contribution purposes go straight to a fund without touching obligations. */
    public const PURPOSES = [
        'sadaka' => ['fund' => 'charity', 'account' => '4300'],
        'donation' => ['fund' => 'general', 'account' => '4400'],
        'welfare' => ['fund' => 'welfare', 'account' => '4100'],
        'event' => ['fund' => 'events', 'account' => '4400'],
    ];

    public function __construct(private readonly LedgerService $ledger) {}

    // ------------------------------------------------------------------ obligations

    public function ruleFor(string $period, string $category = 'standard'): ?ContributionRule
    {
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfDay();

        return ContributionRule::where('status', 'approved')
            ->where('membership_category', $category)
            ->where('effective_from', '<=', $start->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start->toDateString()))
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Idempotently create the monthly contribution obligations for a period.
     *
     * @return array{created: int, existing: int, skipped: array<string>}
     */
    public function generateMonthly(string $period, ?User $by = null): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new DomainRuleException('Invalid period.');
        }
        $periodEnd = Carbon::createFromFormat('Y-m-d', $period.'-01')->endOfMonth();
        $created = 0;
        $existing = 0;
        $skipped = [];

        Member::contributing()->orderBy('full_name')->get()->each(function (Member $m) use ($period, $periodEnd, $by, &$created, &$existing, &$skipped) {
            if ($m->joined_on && $m->joined_on->gt($periodEnd)) {
                return;
            }
            $rule = $this->ruleFor($period, $m->membership_category);
            if (! $rule) {
                $skipped[] = $m->full_name;

                return;
            }
            $due = Carbon::createFromFormat('Y-m-d', $period.'-01')->day(min($rule->due_day, $periodEnd->day));
            $ob = Obligation::firstOrCreate(
                ['member_id' => $m->id, 'uniqueness_key' => 'monthly:'.$period],
                [
                    'kind' => 'monthly_contribution',
                    'period' => $period,
                    'description' => __('finance.monthly_for', ['period' => Carbon::createFromFormat('Y-m', $period)->translatedFormat('F Y')]),
                    'amount' => $rule->amount,
                    'fund_splits' => $rule->fund_splits,
                    'due_date' => $due,
                    'contribution_rule_id' => $rule->id,
                    'created_by' => $by?->id,
                ],
            );
            $ob->wasRecentlyCreated ? $created++ : $existing++;
            if ($ob->wasRecentlyCreated) {
                $this->applyAvailableCredit($m, $by);
            }
        });

        if ($created) {
            Audit::record('finance.obligations_generated', null, "Monthly contributions generated for {$period}", compact('period', 'created', 'existing'));
        }

        return compact('created', 'existing', 'skipped');
    }

    public function addObligation(Member $member, string $kind, int $amount, string $description, string $dueDate, array $fundSplits, string $uniquenessKey, ?User $by, array $extra = []): Obligation
    {
        if ($amount <= 0) {
            throw new DomainRuleException(__('finance.amount_positive'));
        }
        $this->assertSplits($fundSplits, $amount);

        $ob = Obligation::firstOrCreate(
            ['member_id' => $member->id, 'uniqueness_key' => $uniquenessKey],
            array_merge([
                'kind' => $kind,
                'description' => $description,
                'amount' => $amount,
                'fund_splits' => $fundSplits,
                'due_date' => $dueDate,
                'created_by' => $by?->id,
            ], $extra),
        );
        if ($ob->wasRecentlyCreated) {
            Audit::record('finance.obligation_added', $ob, "{$kind} ".Money::format($amount)." for {$member->full_name}", ['reason' => $description]);
            $this->applyAvailableCredit($member, $by);
        }

        return $ob;
    }

    public function waive(Obligation $ob, string $reason, User $by): void
    {
        if (trim($reason) === '') {
            throw new DomainRuleException(__('finance.reason_required'));
        }
        if (! in_array($ob->status, ['open', 'partially_paid'], true)) {
            throw new DomainRuleException(__('finance.cannot_waive'));
        }
        $ob->update(['status' => 'waived', 'status_reason' => $reason]);
        Audit::record('finance.obligation_waived', $ob, 'Obligation waived', ['reason' => $reason, 'unpaid' => $ob->balance()]);
    }

    // ------------------------------------------------------------------ payments

    /**
     * Treasurer confirms a member's submission. Separation of duties: nobody
     * verifies a payment made for their own member record.
     */
    public function verifySubmission(PaymentSubmission $submission, LedgerAccount $depositAccount, User $by, ?int $amount = null, ?string $note = null): VerifiedPayment
    {
        if ($submission->member_id === $by->member_id) {
            throw new DomainRuleException(__('finance.no_self_verify'));
        }

        return DB::transaction(function () use ($submission, $depositAccount, $by, $amount, $note) {
            $sub = PaymentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            if ($sub->status !== 'pending') {
                throw new DomainRuleException(__('finance.submission_not_pending'));
            }
            $payment = $this->recordPayment(
                member: $sub->member,
                amount: $amount ?? $sub->amount,
                receivedOn: $sub->paid_on->toDateString(),
                method: $sub->method,
                depositAccount: $depositAccount,
                reference: $sub->transaction_reference,
                purpose: $sub->purpose,
                purposeTargetId: $sub->purpose_target_id,
                by: $by,
                notes: $note,
                submission: $sub,
            );
            $sub->update(['status' => 'verified', 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'verified_payment_id' => $payment->id]);

            return $payment;
        });
    }

    public function rejectSubmission(PaymentSubmission $submission, string $reason, User $by): void
    {
        if (trim($reason) === '') {
            throw new DomainRuleException(__('finance.reason_required'));
        }
        DB::transaction(function () use ($submission, $reason, $by) {
            $sub = PaymentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            if ($sub->status !== 'pending') {
                throw new DomainRuleException(__('finance.submission_not_pending'));
            }
            $sub->update(['status' => 'rejected', 'rejection_reason' => $reason, 'reviewed_by' => $by->id, 'reviewed_at' => now()]);
            Audit::record('finance.submission_rejected', $sub, 'Payment submission rejected', ['reason' => $reason]);
        });
    }

    /** Create a verified payment and post it. Used for verified submissions and direct entry by the treasurer. */
    public function recordPayment(
        Member $member,
        int $amount,
        string $receivedOn,
        string $method,
        LedgerAccount $depositAccount,
        ?string $reference,
        string $purpose,
        ?int $purposeTargetId,
        User $by,
        ?string $notes = null,
        ?PaymentSubmission $submission = null,
    ): VerifiedPayment {
        if ($amount <= 0) {
            throw new DomainRuleException(__('finance.amount_positive'));
        }
        if (! $depositAccount->accepts_deposits || $depositAccount->type !== 'asset') {
            throw new DomainRuleException('That account cannot receive member payments.');
        }
        if ($purpose !== 'contributions' && ! isset(self::PURPOSES[$purpose])) {
            throw new DomainRuleException('Unknown payment purpose.');
        }
        $refNorm = self::normalizeReference($reference);
        if ($refNorm && VerifiedPayment::where('reference_normalized', $refNorm)->exists()) {
            throw new DomainRuleException(__('finance.duplicate_reference', ['ref' => $reference]));
        }

        return DB::transaction(function () use ($member, $amount, $receivedOn, $method, $depositAccount, $reference, $refNorm, $purpose, $purposeTargetId, $by, $notes, $submission) {
            try {
                $payment = VerifiedPayment::create([
                    'member_id' => $member->id,
                    'payment_submission_id' => $submission?->id,
                    'amount' => $amount,
                    'received_on' => $receivedOn,
                    'method' => $method,
                    'deposit_account_id' => $depositAccount->id,
                    'transaction_reference' => $reference,
                    'reference_normalized' => $refNorm,
                    'purpose' => $purpose,
                    'purpose_target_id' => $purposeTargetId,
                    'verified_by' => $by->id,
                    'verified_at' => now(),
                    'notes' => $notes,
                ]);
            } catch (QueryException $e) {
                // Unique index on reference_normalized: a concurrent duplicate lost the race.
                throw new DomainRuleException(__('finance.duplicate_reference', ['ref' => $reference]));
            }

            $lines = [];
            $plan = [];
            if ($purpose === 'contributions') {
                $plan = $this->planAllocation($member, $amount);
                foreach ($plan as $p) {
                    foreach ($this->splitAmount($p['amount'], $p['obligation']->fund_splits) as $fund => $part) {
                        $lines[] = ['account' => $depositAccount, 'fund' => $fund, 'debit' => $part, 'member_id' => $member->id];
                        $lines[] = ['account' => self::INCOME_ACCOUNT[$p['obligation']->kind], 'fund' => $fund, 'credit' => $part, 'member_id' => $member->id, 'memo' => $p['obligation']->description];
                    }
                }
                $remainder = $amount - array_sum(array_column($plan, 'amount'));
                if ($remainder > 0) {
                    $lines[] = ['account' => $depositAccount, 'fund' => 'general', 'debit' => $remainder, 'member_id' => $member->id];
                    $lines[] = ['account' => '2100', 'fund' => 'general', 'credit' => $remainder, 'member_id' => $member->id, 'memo' => 'Unallocated credit'];
                }
            } else {
                $target = self::PURPOSES[$purpose];
                $lines[] = ['account' => $depositAccount, 'fund' => $target['fund'], 'debit' => $amount, 'member_id' => $member->id];
                $lines[] = ['account' => $target['account'], 'fund' => $target['fund'], 'credit' => $amount, 'member_id' => $member->id, 'memo' => ucfirst($purpose)];
            }

            $txn = $this->ledger->post(
                date: $receivedOn,
                category: $purpose === 'contributions' ? 'member_payment' : 'member_'.$purpose,
                description: 'Payment from '.$member->full_name,
                lines: $lines,
                by: $by,
                source: $payment,
                reference: $reference,
                idempotencyKey: 'payment:'.$payment->id,
            );
            $payment->update(['ledger_transaction_id' => $txn->id]);

            foreach ($plan as $p) {
                PaymentAllocation::create([
                    'verified_payment_id' => $payment->id,
                    'obligation_id' => $p['obligation']->id,
                    'amount' => $p['amount'],
                    'ledger_transaction_id' => $txn->id,
                ]);
                $this->refreshStatus($p['obligation']);
            }

            Audit::record('finance.payment_verified', $payment, 'Payment verified: '.Money::format($amount).' from '.$member->full_name, [
                'reference' => $reference, 'purpose' => $purpose, 'allocated' => array_sum(array_column($plan, 'amount')),
            ]);

            return $payment->fresh();
        });
    }

    /** Reverse a verified payment: all its ledger postings and allocations are reversed, never deleted. */
    public function reversePayment(VerifiedPayment $payment, string $reason, User $by): void
    {
        if (trim($reason) === '') {
            throw new DomainRuleException(__('finance.reason_required'));
        }
        DB::transaction(function () use ($payment, $reason, $by) {
            $p = VerifiedPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($p->status !== 'active') {
                throw new DomainRuleException(__('finance.already_reversed'));
            }
            $txnIds = $p->allocations()->where('status', 'active')->pluck('ledger_transaction_id')
                ->push($p->ledger_transaction_id)->filter()->unique();
            foreach (LedgerTransaction::whereIn('id', $txnIds)->where('status', 'posted')->get() as $txn) {
                $this->ledger->reverse($txn, $reason, $by);
            }
            $obligationIds = $p->allocations()->where('status', 'active')->pluck('obligation_id');
            $p->allocations()->update(['status' => 'reversed']);
            Obligation::whereIn('id', $obligationIds)->get()->each(fn ($o) => $this->refreshStatus($o));
            // Freeing the reference lets a corrected payment be entered again.
            $p->update(['status' => 'reversed', 'reversed_by' => $by->id, 'reversed_at' => now(), 'reversal_reason' => $reason, 'reference_normalized' => null]);
            Audit::record('finance.payment_reversed', $p, 'Payment reversed', ['reason' => $reason, 'amount' => $p->amount]);
        });
    }

    /** Apply any unallocated remainder of past payments to open obligations. */
    public function applyAvailableCredit(Member $member, ?User $by = null): int
    {
        $applied = 0;
        $payments = VerifiedPayment::where('member_id', $member->id)->where('status', 'active')
            ->where('purpose', 'contributions')->orderBy('received_on')->orderBy('id')->get();

        foreach ($payments as $payment) {
            $remaining = $payment->amount - $payment->allocatedAmount();
            if ($remaining <= 0) {
                continue;
            }
            $plan = $this->planAllocation($member, $remaining);
            if (! $plan) {
                break;
            }
            DB::transaction(function () use ($payment, $plan, $member, $by, &$applied) {
                $total = array_sum(array_column($plan, 'amount'));
                $lines = [
                    ['account' => '2100', 'fund' => 'general', 'debit' => $total, 'member_id' => $member->id, 'memo' => 'Credit applied'],
                    ['account' => $payment->depositAccount, 'fund' => 'general', 'credit' => $total, 'member_id' => $member->id],
                ];
                foreach ($plan as $p) {
                    foreach ($this->splitAmount($p['amount'], $p['obligation']->fund_splits) as $fund => $part) {
                        $lines[] = ['account' => $payment->depositAccount, 'fund' => $fund, 'debit' => $part, 'member_id' => $member->id];
                        $lines[] = ['account' => self::INCOME_ACCOUNT[$p['obligation']->kind], 'fund' => $fund, 'credit' => $part, 'member_id' => $member->id, 'memo' => $p['obligation']->description];
                    }
                }
                $txn = $this->ledger->post(now()->toDateString(), 'credit_applied', 'Credit applied for '.$member->full_name, $lines, $by, $payment);
                foreach ($plan as $p) {
                    PaymentAllocation::create([
                        'verified_payment_id' => $payment->id,
                        'obligation_id' => $p['obligation']->id,
                        'amount' => $p['amount'],
                        'ledger_transaction_id' => $txn->id,
                    ]);
                    $this->refreshStatus($p['obligation']);
                }
                $applied += $total;
            });
        }

        return $applied;
    }

    // ------------------------------------------------------------------ queries

    /** @return array{outstanding: int, credit: int, months_in_arrears: int, overdue: int} */
    public function summary(Member $member): array
    {
        $open = $member->obligations()->outstanding()->with('allocations')->get();
        $outstanding = $open->sum(fn (Obligation $o) => max(0, $o->amount - $o->allocations->sum('amount')));
        $overdue = $open->filter(fn ($o) => $o->due_date->isPast())->sum(fn ($o) => max(0, $o->amount - $o->allocations->sum('amount')));
        $months = $open->where('kind', 'monthly_contribution')->filter(fn ($o) => $o->due_date->isPast())->count();
        $credit = VerifiedPayment::where('member_id', $member->id)->where('status', 'active')->where('purpose', 'contributions')->get()
            ->sum(fn ($p) => $p->amount - $p->allocatedAmount());

        return ['outstanding' => (int) $outstanding, 'credit' => (int) $credit, 'months_in_arrears' => $months, 'overdue' => (int) $overdue];
    }

    public static function normalizeReference(?string $ref): ?string
    {
        $n = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $ref) ?? '');

        return $n === '' ? null : mb_substr($n, 0, 100);
    }

    /** Pending submissions whose reference matches anything already seen. */
    public function duplicateWarnings(PaymentSubmission $sub): Collection
    {
        if (! $sub->reference_normalized) {
            return collect();
        }

        return collect()
            ->merge(VerifiedPayment::where('reference_normalized', $sub->reference_normalized)->get()->map(fn ($p) => __('finance.dup_verified', ['date' => $p->received_on->format('d/m/Y')])))
            ->merge(PaymentSubmission::where('reference_normalized', $sub->reference_normalized)->where('id', '!=', $sub->id)->whereIn('status', ['pending', 'verified'])->get()->map(fn ($s) => __('finance.dup_submission', ['date' => $s->created_at->format('d/m/Y')])));
    }

    // ------------------------------------------------------------------ internals

    /**
     * Decide which open obligations a sum pays. Order: oldest due first. If the
     * family has CONFIRMED the rule "payments.penalties_first", penalties are
     * cleared before contributions (recorded in the June 2026 minutes, but it
     * only takes effect once confirmed).
     *
     * @return array<int, array{obligation: Obligation, amount: int}>
     */
    private function planAllocation(Member $member, int $amount): array
    {
        $open = Obligation::where('member_id', $member->id)->outstanding()
            ->with('allocations')->lockForUpdate()->get();
        $penaltiesFirst = (bool) PolicyRule::confirmedValue('payments.penalties_first');
        $open = $open->sortBy(fn (Obligation $o) => [($penaltiesFirst && $o->kind === 'penalty') ? 0 : 1, $o->due_date->timestamp, $o->id])->values();

        $plan = [];
        foreach ($open as $o) {
            if ($amount <= 0) {
                break;
            }
            $due = $o->amount - (int) $o->allocations->sum('amount');
            if ($due <= 0) {
                continue;
            }
            $take = min($due, $amount);
            $plan[] = ['obligation' => $o, 'amount' => $take];
            $amount -= $take;
        }

        return $plan;
    }

    /** Proportional split across funds; the last fund absorbs rounding. @return array<string,int> */
    private function splitAmount(int $amount, array $splits): array
    {
        $total = array_sum(array_column($splits, 'amount'));
        $out = [];
        $assigned = 0;
        foreach (array_values($splits) as $i => $s) {
            $part = $i === count($splits) - 1 ? $amount - $assigned : intdiv($amount * (int) $s['amount'], max(1, $total));
            $assigned += $part;
            if ($part > 0) {
                $out[$s['fund']] = ($out[$s['fund']] ?? 0) + $part;
            }
        }

        return $out;
    }

    private function refreshStatus(Obligation $o): void
    {
        $o->refresh();
        if (in_array($o->status, ['waived', 'cancelled'], true)) {
            return;
        }
        $paid = (int) $o->allocations()->sum('amount');
        $o->update(['status' => $paid >= $o->amount ? 'paid' : ($paid > 0 ? 'partially_paid' : 'open')]);
    }

    private function assertSplits(array $splits, int $amount): void
    {
        if (! $splits || array_sum(array_column($splits, 'amount')) !== $amount) {
            throw new DomainRuleException('Fund splits must add up to the amount.');
        }
    }
}
