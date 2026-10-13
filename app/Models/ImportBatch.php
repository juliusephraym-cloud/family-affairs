<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['summary' => 'array'];
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(ImportCandidate::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
