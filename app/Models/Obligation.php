<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Obligation extends Model
{
    public const KINDS = ['monthly_contribution', 'entry_fee', 'welfare_levy', 'penalty', 'other'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fund_splits' => 'array', 'due_date' => 'date', 'is_historical' => 'boolean'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class)->where('status', 'active');
    }

    public function welfareCase(): BelongsTo
    {
        return $this->belongsTo(WelfareCase::class);
    }

    public function scopeOutstanding(Builder $q): Builder
    {
        return $q->whereIn('status', ['open', 'partially_paid']);
    }

    public function paidAmount(): int
    {
        return (int) $this->allocations()->sum('amount');
    }

    public function balance(): int
    {
        return in_array($this->status, ['waived', 'cancelled'], true) ? 0 : max(0, $this->amount - $this->paidAmount());
    }
}
