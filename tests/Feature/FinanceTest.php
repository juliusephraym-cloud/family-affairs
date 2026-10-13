<?php

namespace Tests\Feature;

use App\Exceptions\DomainRuleException;
use App\Models\Fund;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\Obligation;
use App\Models\PaymentSubmission;
use App\Models\VerifiedPayment;
use App\Services\ContributionService;
use App\Services\LedgerService;
use App\Services\ReportService;
use App\Services\TreasuryService;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    private ContributionService $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = app(ContributionService::class);
        $this->travelTo('2026-10-13 10:00:00');
    }

    public function test_no_obligations_without_an_approved_rule(): void
    {
        $this->makeUser();
        $r = $this->c->generateMonthly('2026-09');
        $this->assertSame(0, $r['created']);
        $this->assertSame(0, Obligation::count());
    }

    public function test_monthly_generation_is_idempotent_and_respects_join_date(): void
    {
        $this->approvedRule();
        $a = $this->makeUser();
        $b = $this->makeUser(memberAttrs: ['joined_on' => '2026-10-05']);
        $this->c->generateMonthly('2026-09');
        $this->c->generateMonthly('2026-09');
        $this->assertSame(1, Obligation::where('period', '2026-09')->count(), 'Late joiner owes nothing for September; re-running must not duplicate');
        $this->c->generateMonthly('2026-10');
        $this->assertSame(2, Obligation::where('period', '2026-10')->count());
        $this->assertSame(20000, (int) Obligation::where('member_id', $a->member_id)->value('amount'));
    }

    public function test_payment_pays_oldest_first_splits_funds_and_keeps_credit(): void
    {
        $this->approvedRule(10000, 10000);
        $treasurer = $this->makeUser(['treasurer']);
        $m = $this->makeUser();
        foreach (['2026-08', '2026-09', '2026-10'] as $p) {
            $this->c->generateMonthly($p);
        }
        $p = $this->c->recordPayment($m->member, 65000, '2026-10-10', 'bank_transfer', LedgerAccount::byCode('1000'), 'REF-1', 'contributions', null, $treasurer);

        $obs = Obligation::where('member_id', $m->member_id)->orderBy('due_date')->get();
        $this->assertSame(['paid', 'paid', 'paid'], $obs->pluck('status')->all());
        $this->assertSame(5000, $this->c->summary($m->member)['credit']);

        $balances = app(LedgerService::class)->fundBalances()->keyBy(fn ($r) => $r['fund']->key);
        $this->assertSame(35000, $balances['general']['cash'], '30,000 general + 5,000 unallocated credit held in general');
        $this->assertSame(30000, $balances['welfare']['cash']);
        $this->assertLedgerBalanced();

        // Credit is applied automatically when the next obligation appears.
        $this->c->generateMonthly('2026-11');
        $nov = Obligation::where('member_id', $m->member_id)->where('period', '2026-11')->first();
        $this->assertSame('partially_paid', $nov->status);
        $this->assertSame(0, $this->c->summary($m->member)['credit']);
        $this->assertLedgerBalanced();
    }

    public function test_confirmed_penalties_first_rule_changes_allocation_order(): void
    {
        $this->approvedRule();
        $treasurer = $this->makeUser(['treasurer']);
        $m = $this->makeUser();
        $this->c->generateMonthly('2026-09');
        $this->c->addObligation($m->member, 'penalty', 5000, 'Late fine', '2026-10-12', [['fund' => 'general', 'amount' => 5000]], 'penalty:x', $treasurer);

        $this->confirmRule('payments.penalties_first', true);
        $this->c->recordPayment($m->member, 5000, '2026-10-13', 'cash', LedgerAccount::byCode('1020'), null, 'contributions', null, $treasurer);
        $this->assertSame('paid', Obligation::where('kind', 'penalty')->value('status'));
        $this->assertSame('open', Obligation::where('kind', 'monthly_contribution')->where('member_id', $m->member_id)->value('status'));
    }

    public function test_duplicate_transaction_reference_is_refused(): void
    {
        $treasurer = $this->makeUser(['treasurer']);
        $m = $this->makeUser();
        $this->c->recordPayment($m->member, 20000, '2026-10-01', 'bank_transfer', LedgerAccount::byCode('1000'), 'ab-12 34', 'contributions', null, $treasurer);
        $this->expectException(DomainRuleException::class);
        $this->c->recordPayment($m->member, 20000, '2026-10-01', 'bank_transfer', LedgerAccount::byCode('1000'), 'AB1234', 'contributions', null, $treasurer);
    }

    public function test_member_submission_has_no_effect_until_verified_and_no_self_verification(): void
    {
        $this->approvedRule();
        $treasurer = $this->makeUser(['treasurer']);
        $m = $this->makeUser();
        $this->c->generateMonthly('2026-09');

        $this->actingAs($m)->post('/finance/pay', ['amount' => '20,000', 'paid_on' => '2026-10-01', 'method' => 'mobile_money', 'transaction_reference' => 'MP123', 'purpose' => 'contributions'])->assertRedirect();
        $mine = fn () => Obligation::where('member_id', $m->member_id)->value('status');
        $this->assertSame('open', $mine(), 'A submission alone never marks anything paid');
        $this->assertSame(0, LedgerTransaction::count());

        $sub = PaymentSubmission::first();
        // Treasurer cannot verify their own payment.
        $own = PaymentSubmission::create(['member_id' => $treasurer->member_id, 'submitted_by' => $treasurer->id, 'amount' => 20000, 'paid_on' => '2026-10-01', 'method' => 'cash', 'purpose' => 'contributions', 'status' => 'pending']);
        $this->actingAs($treasurer)->post(route('treasury.submissions.verify', $own), ['deposit_account_id' => LedgerAccount::byCode('1000')->id])->assertSessionHas('error');
        $this->assertSame('pending', $own->fresh()->status);

        $this->actingAs($treasurer)->post(route('treasury.submissions.verify', $sub), ['deposit_account_id' => LedgerAccount::byCode('1000')->id])->assertSessionHas('status');
        $this->assertSame('verified', $sub->fresh()->status);
        $this->assertSame('paid', $mine());
        // Double-verify is refused.
        $this->actingAs($treasurer)->post(route('treasury.submissions.verify', $sub), ['deposit_account_id' => LedgerAccount::byCode('1000')->id])->assertSessionHas('error');
        $this->assertSame(1, VerifiedPayment::count());
    }

    public function test_reversal_preserves_history_and_reopens_obligations(): void
    {
        $this->approvedRule();
        $treasurer = $this->makeUser(['treasurer']);
        $m = $this->makeUser();
        $this->c->generateMonthly('2026-10');
        $p = $this->c->recordPayment($m->member, 20000, '2026-10-02', 'bank_transfer', LedgerAccount::byCode('1000'), 'R1', 'contributions', null, $treasurer);
        $txnCount = LedgerTransaction::count();

        $this->c->reversePayment($p, 'Entered against the wrong member', $treasurer);
        $mine = fn () => Obligation::where('member_id', $m->member_id)->value('status');

        $this->assertSame('reversed', $p->fresh()->status);
        $this->assertSame('open', $mine());
        $this->assertSame($txnCount * 2, LedgerTransaction::count(), 'Original kept + reversal posted');
        $this->assertSame('reversed', LedgerTransaction::whereNull('reverses_transaction_id')->value('status'));
        $this->assertSame(0, app(LedgerService::class)->fundBalances()->sum('total'));
        $this->assertLedgerBalanced();
        $this->assertDatabaseHas('audit_logs', ['action' => 'finance.payment_reversed']);
        // The freed reference can now be used for the corrected entry.
        $this->c->recordPayment($m->member, 20000, '2026-10-02', 'bank_transfer', LedgerAccount::byCode('1000'), 'R1', 'contributions', null, $treasurer);
        $this->assertSame('paid', $mine());
    }

    public function test_posted_transactions_are_immutable(): void
    {
        $treasurer = $this->makeUser(['treasurer']);
        $txn = app(TreasuryService::class)->openingBalance(LedgerAccount::byCode('1000'), Fund::byKey('general'), 1000, '2026-10-01', 'test', $treasurer);
        $this->expectException(\LogicException::class);
        $txn->update(['description' => 'tampered']);
    }

    public function test_ledger_rejects_unbalanced_and_cross_fund_lines(): void
    {
        $this->expectException(DomainRuleException::class);
        app(LedgerService::class)->post('2026-10-01', 'test', 'bad', [
            ['account' => '1000', 'fund' => 'general', 'debit' => 1000],
            ['account' => '4000', 'fund' => 'welfare', 'credit' => 1000],
        ]);
    }

    public function test_expense_needs_independent_approval_and_sufficient_funds(): void
    {
        $t = app(TreasuryService::class);
        $requester = $this->makeUser(['chairperson']);
        $other = $this->makeUser(['vice_chairperson']);
        $treasurer = $this->makeUser(['treasurer']);
        $e = $t->submitExpense(['title' => 'Cake', 'category' => 'event', 'amount' => 150000, 'expense_date' => '2026-10-01', 'purpose' => 'x', 'fund_id' => Fund::byKey('general')->id], $requester);

        try {
            $t->decideExpense($e, true, $requester);
            $this->fail('Self-approval must be refused');
        } catch (DomainRuleException) {
        }
        $t->decideExpense($e, true, $other);

        try {
            $t->payExpense($e->fresh(), LedgerAccount::byCode('1000'), '2026-10-02', 'X', $treasurer);
            $this->fail('Paying more than the fund holds must be refused');
        } catch (DomainRuleException) {
        }
        $t->openingBalance(LedgerAccount::byCode('1000'), Fund::byKey('general'), 200000, '2026-09-30', 'cut-over', $treasurer);
        $t->payExpense($e->fresh(), LedgerAccount::byCode('1000'), '2026-10-02', 'X', $treasurer);
        $this->assertSame('paid', $e->fresh()->status);
        $this->assertSame(50000, app(LedgerService::class)->accountBalance(LedgerAccount::byCode('1000'), Fund::byKey('general')->id));
        $this->assertLedgerBalanced();
    }

    public function test_investment_transfer_and_valuation_are_kept_separate(): void
    {
        $t = app(TreasuryService::class);
        $treasurer = $this->makeUser(['treasurer']);
        $t->openingBalance(LedgerAccount::byCode('1000'), Fund::byKey('investment'), 3000000, '2026-09-30', 'cut-over', $treasurer);
        $t->transfer(LedgerAccount::byCode('1000'), LedgerAccount::byCode('1100'), Fund::byKey('investment'), 2000000, '2026-10-01', 'To UTT', null, $treasurer);
        $t->recordValuation(LedgerAccount::byCode('1100'), 2150000, '2026-10-10', 'UTT', null, $treasurer);

        $inv = app(LedgerService::class)->fundBalances()->firstWhere(fn ($r) => $r['fund']->key === 'investment');
        $this->assertSame(1000000, $inv['cash']);
        $this->assertSame(2000000, $inv['invested'], 'Valuation must not change the cost recorded in the ledger');
    }

    public function test_penalties_cannot_be_applied_until_amount_is_confirmed(): void
    {
        $this->approvedRule();
        $treasurer = $this->makeUser(['treasurer']);
        $m = $this->makeUser();
        $this->c->generateMonthly('2026-09');

        $this->actingAs($treasurer)->post(route('treasury.penalties.apply'), ['period' => '2026-09', 'members' => [$m->member_id]])->assertSessionHas('error');
        $this->assertSame(0, Obligation::where('kind', 'penalty')->count());

        $this->confirmRule('penalty.late_monthly_amount', 5000);
        $this->actingAs($treasurer)->post(route('treasury.penalties.apply'), ['period' => '2026-09', 'members' => [$m->member_id]]);
        $this->actingAs($treasurer)->post(route('treasury.penalties.apply'), ['period' => '2026-09', 'members' => [$m->member_id]]);
        $this->assertSame(1, Obligation::where('kind', 'penalty')->count(), 'Penalties are idempotent per period');
    }

    public function test_reports_render_and_csv_neutralises_formulas(): void
    {
        $treasurer = $this->makeUser(['treasurer']);
        $m = $this->makeUser(memberAttrs: ['full_name' => '=HYPERLINK("http://evil")']);
        $this->approvedRule();
        $this->c->generateMonthly('2026-09');
        foreach (array_keys(ReportService::REPORTS) as $r) {
            $this->actingAs($treasurer)->get(route('treasury.reports', ['report' => $r]))->assertOk();
        }
        $csv = $this->actingAs($treasurer)->get(route('treasury.reports.download', ['report' => 'outstanding', 'format' => 'csv']))->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->actingAs($treasurer)->get(route('treasury.reports.download', ['report' => 'statement', 'format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
