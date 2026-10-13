<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportCandidate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'decided_at' => 'datetime'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function proposedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'proposed_member_id');
    }
}
