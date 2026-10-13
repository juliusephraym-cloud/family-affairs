<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WelfareDisbursement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['paid_on' => 'date'];
    }

    public function welfareCase(): BelongsTo
    {
        return $this->belongsTo(WelfareCase::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
