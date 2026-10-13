<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Treasury. Double-entry ledger with a fund dimension: every transaction must
 * balance overall AND within each fund, so "balance by fund" is always derivable.
 * Amounts are whole Tanzanian shillings (BIGINT). Posted rows are never updated;
 * corrections are reversing transactions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funds', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name_sw');
            $table->string('name_en');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('target_amount')->nullable();
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name');
            // asset | liability | equity | income | expense
            $table->string('type', 16);
            // bank | mobile_money | cash | investment | member_credit | ...
            $table->string('subtype', 24)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('accepts_deposits')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->date('txn_date')->index();
            $table->string('category', 32)->index();
            $table->string('description');
            $table->string('reference')->nullable();
            // posted | reversed
            $table->string('status', 16)->default('posted');
            $table->nullableMorphs('source');
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->foreignId('reverses_transaction_id')->nullable()->unique()->constrained('ledger_transactions');
            $table->text('reversal_reason')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained();
            $table->foreignId('fund_id')->constrained();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);
            $table->string('memo')->nullable();
            $table->index(['ledger_account_id', 'fund_id']);
        });

        Schema::create('contribution_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('membership_category', 32)->default('standard');
            $table->unsignedBigInteger('amount');
            // [{"fund":"general","amount":10000},{"fund":"welfare","amount":10000}]
            $table->json('fund_splits');
            $table->unsignedTinyInteger('due_day')->default(28);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            // draft | proposed | approved | retired
            $table->string('status', 16)->default('draft');
            $table->text('source_reference')->nullable();
            $table->foreignId('decision_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // Anything a member owes: monthly contribution, welfare levy, penalty, entry fee.
        Schema::create('obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            // monthly_contribution | entry_fee | welfare_levy | penalty | other
            $table->string('kind', 24)->index();
            $table->char('period', 7)->nullable()->index();
            $table->string('description');
            $table->unsignedBigInteger('amount');
            $table->json('fund_splits');
            $table->date('due_date')->index();
            $table->foreignId('contribution_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('welfare_case_id')->nullable()->index();
            $table->string('penalty_rule_key', 64)->nullable();
            // open | partially_paid | paid | waived | cancelled
            $table->string('status', 16)->default('open')->index();
            $table->text('status_reason')->nullable();
            // Guarantees idempotent generation, e.g. "monthly:2026-05" / "welfare:7"
            $table->string('uniqueness_key', 80);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_historical')->default(false);
            $table->timestamps();
            $table->unique(['member_id', 'uniqueness_key']);
        });

        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('paid_on');
            // bank_transfer | mobile_money | cash | other
            $table->string('method', 16);
            $table->string('channel_detail', 64)->nullable();
            $table->string('transaction_reference', 100)->nullable();
            $table->string('reference_normalized', 100)->nullable()->index();
            // contributions | sadaka | donation | welfare | event
            $table->string('purpose', 16)->default('contributions');
            $table->unsignedBigInteger('purpose_target_id')->nullable();
            $table->text('note')->nullable();
            $table->string('receipt_path')->nullable();
            // pending | verified | rejected | withdrawn
            $table->string('status', 16)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('verified_payment_id')->nullable();
            $table->timestamps();
        });

        Schema::create('verified_payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_submission_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('received_on')->index();
            $table->string('method', 16);
            $table->foreignId('deposit_account_id')->constrained('ledger_accounts');
            $table->string('transaction_reference', 100)->nullable();
            // Unique: the same bank/M-Pesa reference can never be verified twice.
            $table->string('reference_normalized', 100)->nullable()->unique();
            $table->string('purpose', 16)->default('contributions');
            $table->unsignedBigInteger('purpose_target_id')->nullable();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained();
            // active | reversed
            $table->string('status', 16)->default('active');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_historical')->default(false);
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verified_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('obligation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained();
            // active | reversed
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->index(['obligation_id', 'status']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('title');
            $table->string('category', 32);
            $table->unsignedBigInteger('amount');
            $table->date('expense_date');
            $table->text('purpose');
            $table->foreignId('fund_id')->constrained();
            $table->foreignId('paid_from_account_id')->nullable()->constrained('ledger_accounts');
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->unsignedBigInteger('welfare_case_id')->nullable()->index();
            $table->unsignedBigInteger('charity_activity_id')->nullable()->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            // submitted | approved | rejected | paid | cancelled
            $table->string('status', 16)->default('submitted')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->date('paid_on')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained();
            $table->string('document_path')->nullable();
            $table->timestamps();
        });

        // Market valuations are NOT cash and never enter the ledger.
        Schema::create('investment_valuations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_account_id')->constrained();
            $table->date('valued_on');
            $table->unsignedBigInteger('value');
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['investment_valuations', 'expenses', 'payment_allocations', 'verified_payments', 'payment_submissions',
            'obligations', 'contribution_rules', 'ledger_entries', 'ledger_transactions', 'ledger_accounts', 'funds'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
