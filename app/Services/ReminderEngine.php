<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Meeting;
use App\Models\Member;
use App\Models\ReminderRule;
use App\Models\SecretoSeason;
use Illuminate\Support\Carbon;

/**
 * Automatic reminders. Each rule is disabled until an administrator enables
 * it (which is the standing authorisation for its sends) and has an approved
 * provider template. Every run is idempotent through the job idempotency key,
 * so running it every few minutes can never double-send.
 */
class ReminderEngine
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** @return array<string,int> jobs created per rule */
    public function run(?Carbon $now = null): array
    {
        $now ??= now();
        $created = [];
        foreach (ReminderRule::where('enabled', true)->with('template')->get() as $rule) {
            if (! $rule->template?->isSendable()) {
                continue;
            }
            $created[$rule->key] = match ($rule->key) {
                'monthly_contribution' => $this->monthly($rule, $now),
                'private_balance' => $this->balance($rule, $now),
                'birthday_greeting' => $this->birthdays($rule, $now),
                'meeting_reminder' => $this->meetings($rule, $now),
                'event_reminder' => $this->events($rule, $now),
                'secreto_deadline' => $this->secreto($rule, $now),
                default => 0,
            };
            $rule->update(['last_run_at' => $now]);
        }

        return $created;
    }

    private function atConfiguredTime(ReminderRule $rule, Carbon $now): bool
    {
        return $now->format('H:i') >= ($rule->config['time'] ?? '09:00');
    }

    private function monthly(ReminderRule $rule, Carbon $now): int
    {
        if ($now->day !== (int) ($rule->config['day'] ?? 25) || ! $this->atConfiguredTime($rule, $now)) {
            return 0;
        }
        $period = $now->translatedFormat('F Y');
        $job = $this->notifications->schedule($rule->template, "Ukumbusho wa mchango — {$period}", ['type' => 'all_active'], $now,
            ['period' => $period], null, $rule, 'auto:monthly:'.$now->format('Y-m'), true);

        return $job->wasRecentlyCreated ? 1 : 0;
    }

    private function balance(ReminderRule $rule, Carbon $now): int
    {
        if ($now->day !== (int) ($rule->config['day'] ?? 5) || ! $this->atConfiguredTime($rule, $now)) {
            return 0;
        }
        $job = $this->notifications->schedule($rule->template, 'Salio binafsi — '.$now->format('m/Y'), ['type' => 'arrears'], $now,
            [], null, $rule, 'auto:balance:'.$now->format('Y-m'), true);

        return $job->wasRecentlyCreated ? 1 : 0;
    }

    private function birthdays(ReminderRule $rule, Carbon $now): int
    {
        if (! $this->atConfiguredTime($rule, $now)) {
            return 0;
        }
        $n = 0;
        Member::contributing()->whereNotNull('date_of_birth')->whereNotNull('phone_e164')
            ->whereMonth('date_of_birth', $now->month)->whereDay('date_of_birth', $now->day)->get()
            ->each(function (Member $m) use ($rule, $now, &$n) {
                $job = $this->notifications->schedule($rule->template, 'Heri ya kuzaliwa — '.$m->displayName(), ['type' => 'members', 'member_ids' => [$m->id]], $now,
                    [], null, $rule, 'auto:birthday:'.$m->id.':'.$now->toDateString(), true);
                $n += $job->wasRecentlyCreated ? 1 : 0;
            });

        return $n;
    }

    private function meetings(ReminderRule $rule, Carbon $now): int
    {
        $hours = (int) ($rule->config['hours_before'] ?? 24);
        $n = 0;
        Meeting::where('status', 'planned')->whereBetween('starts_at', [$now, $now->copy()->addHours($hours)])->get()
            ->each(function (Meeting $m) use ($rule, $now, &$n) {
                $job = $this->notifications->schedule($rule->template, 'Kikao: '.$m->title, ['type' => 'all_active'], $now, [
                    'title' => $m->title,
                    'date' => $m->starts_at->translatedFormat('l d/m/Y H:i'),
                    'place' => $m->location ?: ($m->online_method ?: '—'),
                    'portal_link' => route('meetings.show', $m),
                ], null, $m, 'auto:meeting:'.$m->id.':'.$m->starts_at->format('YmdHi'), true);
                $n += $job->wasRecentlyCreated ? 1 : 0;
            });

        return $n;
    }

    private function events(ReminderRule $rule, Carbon $now): int
    {
        $days = (int) ($rule->config['days_before'] ?? 3);
        $n = 0;
        Event::whereIn('status', ['planned', 'confirmed'])->whereIn('visibility', ['members', 'invited'])
            ->whereBetween('starts_at', [$now, $now->copy()->addDays($days)])->get()
            ->each(function (Event $e) use ($rule, $now, &$n) {
                $job = $this->notifications->schedule($rule->template, 'Tukio: '.$e->title, ['type' => 'event', 'event_id' => $e->id], $now, [
                    'title' => $e->title,
                    'date' => $e->starts_at->translatedFormat('l d/m/Y H:i'),
                    'place' => $e->venue ?: '—',
                    'portal_link' => route('events.show', $e),
                ], null, $e, 'auto:event:'.$e->id.':'.$e->starts_at->format('YmdHi'), true);
                $n += $job->wasRecentlyCreated ? 1 : 0;
            });

        return $n;
    }

    private function secreto(ReminderRule $rule, Carbon $now): int
    {
        $days = (int) ($rule->config['days_before'] ?? 7);
        $season = SecretoSeason::where('status', 'drawn')->whereBetween('exchange_at', [$now, $now->copy()->addDays($days)])->first();
        if (! $season) {
            return 0;
        }
        $ids = $season->participants()->pluck('member_id')->all();
        $job = $this->notifications->schedule($rule->template, 'Secreto '.$season->year, ['type' => 'members', 'member_ids' => $ids], $now, [
            'title' => $season->title,
            'date' => $season->exchange_at->translatedFormat('l d/m/Y'),
            'portal_link' => route('secreto.index'),
        ], null, $season, 'auto:secreto:'.$season->id, true);

        return $job->wasRecentlyCreated ? 1 : 0;
    }
}
