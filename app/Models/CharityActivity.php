<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CharityActivity extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['planned_for' => 'datetime', 'is_demo' => 'boolean'];
    }

    use HasPublicId;

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'coordinator_member_id');
    }

    public function volunteers(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'charity_volunteers')->withPivot('attended')->withTimestamps();
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
