<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    protected $guarded = ['id'];

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
