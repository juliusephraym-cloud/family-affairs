<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerAccount extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'accepts_deposits' => 'boolean'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public static function byCode(string $code): self
    {
        return static::where('code', $code)->firstOrFail();
    }

    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }
}
