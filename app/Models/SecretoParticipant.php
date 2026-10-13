<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecretoParticipant extends Model
{
    protected $guarded = ['id'];

    public function season(): BelongsTo
    {
        return $this->belongsTo(SecretoSeason::class, 'secreto_season_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
