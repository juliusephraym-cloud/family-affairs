<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    /** Statuses after which nothing should be (re)sent. */
    public const FINAL = ['delivered', 'read', 'undeliverable', 'skipped'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'variables' => 'encrypted:array',
            'provider_response' => 'array',
            'next_attempt_at' => 'datetime',
            'submitted_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(NotificationJob::class, 'notification_job_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
