<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Records exposed in URLs are addressed by a random ULID, never by the
 * sequential primary key, so ids cannot be guessed or enumerated.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
