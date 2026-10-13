<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstitutionVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'approved_at' => 'datetime'];
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
