<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    protected $guarded = ['id'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(VerifiedPayment::class, 'verified_payment_id');
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }
}
