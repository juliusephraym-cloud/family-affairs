<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Announcement extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'is_important' => 'boolean', 'is_pinned' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->where('status', 'visible')->oldest();
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->hasPermission('community.moderate')) {
            return $q;
        }

        return $q->where('status', 'published')->where(fn ($w) => $w->where('visibility', 'members')
            ->when($user->isLeader(), fn ($l) => $l->orWhere('visibility', 'leaders')));
    }
}
