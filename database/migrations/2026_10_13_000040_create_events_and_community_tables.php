<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            // gathering | birthday | anniversary | milestone | celebration | easter | bereavement | charity | other
            $table->string('type', 16);
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('venue')->nullable();
            $table->string('online_link')->nullable();
            $table->foreignId('organizer_member_id')->nullable()->constrained('members')->nullOnDelete();
            // members | leaders | invited
            $table->string('visibility', 16)->default('members');
            $table->unsignedBigInteger('budget_amount')->nullable();
            $table->unsignedSmallInteger('expected_attendance')->nullable();
            // planned | confirmed | done | cancelled
            $table->string('status', 16)->default('planned');
            $table->text('report')->nullable();
            $table->text('lessons')->nullable();
            $table->foreignId('fund_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('event_invitees', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->primary(['event_id', 'member_id']);
        });

        Schema::create('event_committees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('lead_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('event_committee_member', function (Blueprint $table) {
            $table->foreignId('event_committee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->primary(['event_committee_id', 'member_id']);
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('service');
            $table->string('phone', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('event_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_committee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->foreignId('owner_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            // todo | doing | done | blocked
            $table->string('status', 16)->default('todo')->index();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_committee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responsible_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('label');
            $table->unsignedBigInteger('estimated_amount')->default(0);
            $table->unsignedBigInteger('actual_amount')->nullable();
            $table->boolean('is_done')->default(false);
            $table->timestamps();
        });

        Schema::create('event_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            // yes | no | maybe
            $table->string('response', 8);
            $table->unsignedTinyInteger('guests')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'member_id']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('title');
            $table->text('body');
            // official | post
            $table->string('kind', 16)->default('post');
            $table->boolean('is_important')->default(false);
            $table->boolean('is_pinned')->default(false);
            // members | leaders
            $table->string('visibility', 16)->default('members');
            // published | hidden
            $table->string('status', 16)->default('published');
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 16)->default('visible');
            $table->timestamps();
        });

        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->morphs('reactable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();
            $table->unique(['reactable_type', 'reactable_id', 'user_id', 'emoji'], 'reactions_unique');
        });

        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('question');
            $table->text('description')->nullable();
            $table->dateTime('opens_at');
            $table->dateTime('closes_at');
            // members | leaders
            $table->string('eligibility', 16)->default('members');
            // always | after_vote | after_close | leaders_only
            $table->string('result_visibility', 16)->default('after_vote');
            $table->boolean('allow_multiple')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['poll_id', 'poll_option_id', 'user_id']);
        });

        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('year')->nullable()->index();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_annual_portrait')->default(false);
            // members | leaders
            $table->string('visibility', 16)->default('members');
            $table->unsignedBigInteger('cover_media_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('album_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('caption')->nullable();
            $table->date('taken_on')->nullable();
            $table->boolean('allow_download')->default(true);
            // visible | hidden
            $table->string('status', 16)->default('visible');
            $table->timestamps();
        });

        Schema::create('takedown_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            // open | upheld | declined
            $table->string('status', 16)->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['takedown_requests', 'media_assets', 'albums', 'poll_votes', 'poll_options', 'polls', 'reactions',
            'comments', 'announcements', 'event_rsvps', 'event_budget_lines', 'event_tasks', 'vendors',
            'event_committee_member', 'event_committees', 'event_invitees', 'events'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
