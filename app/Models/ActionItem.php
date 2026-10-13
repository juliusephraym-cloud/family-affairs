<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'completed_at' => 'datetime'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'assignee_member_id');
    }

    public function isOverdue(): bool
    {
        return $this->due_on && $this->due_on->isPast() && ! in_array($this->status, ['done', 'cancelled'], true);
    }
}
