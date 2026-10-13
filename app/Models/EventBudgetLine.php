<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventBudgetLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_done' => 'boolean'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(EventCommittee::class, 'event_committee_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'responsible_member_id');
    }
}
