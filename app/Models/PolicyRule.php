<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A family rule value (amount, deadline, count). Business logic only acts on
 * a rule whose status is "confirmed"; source-derived values are shown to
 * leaders with a "needs confirmation" badge until someone authorised confirms.
 */
class PolicyRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'effective_from' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PolicyRuleVersion::class)->latest('created_at');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function scalar(): mixed
    {
        return $this->value['amount'] ?? $this->value['value'] ?? null;
    }

    public function title(): string
    {
        return app()->getLocale() === 'en' ? $this->title_en : $this->title_sw;
    }

    /** Value of a confirmed rule, or null if absent/unconfirmed. Never falls back to an unconfirmed value. */
    public static function confirmedValue(string $key): mixed
    {
        $rule = static::where('key', $key)->first();

        return $rule?->isConfirmed() ? $rule->scalar() : null;
    }
}
