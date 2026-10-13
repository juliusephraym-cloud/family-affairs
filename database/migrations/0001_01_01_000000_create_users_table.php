<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity, membership and access control.
 *
 * A "member" is a person the family records (they may never log in, e.g. a
 * child). A "user" is a login account and always belongs to exactly one
 * member. Accounts are passwordless: phone + WhatsApp OTP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('full_name');
            $table->string('preferred_name')->nullable();
            $table->string('phone_e164', 20)->nullable()->unique();
            $table->string('email')->nullable();
            $table->date('date_of_birth')->nullable();
            // private | leaders | members — only day+month is ever shown to members
            $table->string('birthday_visibility', 16)->default('members');
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->string('relationship_note')->nullable();
            $table->boolean('is_minor')->default(false);
            // invited | active | suspended | resigned | archived | non_member
            $table->string('membership_status', 16)->default('non_member')->index();
            $table->string('membership_category', 32)->default('standard');
            $table->date('joined_on')->nullable();
            $table->text('bio')->nullable();
            $table->string('interests')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('photo_path')->nullable();
            $table->text('leader_notes')->nullable();
            $table->timestamp('whatsapp_opt_in_at')->nullable();
            $table->timestamp('whatsapp_opt_out_at')->nullable();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phone_e164', 20)->unique();
            $table->timestamp('phone_verified_at')->nullable();
            // active | locked | disabled
            $table->string('status', 16)->default('active');
            $table->string('locale', 5)->default('sw');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('family_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_member_id')->constrained('members')->cascadeOnDelete();
            // parent_of | spouse_of | sibling_of | guardian_of | other
            $table->string('type', 16);
            $table->string('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['member_id', 'related_member_id', 'type']);
        });

        Schema::create('membership_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->text('reason');
            $table->unsignedBigInteger('decision_id')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('name_sw');
            $table->string('name_en');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('group', 32);
            $table->string('description');
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->date('expires_on')->nullable();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('phone_e164', 20);
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('phone_e164', 20)->index();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('invitation_id')->nullable()->constrained()->cascadeOnDelete();
            // login | register | phone_change
            $table->string('purpose', 16);
            $table->string('new_phone_e164', 20)->nullable();
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            // queued | submitted | failed — never "delivered" unless the provider says so
            $table->string('delivery_status', 16)->default('queued');
            $table->string('provider_message_id', 40)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64)->index();
            $table->nullableMorphs('subject');
            $table->string('description');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->json('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
        });

        // Family rules (constitution-derived or meeting-derived values). Nothing
        // here is "in force" until status = confirmed by an authorised leader.
        Schema::create('policy_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('title_sw');
            $table->string('title_en');
            $table->json('value')->nullable();
            $table->string('value_type', 16)->default('money');
            // source_unconfirmed | proposed | confirmed | retired
            $table->string('status', 24)->default('source_unconfirmed');
            $table->text('source_reference')->nullable();
            $table->date('effective_from')->nullable();
            $table->unsignedBigInteger('decision_id')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('policy_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_rule_id')->constrained()->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->string('status', 24);
            $table->text('reason');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source', 24);
            $table->string('filename');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('review');
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('import_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('raw_name');
            $table->string('normalized_name')->index();
            $table->foreignId('proposed_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->json('data')->nullable();
            // pending | linked | created | rejected
            $table->string('status', 16)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['import_candidates', 'import_batches', 'policy_rule_versions', 'policy_rules', 'system_settings',
            'audit_logs', 'sessions', 'otp_challenges', 'invitations', 'role_user', 'permission_role', 'permissions',
            'roles', 'membership_status_histories', 'family_relationships', 'users', 'members', 'households'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
