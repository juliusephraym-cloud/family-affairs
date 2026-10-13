<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fund extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function label(): string
    {
        return app()->getLocale() === 'en' ? $this->name_en : $this->name_sw;
    }

    public static function byKey(string $key): self
    {
        return static::where('key', $key)->firstOrFail();
    }
}
