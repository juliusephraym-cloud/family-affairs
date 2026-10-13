<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
