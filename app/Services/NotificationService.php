<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Jobs\SendNotificationDelivery;
use App\Models\Event;
use App\Models\Member;
use App\Models\NotificationDelivery;
use App\Models\NotificationJob;
use App\Models\NotificationTemplate;
use App\Models\OtpChallenge;
use App\Models\ProviderWebhookEvent;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Messaging\WhatsAppMessage;
use App\Services\Messaging\WhatsAppProvider;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationService
{
    public const CATEGORIES = [
        'contributions', 'balances', 'penalties', 'meetings', 'birthdays', 'celebrations',
        'events', 'committee_tasks', 'welfare', 'charity', 'secreto', 'governance', 'security',
    ];

    /** Categories a member cannot switch off (account security, constitution notices). */
    public const MANDATORY = ['security', 'governance'];

    public const AUDIENCES = ['all_active', 'leaders', 'role', 'members', 'event', 'arrears'];

    public function __construct(
        private readonly WhatsAppProvider $provider,
        private readonly ContributionService $contributions,
    ) {}

    // ------------------------------------------------------------------ scheduling

    public function schedule(
        NotificationTemplate $template,
        string $title,
        array $audience,
        Carbon $scheduledAt,
        array $staticVariables,
        ?User $by,
        ?Model $source = null,
        ?string $idempotencyKey = null,
        bool $preAuthorised = false,
    ): NotificationJob {
        if ($idempotencyKey && ($existing = NotificationJob::where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }
        if ($template->is_sensitive && ($audience['type'] ?? null) !== 'arrears' && count($audience['member_ids'] ?? []) !== 1) {
            throw new DomainRuleException(__('notifications.sensitive_group'));
        }

        $recipients = $this->resolveAudience($audience, $template->category);
        $needsApproval = ! $preAuthorised && $recipients->count() > (int) config('family.messaging.approval_threshold');

        $job = NotificationJob::create([
            'notification_template_id' => $template->id,
            'category' => $template->category,
            'title' => $title,
            'audience' => $audience,
            'static_variables' => $staticVariables,
            'scheduled_at' => $scheduledAt,
            'status' => $needsApproval ? 'pending_approval' : 'scheduled',
            'requires_approval' => $needsApproval,
            'estimated_recipients' => $recipients->count(),
            'estimated_cost' => $recipients->count() * (int) config('family.nextsms.price_per_message'),
            'idempotency_key' => $idempotencyKey ?? 'manual:'.Str::ulid(),
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'created_by' => $by?->id,
        ]);
        Audit::record('notifications.scheduled', $job, 'Notification scheduled: '.$title, ['recipients' => $recipients->count(), 'needs_approval' => $needsApproval]);

        return $job;
    }

    public function approve(NotificationJob $job, User $by): void
    {
        if ($job->created_by === $by->id) {
            throw new DomainRuleException(__('notifications.no_self_approve'));
        }
        if ($job->status !== 'pending_approval') {
            throw new DomainRuleException(__('notifications.not_pending'));
        }
        $job->update(['status' => 'scheduled', 'approved_by' => $by->id, 'approved_at' => now()]);
        Audit::record('notifications.approved', $job, 'Broadcast approved');
    }

    public function cancel(NotificationJob $job, User $by): void
    {
        $updated = NotificationJob::whereKey($job->id)->whereIn('status', ['draft', 'pending_approval', 'scheduled'])
            ->update(['status' => 'cancelled', 'cancelled_by' => $by->id, 'cancelled_at' => now()]);
        if (! $updated) {
            throw new DomainRuleException(__('notifications.cannot_cancel'));
        }
        Audit::record('notifications.cancelled', $job, 'Notification cancelled');
    }

    // ------------------------------------------------------------------ dispatch

    /** Called every minute by the scheduler. Never depends on a browser being open. */
    public function dispatchDue(?Carbon $now = null): int
    {
        $now ??= now();
        $count = 0;
        $due = NotificationJob::where('status', 'scheduled')->where('scheduled_at', '<=', $now)->pluck('id');

        foreach ($due as $id) {
            // Atomic claim: only one scheduler run can move a job to processing.
            if (! NotificationJob::whereKey($id)->where('status', 'scheduled')->update(['status' => 'processing', 'started_at' => now()])) {
                continue;
            }
            $job = NotificationJob::with('template')->find($id);
            $recipients = $this->resolveAudience($job->audience, $job->category);
            foreach ($recipients as $member) {
                $vars = $this->variablesFor($job, $member);
                NotificationDelivery::firstOrCreate(
                    ['notification_job_id' => $job->id, 'member_id' => $member->id],
                    ['phone_e164' => $member->phone_e164, 'variables' => $vars, 'status' => 'queued'],
                );
            }
            $count++;
        }

        // Queue every delivery that is due (new ones and retries whose backoff has elapsed).
        NotificationDelivery::where('status', 'queued')
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))
            ->whereHas('job', fn ($q) => $q->where('status', 'processing'))
            ->pluck('id')
            ->each(fn ($id) => SendNotificationDelivery::dispatch($id));

        $this->finaliseJobs();

        return $count;
    }

    /** Send one delivery. Safe to call twice: the row lock + status check prevent duplicate sends. */
    public function send(int $deliveryId): void
    {
        $delivery = DB::transaction(function () use ($deliveryId) {
            $d = NotificationDelivery::whereKey($deliveryId)->lockForUpdate()->first();
            if (! $d || $d->status !== 'queued' || ($d->next_attempt_at && $d->next_attempt_at->isFuture())) {
                return null;
            }
            $d->update(['status' => 'sending', 'attempts' => $d->attempts + 1]);

            return $d;
        });
        if (! $delivery) {
            return;
        }

        $job = $delivery->job()->with('template')->first();
        $template = $job->template;
        $member = $delivery->member;

        if ($job->status === 'cancelled') {
            $delivery->update(['status' => 'skipped', 'skip_reason' => 'job_cancelled']);

            return;
        }
        if (! $template->isSendable()) {
            $delivery->update(['status' => 'failed', 'last_error' => __('notifications.template_not_ready')]);

            return;
        }
        if (! $member->wantsCategory($job->category) && ! in_array($job->category, self::MANDATORY, true)) {
            $delivery->update(['status' => 'skipped', 'skip_reason' => 'opted_out']);

            return;
        }
        if ($next = $this->quietHoursEnd()) {
            $delivery->update(['status' => 'queued', 'attempts' => $delivery->attempts - 1, 'next_attempt_at' => $next]);

            return;
        }

        $vars = $delivery->variables ?? [];
        $personalisation = [];
        foreach ($template->variables as $name) {
            $personalisation[$name] = (string) ($vars[$name] ?? '');
        }

        $result = $this->provider->sendTemplate(new WhatsAppMessage(
            to: $delivery->phone_e164,
            template: $template->provider_template,
            personalisation: $personalisation,
            reference: 'fa-'.$delivery->id,
        ));

        if ($result->accepted) {
            $delivery->update([
                'status' => 'submitted',
                'provider_message_id' => $result->messageId,
                'provider_status_id' => $result->statusId,
                'provider_status_name' => $result->statusName,
                'provider_response' => $result->raw,
                'price' => $result->price,
                'submitted_at' => now(),
                'last_error' => null,
            ]);

            return;
        }

        $backoff = config('family.messaging.retry_backoff_minutes');
        $canRetry = $result->retryable && $delivery->attempts < (int) config('family.messaging.max_attempts');
        $delivery->update([
            'status' => $canRetry ? 'queued' : 'failed',
            'next_attempt_at' => $canRetry ? now()->addMinutes($backoff[min($delivery->attempts - 1, count($backoff) - 1)]) : null,
            'provider_status_id' => $result->statusId,
            'provider_status_name' => $result->statusName,
            'provider_response' => $result->raw,
            'last_error' => $result->error,
        ]);
    }

    /** Manual retry of failed deliveries (never re-sends anything already accepted). */
    public function retryFailed(NotificationJob $job, User $by): int
    {
        $n = $job->deliveries()->where('status', 'failed')->update(['status' => 'queued', 'next_attempt_at' => null, 'attempts' => 0]);
        if ($n) {
            $job->update(['status' => 'processing']);
            Audit::record('notifications.retry', $job, "Retry of {$n} failed deliveries");
        }

        return $n;
    }

    public function finaliseJobs(): void
    {
        NotificationJob::where('status', 'processing')->get()->each(function (NotificationJob $job) {
            $pending = $job->deliveries()->whereIn('status', ['queued', 'sending'])->exists();
            if (! $pending) {
                $failed = $job->deliveries()->where('status', 'failed')->count();
                $total = $job->deliveries()->count();
                $job->update(['status' => $total > 0 && $failed === $total ? 'failed' : 'completed', 'completed_at' => now()]);
            }
        });
    }

    // ------------------------------------------------------------------ webhooks

    /**
     * NextSMS posts {token, messageId, status, id} (delivery) or {token, messageId, response, payload, text}
     * (WhatsApp reply). The token must equal the Delivery Callback Verify Token configured in the dashboard.
     */
    public function handleWebhook(array $payload): string
    {
        $expected = (string) config('family.nextsms.webhook_token');
        $valid = $expected !== '' && is_string($payload['token'] ?? null) && hash_equals($expected, $payload['token']);
        $stored = $payload;
        unset($stored['token']);
        $event = ProviderWebhookEvent::create(['provider' => 'nextsms', 'payload' => $stored, 'is_valid' => $valid]);

        if (! $valid) {
            $event->update(['result' => 'rejected_token']);

            return 'rejected';
        }

        $messageId = (string) ($payload['messageId'] ?? '');
        $statusName = strtoupper((string) ($payload['status'] ?? ''));
        $statusId = isset($payload['id']) ? (int) $payload['id'] : null;
        $mapped = match (true) {
            $statusName === 'READ' || $statusId === 108 => 'read',
            $statusName === 'DELIVERED' || $statusId === 73 => 'delivered',
            in_array($statusId, [74, 75, 76, 79, 80], true), in_array($statusName, ['EXPIRED', 'DELETED', 'UNDELIVERABLE', 'REJECTED', 'SKIPPED'], true) => 'undeliverable',
            default => null,
        };

        if ($messageId === '' || $mapped === null) {
            $event->update(['result' => isset($payload['response']) ? 'reply_ignored' : 'unmapped']);

            return 'ignored';
        }

        $d = NotificationDelivery::where('provider_message_id', $messageId)->first();
        if ($d && ! ($d->status === 'read' && $mapped === 'delivered')) {
            $d->update([
                'status' => $mapped,
                'provider_status_id' => $statusId,
                'provider_status_name' => $statusName,
                'delivered_at' => in_array($mapped, ['delivered', 'read'], true) ? ($d->delivered_at ?? now()) : null,
            ]);
            $event->update(['result' => 'delivery:'.$mapped]);

            return 'updated';
        }

        $otp = OtpChallenge::where('provider_message_id', $messageId)->first();
        if ($otp) {
            $otp->update(['delivery_status' => $mapped === 'undeliverable' ? 'failed' : 'delivered']);
            $event->update(['result' => 'otp:'.$mapped]);

            return 'updated';
        }
        $event->update(['result' => 'unknown_message']);

        return 'ignored';
    }

    // ------------------------------------------------------------------ audiences & variables

    /** @return Collection<int, Member> */
    public function resolveAudience(array $audience, string $category): Collection
    {
        $q = Member::query()->whereNotNull('phone_e164')->whereNull('whatsapp_opt_out_at')->with('notificationPreferences');
        $type = $audience['type'] ?? 'all_active';

        $q = match ($type) {
            'all_active' => $q->contributing(),
            'leaders' => $q->whereHas('user', fn ($u) => $u->whereHas('roles', fn ($r) => $r->where('key', '!=', 'member'))),
            'role' => $q->whereHas('user.roles', fn ($r) => $r->where('key', $audience['role'] ?? '')),
            'members' => $q->whereIn('id', array_map('intval', $audience['member_ids'] ?? [])),
            'event' => $this->eventAudience($q, (int) ($audience['event_id'] ?? 0)),
            'arrears' => $q->contributing()->whereHas('obligations', fn ($o) => $o->outstanding()->where('due_date', '<', now()->toDateString())),
            default => throw new DomainRuleException('Unknown audience.'),
        };

        return $q->get()->filter(fn (Member $m) => in_array($category, self::MANDATORY, true) || $m->wantsCategory($category))->values();
    }

    private function eventAudience($q, int $eventId)
    {
        $event = Event::find($eventId);
        if (! $event) {
            return $q->whereRaw('1 = 0');
        }

        return $event->visibility === 'invited'
            ? $q->whereIn('id', $event->invitees()->pluck('members.id'))
            : ($event->visibility === 'leaders' ? $q->whereHas('user.roles', fn ($r) => $r->where('key', '!=', 'member')) : $q->contributing());
    }

    public function variablesFor(NotificationJob $job, Member $member): array
    {
        $vars = (array) $job->static_variables;
        $vars['name'] = $member->displayName();
        $vars['portal_link'] = $vars['portal_link'] ?? url('/');
        if ($job->template?->is_sensitive) {
            $s = $this->contributions->summary($member);
            $vars['amount'] = Money::format($s['overdue']);
            $vars['months'] = (string) $s['months_in_arrears'];
        }

        return $vars;
    }

    public function previewFor(NotificationTemplate $template, array $staticVars, ?Member $sample): string
    {
        $vars = $staticVars + ['name' => $sample?->displayName() ?? 'Mwanafamilia', 'portal_link' => url('/')];
        if ($template->is_sensitive) {
            $vars['amount'] = 'TSh •••';
            $vars['months'] = '•';
        }

        return $template->render($vars);
    }

    /** Returns when quiet hours end if we are inside them, else null. */
    public function quietHoursEnd(?Carbon $at = null): ?Carbon
    {
        $at ??= now();
        $qh = SystemSetting::find('notifications.quiet_hours')?->value;
        if (! $qh || empty($qh['start']) || empty($qh['end'])) {
            return null;
        }
        $start = $at->copy()->setTimeFromTimeString($qh['start']);
        $end = $at->copy()->setTimeFromTimeString($qh['end']);
        if ($start->lt($end)) {
            return $at->between($start, $end) ? $end : null;
        }
        // Overnight window, e.g. 21:00 -> 07:00
        if ($at->gte($start)) {
            return $end->addDay();
        }

        return $at->lt($end) ? $end : null;
    }
}
