<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderWebhookEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'is_valid' => 'boolean', 'created_at' => 'datetime'];
    }

    public const UPDATED_AT = null;
}
