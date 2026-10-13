<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A member's claim that they paid. Has no financial effect until a finance officer verifies it. */
class PaymentSubmission extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function verifiedPayment(): BelongsTo
    {
        return $this->belongsTo(VerifiedPayment::class);
    }
}
