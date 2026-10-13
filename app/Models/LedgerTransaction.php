<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Created only through LedgerService::post(). Once posted, the only permitted
 * change is status posted -> reversed (with a linked reversing transaction).
 */
class LedgerTransaction extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['txn_date' => 'date'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_transaction_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_transaction_id');
    }

    public function total(): int
    {
        return (int) $this->entries->sum('debit');
    }

    protected static function booted(): void
    {
        static::updating(function (self $txn) {
            $dirty = array_keys($txn->getDirty());
            if (array_diff($dirty, ['status', 'updated_at']) || $txn->getOriginal('status') !== 'posted' || $txn->status !== 'reversed') {
                throw new LogicException('Posted ledger transactions are immutable; post a reversal instead.');
            }
        });
        static::deleting(fn () => throw new LogicException('Ledger transactions cannot be deleted.'));
    }
}
