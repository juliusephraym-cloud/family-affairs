<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['is_annual_portrait' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function media(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function visibleMedia(): HasMany
    {
        return $this->hasMany(MediaAsset::class)->where('status', 'visible')->latest();
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->hasPermission('memories.manage') || $user->isLeader() ? $q : $q->where('visibility', 'members');
    }
}
