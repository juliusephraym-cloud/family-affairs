<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecretoSeason extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return [
            'registration_closes_at' => 'datetime',
            'exchange_at' => 'datetime',
            'drawn_at' => 'datetime',
            'allow_vouchers' => 'boolean',
            'avoid_same_household' => 'boolean',
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(SecretoParticipant::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SecretoAssignment::class);
    }

    public function registrationOpen(): bool
    {
        return $this->status === 'registration'
            && (! $this->registration_closes_at || $this->registration_closes_at->isFuture());
    }
}
