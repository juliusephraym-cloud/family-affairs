<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Meetings, minutes, decisions, action items, constitution and the arrears review process. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('title');
            // general | online | leadership | special
            $table->string('type', 16)->default('general');
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('online_method')->nullable();
            $table->foreignId('organizer_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->boolean('attendance_required')->default(true);
            // planned | held | cancelled
            $table->string('status', 16)->default('planned');
            $table->text('notes')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('meeting_agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('discussion')->nullable();
            $table->timestamps();
        });

        Schema::create('meeting_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            // present | apology | absent | excused
            $table->string('status', 16);
            $table->string('role_at_meeting')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['meeting_id', 'member_id']);
        });

        Schema::create('meeting_minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->unique()->constrained()->cascadeOnDelete();
            // draft | submitted | approved | archived
            $table->string('status', 16)->default('draft');
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->string('next_meeting_note')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agenda_item_id')->nullable()->constrained('meeting_agenda_items')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            // resolution | amendment | financial | membership | other
            $table->string('type', 16)->default('resolution');
            // meeting | chat_proposal | leadership | imported
            $table->string('source', 16)->default('meeting');
            $table->unsignedSmallInteger('votes_for')->nullable();
            $table->unsignedSmallInteger('votes_against')->nullable();
            $table->unsignedSmallInteger('votes_abstain')->nullable();
            // recorded | needs_confirmation | confirmed | superseded
            $table->string('status', 24)->default('recorded');
            $table->date('effective_from')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('decision_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->foreignId('assignee_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->date('due_on')->nullable();
            // open | in_progress | done | cancelled
            $table->string('status', 16)->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('constitution_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version_label', 32);
            $table->string('title');
            $table->longText('body');
            // draft | approved | superseded
            $table->string('status', 16)->default('draft');
            $table->date('effective_from')->nullable();
            $table->foreignId('decision_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('change_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('constitution_amendments', function (Blueprint $table) {
            $table->id();
            $table->string('clause_ref', 32);
            $table->text('current_text')->nullable();
            $table->text('proposed_text');
            $table->text('rationale')->nullable();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            // proposed | under_review | approved | rejected | withdrawn
            $table->string('status', 16)->default('proposed');
            $table->foreignId('decision_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });

        // Warning -> reminder -> review -> recorded decision -> optional status change.
        Schema::create('compliance_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            // warning | reminder | review | decided | closed
            $table->string('stage', 16)->default('warning');
            $table->unsignedBigInteger('arrears_snapshot')->default(0);
            $table->unsignedSmallInteger('months_in_arrears')->default(0);
            $table->text('notes')->nullable();
            // no_action | payment_plan | status_change
            $table->string('outcome', 16)->nullable();
            $table->string('outcome_status', 16)->nullable();
            $table->text('decision_text')->nullable();
            $table->foreignId('decision_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->string('step', 32)->default('approval');
            // approved | rejected | submitted | returned
            $table->string('decision', 16);
            $table->text('reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->nullableMorphs('attachable');
            $table->string('label')->nullable();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['documents', 'approvals', 'compliance_cases', 'constitution_amendments', 'constitution_versions',
            'action_items', 'decisions', 'meeting_minutes', 'meeting_attendances', 'meeting_agenda_items', 'meetings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
