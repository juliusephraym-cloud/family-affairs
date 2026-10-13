<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** One OTP issuance. Only an HMAC of the code is stored; the code itself is never persisted or logged. */
class OtpChallenge extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return ! $this->consumed_at && ! $this->invalidated_at
            && $this->expires_at->isFuture() && $this->attempts < $this->max_attempts;
    }
}
