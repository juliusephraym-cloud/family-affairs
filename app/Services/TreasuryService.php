<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Approval;
use App\Models\Expense;
use App\Models\Fund;
use App\Models\InvestmentValuation;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public const EXPENSE_ACCOUNT = [
        'event' => '5000',
        'welfare' => '5100',
        'bereavement' => '5100',
        'charity' => '5200',
        'gift' => '5300',
        'admin' => '5400',
        'refund' => '5500',
        'other' => '5400',
    ];

    public function __construct(private readonly LedgerService $ledger) {}

    // ------------------------------------------------------------------ expenses

    public function submitExpense(array $data, User $by): Expense
    {
        $expense = Expense::create($data + ['requested_by' => $by->id, 'status' => 'submitted']);
        Approval::create(['approvable_type' => $expense->getMorphClass(), 'approvable_id' => $expense->id, 'step' => 'request', 'decision' => 'submitted', 'decided_by' => $by->id]);
        Audit::record('finance.expense_submitted', $expense, 'Expense requested: '.$expense->title.' '.Money::format($expense->amount));

        return $expense;
    }

    /** Independent approval: the requester can never approve their own expense. */
    public function decideExpense(Expense $expense, bool $approve, User $by, ?string $reason = null): void
    {
        if ($expense->requested_by === $by->id) {
            throw new DomainRuleException(__('finance.no_self_approve'));
        }
        if (! $approve && trim((string) $reason) === '') {
            throw new DomainRuleException(__('finance.reason_required'));
        }
        DB::transaction(function () use ($expense, $approve, $by, $reason) {
            $e = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($e->status !== 'submitted') {
                throw new DomainRuleException(__('finance.expense_not_pending'));
            }
            $e->update($approve
                ? ['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now()]
                : ['status' => 'rejected', 'approved_by' => $by->id, 'approved_at' => now(), 'rejection_reason' => $reason]);
            Approval::create(['approvable_type' => $e->getMorphClass(), 'approvable_id' => $e->id, 'step' => 'approval', 'decision' => $approve ? 'approved' : 'rejected', 'reason' => $reason, 'decided_by' => $by->id]);
            Audit::record($approve ? 'finance.expense_approved' : 'finance.expense_rejected', $e, ($approve ? 'Approved' : 'Rejected').': '.$e->title, ['reason' => $reason]);
        });
    }

    /** Record that an approved expense was actually paid; this is when it hits the ledger. */
    public function payExpense(Expense $expense, LedgerAccount $from, string $paidOn, ?string $reference, User $by): LedgerTransaction
    {
        return DB::transaction(function () use ($expense, $from, $paidOn, $reference, $by) {
            $e = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($e->status !== 'approved') {
                throw new DomainRuleException(__('finance.expense_not_approved'));
            }
            $this->assertAvailable($from, $e->fund, $e->amount);
            $txn = $this->ledger->post(
                date: $paidOn,
                category: 'expense',
                description: $e->title,
                lines: [
                    ['account' => self::EXPENSE_ACCOUNT[$e->category] ?? '5400', 'fund' => $e->fund, 'debit' => $e->amount, 'memo' => $e->category],
                    ['account' => $from, 'fund' => $e->fund, 'credit' => $e->amount],
                ],
                by: $by,
                source: $e,
                reference: $reference,
                idempotencyKey: 'expense:'.$e->id,
                approvedBy: $e->approver,
            );
            $e->update(['status' => 'paid', 'paid_on' => $paidOn, 'paid_by' => $by->id, 'payment_reference' => $reference, 'paid_from_account_id' => $from->id, 'ledger_transaction_id' => $txn->id]);
            Audit::record('finance.expense_paid', $e, 'Expense paid: '.$e->title.' '.Money::format($e->amount));

            return $txn;
        });
    }

    // ------------------------------------------------------------------ transfers & balances

    /** Move money between accounts within one fund (e.g. bank -> UTT). */
    public function transfer(LedgerAccount $from, LedgerAccount $to, Fund $fund, int $amount, string $date, string $description, ?string $reference, User $by): LedgerTransaction
    {
        if ($from->is($to) || $amount <= 0) {
            throw new DomainRuleException(__('finance.invalid_transfer'));
        }
        $this->assertAvailable($from, $fund, $amount);
        $txn = $this->ledger->post($date, $to->subtype === 'investment' ? 'investment_contribution' : ($from->subtype === 'investment' ? 'investment_withdrawal' : 'transfer'), $description, [
            ['account' => $to, 'fund' => $fund, 'debit' => $amount],
            ['account' => $from, 'fund' => $fund, 'credit' => $amount],
        ], $by, null, $reference);
        Audit::record('finance.transfer', $txn, "Transfer {$from->code} -> {$to->code}: ".Money::format($amount));

        return $txn;
    }

    /** Re-designate money from one fund to another inside the same account (requires a recorded reason). */
    public function interfundTransfer(LedgerAccount $account, Fund $from, Fund $to, int $amount, string $date, string $reason, User $by): LedgerTransaction
    {
        if ($from->is($to) || $amount <= 0) {
            throw new DomainRuleException(__('finance.invalid_transfer'));
        }
        $this->assertAvailable($account, $from, $amount);

        return $this->ledger->post($date, 'interfund', $reason, [
            ['account' => '3100', 'fund' => $from, 'debit' => $amount],
            ['account' => $account, 'fund' => $from, 'credit' => $amount],
            ['account' => $account, 'fund' => $to, 'debit' => $amount],
            ['account' => '3100', 'fund' => $to, 'credit' => $amount],
        ], $by);
    }

    /** Cut-over balance carried over from the spreadsheet era. */
    public function openingBalance(LedgerAccount $account, Fund $fund, int $amount, string $date, string $note, User $by): LedgerTransaction
    {
        if ($amount <= 0) {
            throw new DomainRuleException(__('finance.amount_positive'));
        }
        $txn = $this->ledger->post($date, 'opening_balance', 'Opening balance: '.$note, [
            ['account' => $account, 'fund' => $fund, 'debit' => $amount],
            ['account' => '3000', 'fund' => $fund, 'credit' => $amount],
        ], $by, null, null, 'opening:'.$account->id.':'.$fund->id.':'.$date);
        Audit::record('finance.opening_balance', $txn, "Opening balance {$account->code}/{$fund->key}: ".Money::format($amount), ['note' => $note]);

        return $txn;
    }

    public function investmentReturn(LedgerAccount $investment, Fund $fund, int $amount, string $date, string $note, User $by): LedgerTransaction
    {
        if ($investment->subtype !== 'investment' || $amount <= 0) {
            throw new DomainRuleException(__('finance.invalid_transfer'));
        }

        return $this->ledger->post($date, 'investment_return', $note, [
            ['account' => $investment, 'fund' => $fund, 'debit' => $amount],
            ['account' => '4500', 'fund' => $fund, 'credit' => $amount],
        ], $by);
    }

    public function recordValuation(LedgerAccount $investment, int $value, string $date, ?string $source, ?string $notes, User $by): InvestmentValuation
    {
        $v = InvestmentValuation::create(['ledger_account_id' => $investment->id, 'valued_on' => $date, 'value' => $value, 'source' => $source, 'notes' => $notes, 'recorded_by' => $by->id]);
        Audit::record('finance.valuation', $v, 'Investment valuation recorded: '.Money::format($value));

        return $v;
    }

    private function assertAvailable(LedgerAccount $account, Fund $fund, int $amount): void
    {
        $available = $this->ledger->accountBalance($account, $fund->id);
        if ($available < $amount) {
            throw new DomainRuleException(__('finance.insufficient', ['fund' => $fund->label(), 'available' => Money::format($available)]));
        }
    }
}
