<?php

namespace App\Providers;

use App\Models\ActionItem;
use App\Models\Album;
use App\Models\Announcement;
use App\Models\CharityActivity;
use App\Models\ComplianceCase;
use App\Models\ConstitutionAmendment;
use App\Models\ConstitutionVersion;
use App\Models\ContributionRule;
use App\Models\Decision;
use App\Models\Event;
use App\Models\EventTask;
use App\Models\Expense;
use App\Models\ImportBatch;
use App\Models\InvestmentValuation;
use App\Models\LedgerTransaction;
use App\Models\MediaAsset;
use App\Models\Meeting;
use App\Models\MeetingMinute;
use App\Models\Member;
use App\Models\NotificationJob;
use App\Models\Obligation;
use App\Models\PaymentSubmission;
use App\Models\PolicyRule;
use App\Models\Poll;
use App\Models\ReminderRule;
use App\Models\Role;
use App\Models\SecretoSeason;
use App\Models\User;
use App\Models\VerifiedPayment;
use App\Models\WelfareCase;
use App\Models\WelfareDisbursement;
use App\Services\Messaging\FakeProvider;
use App\Services\Messaging\NextSmsProvider;
use App\Services\Messaging\WhatsAppProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsAppProvider::class, function ($app) {
            $driver = config('family.messaging.driver');
            if ($driver === 'fake') {
                // Production must never silently pretend to send (or verify) an OTP.
                if (! in_array($app->environment(), config('family.messaging.fake_allowed_envs'), true)) {
                    throw new RuntimeException('FA_MESSAGING_DRIVER=fake is only allowed in local/testing. Configure NextSMS.');
                }

                return new FakeProvider;
            }
            if ($driver === 'nextsms') {
                return new NextSmsProvider(config('family.nextsms'));
            }
            throw new RuntimeException("Unknown messaging driver [{$driver}].");
        });
    }

    public function boot(): void
    {
        // Fail loudly at boot (not on first OTP) if production is misconfigured with the fake driver.
        if (config('family.messaging.driver') === 'fake' && ! in_array($this->app->environment(), config('family.messaging.fake_allowed_envs'), true)) {
            throw new RuntimeException('FA_MESSAGING_DRIVER=fake is only allowed in local/testing. Configure NextSMS.');
        }

        Carbon::setLocale(app()->getLocale());

        // Stable morph names keep audit logs readable and decoupled from class names.
        Relation::enforceMorphMap([
            'member' => Member::class,
            'user' => User::class,
            'payment' => VerifiedPayment::class,
            'submission' => PaymentSubmission::class,
            'obligation' => Obligation::class,
            'expense' => Expense::class,
            'ledger_txn' => LedgerTransaction::class,
            'welfare_case' => WelfareCase::class,
            'welfare_disbursement' => WelfareDisbursement::class,
            'charity' => CharityActivity::class,
            'meeting' => Meeting::class,
            'minutes' => MeetingMinute::class,
            'decision' => Decision::class,
            'action_item' => ActionItem::class,
            'constitution' => ConstitutionVersion::class,
            'amendment' => ConstitutionAmendment::class,
            'policy_rule' => PolicyRule::class,
            'compliance' => ComplianceCase::class,
            'event' => Event::class,
            'event_task' => EventTask::class,
            'announcement' => Announcement::class,
            'poll' => Poll::class,
            'album' => Album::class,
            'media' => MediaAsset::class,
            'secreto_season' => SecretoSeason::class,
            'notification_job' => NotificationJob::class,
            'reminder_rule' => ReminderRule::class,
            'role' => Role::class,
            'contribution_rule' => ContributionRule::class,
            'import_batch' => ImportBatch::class,
            'investment_valuation' => InvestmentValuation::class,
        ]);

        // Every dotted ability ("finance.verify") is a permission key. Role names are never checked.
        Gate::before(function (User $user, string $ability) {
            if (str_contains($ability, '.')) {
                return $user->hasPermission($ability);
            }

            return null;
        });

        RateLimiter::for('otp-verify', fn (Request $r) => [
            Limit::perMinute(10)->by('ip:'.$r->ip()),
            Limit::perHour(30)->by('phone:'.$r->session()->get('otp.phone', 'none')),
        ]);
        RateLimiter::for('otp-send', fn (Request $r) => Limit::perMinute(5)->by('ip:'.$r->ip()));
        RateLimiter::for('invite', fn (Request $r) => Limit::perMinute(10)->by('ip:'.$r->ip()));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(30)->by('u:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('webhooks', fn (Request $r) => Limit::perMinute(600)->by('ip:'.$r->ip()));
    }
}
