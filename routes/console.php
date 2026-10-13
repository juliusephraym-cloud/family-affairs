<?php

use App\Models\Invitation;
use App\Models\Meeting;
use App\Models\Member;
use App\Models\MembershipStatusHistory;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\ReminderEngine;
use App\Support\Phone;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Scheduled work — run by `php artisan schedule:run` every minute (cron).
| Reminders never depend on anyone's browser being open.
|--------------------------------------------------------------------------
*/
Schedule::call(fn () => Cache::forever('scheduler:heartbeat', now()->toIso8601String()))->everyMinute()->name('heartbeat');
Schedule::command('fa:notifications-dispatch')->everyMinute()->withoutOverlapping(5);
Schedule::command('fa:reminders-run')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('fa:prune')->dailyAt('03:15');
// Public demo: start every day from the same fictional data set.
Schedule::command('fa:demo-reset --force')->dailyAt('03:00')->when(fn () => (bool) config('family.demo_mode'));
// Fallback worker for hosts without a supervised `queue:work` process.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=1')->everyMinute()->withoutOverlapping(2)->when(fn () => (bool) config('family.queue_from_scheduler'));

Artisan::command('fa:notifications-dispatch', function (NotificationService $n) {
    $count = $n->dispatchDue();
    $this->info("Dispatched {$count} job(s).");
})->purpose('Queue due WhatsApp notifications and finalise finished jobs');

Artisan::command('fa:reminders-run', function (ReminderEngine $engine) {
    foreach ($engine->run() as $rule => $n) {
        $this->line("{$rule}: {$n}");
    }
})->purpose('Create automatic reminder jobs (idempotent)');

Artisan::command('fa:prune', function () {
    $otp = OtpChallenge::where('created_at', '<', now()->subDays(30))->delete();
    $hooks = DB::table('provider_webhook_events')->where('created_at', '<', now()->subDays(180))->delete();
    $outbox = DB::table('dev_outbox')->where('created_at', '<', now()->subDays(7))->delete();
    $this->info("Pruned {$otp} OTP challenges, {$hooks} webhook events, {$outbox} dev outbox rows.");
})->purpose('Retention: delete expired security artefacts');

/*
| First administrator. Creates the member record, a super-admin account and an
| invitation link. The person then verifies their own phone by WhatsApp OTP —
| there is no password anywhere in the system.
*/
Artisan::command('fa:bootstrap-admin {phone} {name}', function (string $phone, string $name) {
    $e164 = Phone::normalize($phone);
    if (! $e164) {
        $this->error('Invalid phone number.');

        return 1;
    }
    $result = DB::transaction(function () use ($e164, $name) {
        $member = Member::firstOrCreate(['phone_e164' => $e164], ['full_name' => $name, 'membership_status' => 'active', 'joined_on' => now()]);
        if ($member->wasRecentlyCreated) {
            MembershipStatusHistory::create(['member_id' => $member->id, 'to_status' => 'active', 'reason' => 'Bootstrap administrator (console)']);
        }
        $user = User::firstOrCreate(['member_id' => $member->id], ['phone_e164' => $e164, 'status' => 'active']);
        $user->roles()->syncWithoutDetaching([Role::where('key', 'super_admin')->value('id') => ['assigned_at' => now()], Role::where('key', 'member')->value('id') => ['assigned_at' => now()]]);
        Invitation::where('member_id', $member->id)->whereNull('accepted_at')->update(['revoked_at' => now()]);
        $token = Str::random(48);
        Invitation::create(['member_id' => $member->id, 'token_hash' => hash('sha256', $token), 'phone_e164' => $e164, 'expires_at' => now()->addDays(2)]);

        return route('invitation.show', $token);
    });
    $this->info('Administrator prepared. Open this link on the phone that owns '.Phone::display($e164).' (valid 48 hours):');
    $this->line($result);
})->purpose('Create the first super administrator and print a one-time invitation link');

/*
| Import a .docx of meeting minutes as a DRAFT for the Secretary to review.
| Nothing is marked approved, and no names are linked automatically.
*/
Artisan::command('fa:import-minutes {path} {--date=}', function (string $path) {
    $zip = new ZipArchive;
    if (! is_file($path) || $zip->open($path) !== true || ($xml = $zip->getFromName('word/document.xml')) === false) {
        $this->error('Not a readable .docx file.');

        return 1;
    }
    $xml = preg_replace('/<\/w:p>/', "\n", $xml);
    $text = trim(html_entity_decode(strip_tags($xml)));
    $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($l) => $l !== ''));
    $title = mb_substr($lines[0] ?? 'Imported minutes', 0, 150);
    $date = $this->option('date') ?: $this->ask('Meeting date (YYYY-MM-DD) — check it against the chat, the document may be wrong', now()->toDateString());
    $meeting = Meeting::create(['title' => $title, 'type' => 'general', 'starts_at' => $date.' 12:00:00', 'status' => 'held', 'notes' => 'Imported from '.basename($path).' — REVIEW REQUIRED']);
    $meeting->minutes()->create(['status' => 'draft', 'opening_notes' => "[IMEINGIZWA KUTOKA KWENYE HATI — INAHITAJI UKAGUZI]\n\n".implode("\n", $lines)]);
    $this->info("Draft meeting #{$meeting->id} created: {$title}. Review it under Vikao before submitting for approval.");
})->purpose('Import a minutes .docx as an unapproved draft');

Artisan::command('fa:demo-reset {--force}', function () {
    if (! config('family.demo_mode') && ! app()->environment('local')) {
        $this->error('Only available in demo mode.');

        return 1;
    }
    if (! $this->option('force') && ! $this->confirm('Wipe ALL data and reload the demo?')) {
        return 1;
    }
    Storage::disk('private')->deleteDirectory('albums');
    Storage::disk('private')->deleteDirectory('members');
    Storage::disk('private')->deleteDirectory('receipts');
    $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
    Cache::flush();
    $this->info('Demo data reloaded.');
})->purpose('Public demo: wipe and reload the fictional data set');
