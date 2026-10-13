<?php

namespace App\Services\Messaging;

/**
 * Outcome of handing a message to the provider. "accepted" means the provider
 * took it (status group PENDING) — it is NOT proof of delivery. Delivery is
 * only recorded when a delivery report/webhook says DELIVERED or READ.
 */
final class SendResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly ?string $messageId = null,
        public readonly ?int $statusId = null,
        public readonly ?string $statusName = null,
        public readonly ?string $statusGroup = null,
        public readonly ?int $price = null,
        public readonly bool $retryable = false,
        public readonly ?string $error = null,
        public readonly array $raw = [],
    ) {}

    public static function failed(string $error, bool $retryable, array $raw = []): self
    {
        return new self(accepted: false, retryable: $retryable, error: $error, raw: $raw);
    }
}
