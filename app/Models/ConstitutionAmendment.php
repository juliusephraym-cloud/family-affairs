<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstitutionAmendment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }
}
