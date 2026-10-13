<?php

namespace App\Services\Messaging;

interface WhatsAppProvider
{
    public function name(): string;

    public function sendTemplate(WhatsAppMessage $message): SendResult;

    /** Account balance in TZS if the provider reports it, else null. */
    public function balance(): ?array;

    /** True when configured enough to attempt real sends. */
    public function isConfigured(): bool;
}
