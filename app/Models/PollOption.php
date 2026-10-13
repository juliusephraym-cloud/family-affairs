<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollOption extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }
}
