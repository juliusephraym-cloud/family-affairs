<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Fund;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of ledger_transactions / ledger_entries.
 *
 * Invariants enforced here:
 *  1. Every line is either a debit or a credit, positive, whole shillings.
 *  2. Σdebits = Σcredits for the transaction AND for each fund inside it.
 *  3. Posted transactions are immutable; correction = reverse() which posts
 *     the mirror image and links both ways.
 *  4. An idempotency key makes re-posting the same business event a no-op.
 */
class LedgerService
{
    /**
     * @param  array<int, array{account: string|LedgerAccount, fund: string|Fund, debit?: int, credit?: int, member_id?: ?int, memo?: ?string}>  $lines
     */
    public function post(
        Carbon|string $date,
        string $category,
        string $description,
        array $lines,
        ?User $by = null,
        ?Model $source = null,
        ?string $reference = null,
        ?string $idempotencyKey = null,
        ?User $approvedBy = null,
    ): LedgerTransaction {
        if ($idempotencyKey && ($existing = LedgerTransaction::where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }

        $resolved = $this->resolveLines($lines);

        return DB::transaction(function () use ($date, $category, $description, $resolved, $by, $source, $reference, $idempotencyKey, $approvedBy) {
            $txn = LedgerTransaction::create([
                'txn_date' => $date,
                'category' => $category,
                'description' => mb_substr($description, 0, 255),
                'reference' => $reference,
                'status' => 'posted',
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'idempotency_key' => $idempotencyKey,
                'posted_by' => $by?->id,
                'approved_by' => $approvedBy?->id,
            ]);
            $txn->entries()->createMany($resolved->all());

            return $txn->load('entries');
        });
    }

    public function reverse(LedgerTransaction $txn, string $reason, ?User $by, ?string $date = null): LedgerTransaction
    {
        if (trim($reason) === '') {
            throw new DomainRuleException(__('finance.reason_required'));
        }

        return DB::transaction(function () use ($txn, $reason, $by, $date) {
            $locked = LedgerTransaction::whereKey($txn->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'posted' || $locked->reverses_transaction_id) {
                throw new DomainRuleException(__('finance.already_reversed'));
            }

            $reversal = LedgerTransaction::create([
                'txn_date' => $date ?? now()->toDateString(),
                'category' => $locked->category,
                'description' => mb_substr('Reversal: '.$locked->description, 0, 255),
                'reference' => $locked->reference,
                'status' => 'posted',
                'source_type' => $locked->source_type,
                'source_id' => $locked->source_id,
                'reverses_transaction_id' => $locked->id,
                'reversal_reason' => $reason,
                'posted_by' => $by?->id,
            ]);
            foreach ($locked->entries as $e) {
                $reversal->entries()->create([
                    'ledger_account_id' => $e->ledger_account_id,
                    'fund_id' => $e->fund_id,
                    'member_id' => $e->member_id,
                    'debit' => $e->credit,
                    'credit' => $e->debit,
                    'memo' => 'Reversal',
                ]);
            }
            $locked->status = 'reversed';
            $locked->save();

            Audit::record('ledger.reversed', $locked, 'Ledger transaction reversed', ['reason' => $reason, 'reversal' => $reversal->public_id]);

            return $reversal;
        });
    }

    /** Signed balance for an account (natural sign: assets/expenses debit-positive). */
    public function accountBalance(LedgerAccount $account, ?int $fundId = null, ?string $upTo = null): int
    {
        $q = DB::table('ledger_entries')
            ->join('ledger_transactions', 'ledger_transactions.id', '=', 'ledger_entries.ledger_transaction_id')
            ->where('ledger_entries.ledger_account_id', $account->id)
            ->when($fundId, fn ($q) => $q->where('ledger_entries.fund_id', $fundId))
            ->when($upTo, fn ($q) => $q->where('ledger_transactions.txn_date', '<=', $upTo));
        $row = $q->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')->first();

        return $account->isDebitNormal() ? (int) $row->d - (int) $row->c : (int) $row->c - (int) $row->d;
    }

    /**
     * Money held per fund across all asset accounts, split into cash-like
     * (bank, mobile money, cash) and investments at cost. Valuations are
     * reported separately and never mixed in here.
     *
     * @return Collection<int, array{fund: Fund, cash: int, invested: int, total: int}>
     */
    public function fundBalances(?string $upTo = null): Collection
    {
        $rows = DB::table('ledger_entries')
            ->join('ledger_transactions', 'ledger_transactions.id', '=', 'ledger_entries.ledger_transaction_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.ledger_account_id')
            ->where('ledger_accounts.type', 'asset')
            ->when($upTo, fn ($q) => $q->where('ledger_transactions.txn_date', '<=', $upTo))
            ->groupBy('ledger_entries.fund_id', 'ledger_accounts.subtype')
            ->selectRaw('ledger_entries.fund_id, ledger_accounts.subtype, SUM(debit) - SUM(credit) AS bal')
            ->get();

        return Fund::orderBy('sort')->get()->map(function (Fund $fund) use ($rows) {
            $mine = $rows->where('fund_id', $fund->id);
            $invested = (int) $mine->where('subtype', 'investment')->sum('bal');
            $cash = (int) $mine->where('subtype', '!=', 'investment')->sum('bal');

            return ['fund' => $fund, 'cash' => $cash, 'invested' => $invested, 'total' => $cash + $invested];
        });
    }

    private function resolveLines(array $lines): Collection
    {
        if (count($lines) < 2) {
            throw new DomainRuleException('A ledger transaction needs at least two lines.');
        }
        $accounts = [];
        $funds = [];
        $resolved = collect($lines)->map(function (array $l) use (&$accounts, &$funds) {
            $debit = (int) ($l['debit'] ?? 0);
            $credit = (int) ($l['credit'] ?? 0);
            if ($debit < 0 || $credit < 0 || ($debit > 0) === ($credit > 0)) {
                throw new DomainRuleException('Each ledger line must be exactly one positive debit or credit.');
            }
            $account = $l['account'] instanceof LedgerAccount ? $l['account']
                : ($accounts[$l['account']] ??= LedgerAccount::byCode($l['account']));
            $fund = $l['fund'] instanceof Fund ? $l['fund'] : ($funds[$l['fund']] ??= Fund::byKey($l['fund']));

            return [
                'ledger_account_id' => $account->id,
                'fund_id' => $fund->id,
                'member_id' => $l['member_id'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
                'memo' => isset($l['memo']) ? mb_substr($l['memo'], 0, 255) : null,
            ];
        });

        foreach ($resolved->groupBy('fund_id') as $fundId => $fundLines) {
            if ($fundLines->sum('debit') !== $fundLines->sum('credit')) {
                throw new DomainRuleException("Ledger lines do not balance within fund #{$fundId}.");
            }
        }

        return $resolved;
    }
}
