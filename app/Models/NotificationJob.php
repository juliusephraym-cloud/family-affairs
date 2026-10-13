<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationJob extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'static_variables' => 'array',
            'scheduled_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'requires_approval' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['draft', 'pending_approval', 'scheduled'], true);
    }
}
