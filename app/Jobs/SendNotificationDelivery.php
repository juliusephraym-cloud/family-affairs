<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNotificationDelivery implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function __construct(public int $deliveryId) {}

    public function uniqueId(): string
    {
        return 'delivery-'.$this->deliveryId;
    }

    public function handle(NotificationService $notifications): void
    {
        $notifications->send($this->deliveryId);
    }
}
