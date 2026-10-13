<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingAgendaItem extends Model
{
    protected $guarded = ['id'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class, 'agenda_item_id');
    }
}
