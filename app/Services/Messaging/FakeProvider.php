<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Development/testing stand-in. Writes to the dev_outbox table so a developer
 * can read the "message" at /dev/outbox. The service provider refuses to boot
 * with this driver outside local/testing, so production can never silently
 * pretend to send an OTP.
 */
class FakeProvider implements WhatsAppProvider
{
    public static bool $failNext = false;

    public function name(): string
    {
        return 'fake';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function sendTemplate(WhatsAppMessage $message): SendResult
    {
        if (self::$failNext) {
            self::$failNext = false;

            return SendResult::failed('Simulated provider outage', true);
        }

        DB::table('dev_outbox')->insert([
            'to' => $message->to,
            'template' => $message->template,
            'payload' => json_encode(['personalisation' => $message->personalisation, 'button' => $message->button, 'reference' => $message->reference]),
            'created_at' => now(),
        ]);

        return new SendResult(
            accepted: true,
            messageId: (string) random_int(10 ** 17, PHP_INT_MAX),
            statusId: 51,
            statusName: 'ENROUTE (SENT)',
            statusGroup: 'PENDING',
            price: 0,
            raw: ['fake' => true, 'ref' => Str::limit((string) $message->reference, 40)],
        );
    }

    public function balance(): ?array
    {
        return ['display' => 'Fake provider (no real balance)'];
    }
}
