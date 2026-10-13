<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceCase extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public const STAGES = ['warning', 'reminder', 'review', 'decided', 'closed'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
