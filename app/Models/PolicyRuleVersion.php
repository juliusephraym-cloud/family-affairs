<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyRuleVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['value' => 'array', 'created_at' => 'datetime'];
    }

    public const UPDATED_AT = null;

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
