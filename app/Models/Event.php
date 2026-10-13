<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasPublicId;

    public const TYPES = ['gathering', 'birthday', 'anniversary', 'milestone', 'celebration', 'easter', 'bereavement', 'charity', 'other'];

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_demo' => 'boolean'];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'organizer_member_id');
    }

    public function committees(): HasMany
    {
        return $this->hasMany(EventCommittee::class)->orderBy('sort');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(EventTask::class)->orderBy('sort');
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(EventBudgetLine::class);
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    public function invitees(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'event_invitees');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->hasPermission('events.manage')) {
            return $q;
        }

        return $q->where(function (Builder $w) use ($user) {
            $w->where('visibility', 'members')
                ->orWhere('organizer_member_id', $user->member_id)
                ->orWhere(fn ($i) => $i->where('visibility', 'invited')->whereHas('invitees', fn ($m) => $m->where('members.id', $user->member_id)));
            if ($user->isLeader()) {
                $w->orWhere('visibility', 'leaders');
            }
        });
    }

    public function readiness(): ?int
    {
        $total = $this->tasks->count();

        return $total ? (int) round($this->tasks->where('status', 'done')->count() / $total * 100) : null;
    }
}
