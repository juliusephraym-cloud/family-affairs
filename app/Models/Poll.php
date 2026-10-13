<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime', 'allow_multiple' => 'boolean', 'is_anonymous' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('sort');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function isOpen(): bool
    {
        return $this->opens_at->isPast() && $this->closes_at->isFuture();
    }

    public function isEligible(User $user): bool
    {
        return $this->eligibility === 'members' || $user->isLeader();
    }

    public function hasVoted(User $user): bool
    {
        return $this->votes->contains('user_id', $user->id);
    }

    public function resultsVisibleTo(User $user): bool
    {
        if ($user->hasPermission('community.moderate')) {
            return true;
        }

        return match ($this->result_visibility) {
            'always' => true,
            'after_vote' => $this->hasVoted($user) || ! $this->isOpen(),
            'after_close' => $this->closes_at->isPast(),
            default => false,
        };
    }
}
