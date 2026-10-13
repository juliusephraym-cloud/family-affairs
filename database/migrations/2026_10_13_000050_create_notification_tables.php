<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp notification engine. A notification_job is one send to an audience
 * (a reminder, an announcement); each recipient gets a notification_delivery
 * row whose (job, member) uniqueness makes dispatch idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('category', 32)->index();
            $table->string('name');
            // Exact name of the Meta-approved template in the NextSMS dashboard
            $table->string('provider_template')->nullable();
            $table->text('body_preview');
            // Ordered variable names the provider template expects, e.g. ["name","period","amount"]
            $table->json('variables');
            // Personal finance data: never sent to a group audience
            $table->boolean('is_sensitive')->default(false);
            // pending | approved | rejected (as reported in the provider dashboard)
            $table->string('provider_approval', 16)->default('pending');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['member_id', 'category']);
        });

        Schema::create('notification_jobs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('notification_template_id')->constrained();
            $table->string('category', 32)->index();
            $table->string('title');
            $table->json('audience');
            $table->json('static_variables')->nullable();
            $table->dateTime('scheduled_at')->index();
            // draft | pending_approval | scheduled | processing | completed | cancelled | failed
            $table->string('status', 24)->default('draft')->index();
            $table->boolean('requires_approval')->default(false);
            $table->unsignedInteger('estimated_recipients')->default(0);
            $table->unsignedBigInteger('estimated_cost')->default(0);
            $table->string('idempotency_key', 100)->unique();
            $table->nullableMorphs('source');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('phone_e164', 20);
            $table->text('variables')->nullable(); // encrypted: may hold a private balance
            // queued | sending | submitted | delivered | read | failed | undeliverable | skipped
            $table->string('status', 16)->default('queued')->index();
            $table->string('skip_reason', 64)->nullable();
            $table->string('provider_message_id', 40)->nullable()->index();
            $table->unsignedSmallInteger('provider_status_id')->nullable();
            $table->string('provider_status_name', 64)->nullable();
            $table->json('provider_response')->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['notification_job_id', 'member_id']);
        });

        Schema::create('provider_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 16);
            $table->json('payload');
            $table->boolean('is_valid');
            $table->string('result', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('category', 32);
            $table->foreignId('notification_template_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('enabled')->default(false);
            $table->json('config');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        // Local/testing only: what the fake provider "sent". Never used in production.
        Schema::create('dev_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('to', 20);
            $table->string('template')->nullable();
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['dev_outbox', 'reminder_rules', 'provider_webhook_events', 'notification_deliveries',
            'notification_jobs', 'notification_preferences', 'notification_templates'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
