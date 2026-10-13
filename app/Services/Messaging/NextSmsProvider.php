<?php

namespace App\Services\Messaging;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * NextSMS "Messaging Service API V2" (https://messaging-service.co.tz).
 * Verified against the official Postman collection linked from
 * https://www.nextsms.co.tz/service/whatsapp (collection 1679195/2sAYkDP1XN):
 *
 *   POST /api/whatsapp/v2/text/single        Bearer token, JSON
 *   POST /api/whatsapp/v2/test/text/single   same, but nothing is sent
 *   body: {to:[255…], account, template, personalisation:[{var:value}],
 *          button:{personalisation:{copy_otp_code:{parameters:[code]}}}, reference}
 *   200:  {messages:[{to, status:{groupId,groupName,id,name}, messageId, price}]}
 *   GET  /api/v2/balance
 *
 * messageId is a 19-digit integer: decoded with JSON_BIGINT_AS_STRING so it
 * never loses precision as a float.
 */
class NextSmsProvider implements WhatsAppProvider
{
    /** Status groups per the API docs. 18 PENDING, 20 DELIVERY, 22 FAILED, 19 REJECTED. */
    private const ACCEPTED_GROUPS = [18, 20];

    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'nextsms';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['token'] ?? null) && filled($this->config['whatsapp_account'] ?? null);
    }

    public function sendTemplate(WhatsAppMessage $message): SendResult
    {
        if (! $this->isConfigured()) {
            return SendResult::failed('NextSMS is not configured (NEXTSMS_TOKEN / NEXTSMS_WHATSAPP_ACCOUNT).', false);
        }

        $payload = array_filter([
            'to' => [(int) $message->to],
            'account' => $this->config['whatsapp_account'],
            'template' => $message->template,
            'personalisation' => $message->personalisation ? [$message->personalisation] : null,
            'button' => $message->button,
            'reference' => $message->reference,
        ], fn ($v) => $v !== null);

        $path = $this->config['test_mode'] ? '/api/whatsapp/v2/test/text/single' : '/api/whatsapp/v2/text/single';

        try {
            $response = $this->client()->post($path, $payload);
        } catch (ConnectionException $e) {
            return SendResult::failed('Connection to NextSMS failed: '.$e->getMessage(), true);
        } catch (Throwable $e) {
            Log::warning('nextsms.send.exception', ['error' => $e->getMessage()]);

            return SendResult::failed('Unexpected error talking to NextSMS.', true);
        }

        return $this->interpret($response);
    }

    public function balance(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }
        try {
            $r = $this->client()->get('/api/v2/balance');

            return $r->successful() ? $r->json() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Public for tests: map an HTTP response to a SendResult. */
    public function interpret(Response $response): SendResult
    {
        $status = $response->status();
        $body = json_decode($response->body(), true, 512, JSON_BIGINT_AS_STRING) ?: [];
        // Never keep the template text the provider echoes back (it can contain an OTP).
        $safeRaw = $this->redact($body);

        if ($status === 401 || $status === 403) {
            return SendResult::failed("NextSMS rejected the credentials (HTTP {$status}).", false, $safeRaw);
        }
        if ($status === 429 || $status >= 500) {
            return SendResult::failed("NextSMS temporarily unavailable (HTTP {$status}).", true, $safeRaw);
        }
        if (! $response->successful()) {
            return SendResult::failed("NextSMS refused the request (HTTP {$status}).", false, $safeRaw);
        }

        $first = $body['messages'][0] ?? null;
        if (! is_array($first)) {
            return SendResult::failed('NextSMS response had no message result.', false, $safeRaw);
        }

        $groupId = (int) ($first['status']['groupId'] ?? 0);
        $accepted = in_array($groupId, self::ACCEPTED_GROUPS, true);

        return new SendResult(
            accepted: $accepted,
            messageId: isset($first['messageId']) ? (string) $first['messageId'] : null,
            statusId: isset($first['status']['id']) ? (int) $first['status']['id'] : null,
            statusName: $first['status']['name'] ?? null,
            statusGroup: $first['status']['groupName'] ?? null,
            price: isset($first['price']) ? (int) $first['price'] : null,
            // 57 = not enough credits: retry later once topped up
            retryable: ! $accepted && in_array((int) ($first['status']['id'] ?? 0), [57], true),
            error: $accepted ? null : ($first['status']['description'] ?? $first['status']['name'] ?? 'Rejected by provider'),
            raw: $safeRaw,
        );
    }

    private function redact(array $body): array
    {
        foreach ($body['messages'] ?? [] as $i => $m) {
            unset($body['messages'][$i]['message']);
        }

        return $body;
    }

    private function client()
    {
        return Http::baseUrl($this->config['base_url'])
            ->withToken((string) $this->config['token'])
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($this->config['timeout'] ?? 20));
    }
}
