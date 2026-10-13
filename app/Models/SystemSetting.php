<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['value' => 'array', 'updated_at' => 'datetime'];
    }

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = null;
}
