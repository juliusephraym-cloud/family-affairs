<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTask extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public const STATUSES = ['todo', 'doing', 'done', 'blocked'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(EventCommittee::class, 'event_committee_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'owner_member_id');
    }

    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->isPast() && $this->status !== 'done';
    }
}
