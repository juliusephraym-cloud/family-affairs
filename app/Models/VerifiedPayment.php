<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VerifiedPayment extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['received_on' => 'date', 'verified_at' => 'datetime', 'reversed_at' => 'datetime', 'is_historical' => 'boolean'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function depositAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'deposit_account_id');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(PaymentSubmission::class, 'payment_submission_id');
    }

    public function allocatedAmount(): int
    {
        return (int) $this->allocations()->where('status', 'active')->sum('amount');
    }
}
