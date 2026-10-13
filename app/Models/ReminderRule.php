<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'config' => 'array', 'last_run_at' => 'datetime'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }
}
