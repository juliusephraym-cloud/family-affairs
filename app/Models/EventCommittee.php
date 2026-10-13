<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCommittee extends Model
{
    protected $guarded = ['id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'lead_member_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'event_committee_member');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(EventTask::class);
    }
}
