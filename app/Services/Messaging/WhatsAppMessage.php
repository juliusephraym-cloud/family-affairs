<?php

namespace App\Services\Messaging;

/**
 * One templated WhatsApp message. WhatsApp business-initiated messages must
 * use a Meta-approved template; free text is not possible through NextSMS.
 */
final class WhatsAppMessage
{
    /**
     * @param  array<string,string>  $personalisation  template {placeholder} => value
     * @param  array<string,mixed>|null  $button  NextSMS "button.personalisation" block
     */
    public function __construct(
        public readonly string $to,
        public readonly string $template,
        public readonly array $personalisation = [],
        public readonly ?array $button = null,
        public readonly ?string $reference = null,
    ) {}
}
