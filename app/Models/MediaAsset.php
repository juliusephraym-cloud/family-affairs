<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAsset extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['path', 'thumb_path'];

    protected function casts(): array
    {
        return ['taken_on' => 'date', 'allow_download' => 'boolean'];
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
