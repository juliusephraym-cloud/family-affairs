<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributionRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fund_splits' => 'array', 'effective_from' => 'date', 'effective_to' => 'date', 'approved_at' => 'datetime'];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
