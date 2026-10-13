<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Invitation;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\Messaging\WhatsAppMessage;
use App\Services\Messaging\WhatsAppProvider;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * One-time codes over WhatsApp.
 *
 *  - Codes come from random_int (CSPRNG); only an HMAC(app key, challenge id + code) is stored.
 *  - A challenge dies on: success, expiry, too many attempts, or a newer challenge for the phone.
 *  - Verification takes a row lock so two concurrent submissions cannot both succeed.
 *  - Callers show the same message whether or not a phone is registered (no enumeration).
 *  - Codes are never logged, returned in responses, or stored in provider logs.
 */
class OtpService
{
    public function __construct(private readonly WhatsAppProvider $provider) {}

    /**
     * Issue a challenge. Returns null (silently) when the purpose is "login"
     * and the phone has no usable account, so the caller can respond identically.
     *
     * @throws DomainRuleException when throttled
     */
    public function issue(string $phone, string $purpose, ?string $ip, ?User $user = null, ?Invitation $invitation = null, ?string $newPhone = null): ?OtpChallenge
    {
        $cfg = config('family.otp');
        $this->throttle($phone, $ip, $cfg);

        if ($purpose === 'login') {
            $user = User::where('phone_e164', $phone)->first();
            if (! $user || ! $user->isUsable()) {
                // Spend the same throttle budget and do nothing else.
                return null;
            }
        }

        $code = $this->generateCode((int) $cfg['length']);

        $challenge = DB::transaction(function () use ($phone, $purpose, $user, $invitation, $newPhone, $ip, $cfg, $code) {
            // Only the newest code for a phone+purpose is ever valid.
            OtpChallenge::where('phone_e164', $phone)->where('purpose', $purpose)
                ->whereNull('consumed_at')->whereNull('invalidated_at')
                ->update(['invalidated_at' => now()]);

            $challenge = new OtpChallenge([
                'phone_e164' => $phone,
                'user_id' => $user?->id,
                'invitation_id' => $invitation?->id,
                'purpose' => $purpose,
                'new_phone_e164' => $newPhone,
                'code_hash' => '',
                'max_attempts' => (int) $cfg['max_attempts'],
                'expires_at' => now()->addMinutes((int) $cfg['ttl_minutes']),
                'ip_address' => $ip,
            ]);
            $challenge->id = (string) Str::ulid();
            $challenge->code_hash = $this->hash($challenge->id, $code);
            $challenge->save();

            return $challenge;
        });

        $template = config('family.nextsms.otp_template') ?: 'family_affairs_otp';
        $result = $this->provider->sendTemplate(new WhatsAppMessage(
            to: $phone,
            template: $template,
            button: ['personalisation' => ['copy_otp_code' => ['parameters' => [$code]]]],
            reference: 'otp-'.$challenge->id,
        ));
        unset($code);

        $challenge->forceFill([
            'delivery_status' => $result->accepted ? 'submitted' : 'failed',
            'provider_message_id' => $result->messageId,
        ])->save();

        Audit::record('auth.otp_issued', $user, 'Verification code requested', [
            'purpose' => $purpose, 'phone' => Phone::mask($phone), 'provider_accepted' => $result->accepted,
        ]);

        if (! $result->accepted) {
            Log::warning('otp.send_failed', ['phone' => Phone::mask($phone), 'error' => $result->error]);
            $challenge->forceFill(['invalidated_at' => now()])->save();
            throw new DomainRuleException(__('auth.otp_send_failed'));
        }

        return $challenge;
    }

    /**
     * @return array{ok: bool, challenge: ?OtpChallenge, reason: ?string}
     */
    public function verify(string $challengeId, string $code, string $expectedPhone): array
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        return DB::transaction(function () use ($challengeId, $code, $expectedPhone) {
            /** @var OtpChallenge|null $c */
            $c = OtpChallenge::whereKey($challengeId)->lockForUpdate()->first();

            if (! $c || ! hash_equals($c->phone_e164, $expectedPhone)) {
                return ['ok' => false, 'challenge' => null, 'reason' => 'invalid'];
            }
            if (! $c->isOpen()) {
                return ['ok' => false, 'challenge' => $c, 'reason' => $c->expires_at->isPast() ? 'expired' : 'closed'];
            }

            $c->attempts++;
            if (strlen($code) !== (int) config('family.otp.length') || ! hash_equals($c->code_hash, $this->hash($c->id, $code))) {
                $c->save();
                Audit::record('auth.otp_failed', null, 'Wrong verification code', ['phone' => Phone::mask($c->phone_e164), 'attempt' => $c->attempts]);

                return ['ok' => false, 'challenge' => $c, 'reason' => $c->attempts >= $c->max_attempts ? 'locked' : 'wrong'];
            }

            $c->consumed_at = now();
            $c->save();

            return ['ok' => true, 'challenge' => $c, 'reason' => null];
        });
    }

    public function secondsUntilResend(string $phone): int
    {
        $last = OtpChallenge::where('phone_e164', $phone)->latest('created_at')->first();
        if (! $last) {
            return 0;
        }

        return max(0, (int) config('family.otp.resend_seconds') - (int) $last->created_at->diffInSeconds(now()));
    }

    private function throttle(string $phone, ?string $ip, array $cfg): void
    {
        if ($this->secondsUntilResend($phone) > 0) {
            throw new DomainRuleException(__('auth.otp_wait', ['seconds' => $this->secondsUntilResend($phone)]));
        }
        $phoneKey = 'otp-send:phone:'.$phone;
        $ipKey = 'otp-send:ip:'.($ip ?? 'none');
        if (RateLimiter::tooManyAttempts($phoneKey, (int) $cfg['max_sends_per_hour'])
            || RateLimiter::tooManyAttempts($ipKey, (int) $cfg['max_sends_per_ip_per_hour'])) {
            throw new DomainRuleException(__('auth.otp_too_many'));
        }
        RateLimiter::hit($phoneKey, 3600);
        RateLimiter::hit($ipKey, 3600);
    }

    private function generateCode(int $length): string
    {
        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    private function hash(string $challengeId, string $code): string
    {
        return hash_hmac('sha256', $challengeId.'|'.$code, (string) config('app.key'));
    }
}
