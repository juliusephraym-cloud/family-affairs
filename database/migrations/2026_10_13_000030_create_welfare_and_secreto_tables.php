<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('welfare_cases', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            // bereavement | illness | hardship | celebration | other
            $table->string('type', 16);
            $table->string('title');
            $table->text('summary');
            // Encrypted at rest; shown only to holders of welfare.view_private
            $table->text('private_details')->nullable();
            $table->foreignId('beneficiary_member_id')->nullable()->constrained('members')->nullOnDelete();
            // parent | child | spouse | other (bereavement eligibility)
            $table->string('relationship', 16)->nullable();
            // members | leaders | private
            $table->string('visibility', 16)->default('members');
            // submitted | approved | collecting | disbursed | closed | rejected
            $table->string('status', 16)->default('submitted')->index();
            $table->json('policy_snapshot')->nullable();
            $table->unsignedBigInteger('fund_payout_amount')->default(0);
            $table->unsignedBigInteger('per_member_amount')->default(0);
            $table->date('contribution_deadline')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('closing_summary')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('welfare_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('welfare_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('paid_on');
            $table->string('recipient_name');
            $table->string('reference', 100)->nullable();
            $table->foreignId('from_account_id')->constrained('ledger_accounts');
            $table->foreignId('fund_id')->constrained();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('charity_activities', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('beneficiary')->nullable();
            $table->dateTime('planned_for')->nullable();
            // proposed | approved | done | cancelled
            $table->string('status', 16)->default('proposed');
            $table->unsignedBigInteger('budget')->default(0);
            $table->foreignId('coordinator_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->text('logistics')->nullable();
            $table->text('report')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('charity_volunteers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charity_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->boolean('attended')->default(false);
            $table->timestamps();
            $table->unique(['charity_activity_id', 'member_id']);
        });

        Schema::create('secreto_seasons', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('title');
            $table->unsignedBigInteger('gift_minimum')->nullable();
            $table->boolean('allow_vouchers')->default(true);
            $table->text('rules')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->dateTime('exchange_at')->nullable();
            $table->string('venue')->nullable();
            $table->boolean('avoid_same_household')->default(true);
            // draft | registration | drawn | exchanged | archived
            $table->string('status', 16)->default('draft');
            $table->foreignId('drawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('drawn_at')->nullable();
            $table->text('memories')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('secreto_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('secreto_season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->text('wishlist')->nullable();
            // not_started | preparing | ready | brought | by_proxy
            $table->string('gift_status', 16)->default('not_started');
            $table->string('proxy_name')->nullable();
            $table->timestamps();
            $table->unique(['secreto_season_id', 'member_id']);
        });

        // The recipient is stored only inside an encrypted payload; there is no
        // plaintext recipient column to query, join or leak.
        Schema::create('secreto_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('secreto_season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('giver_participant_id')->unique()->constrained('secreto_participants')->cascadeOnDelete();
            $table->text('recipient_payload');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['secreto_assignments', 'secreto_participants', 'secreto_seasons', 'charity_volunteers',
            'charity_activities', 'welfare_disbursements', 'welfare_cases'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
