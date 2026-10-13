<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WelfareCase extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return [
            'private_details' => 'encrypted',
            'policy_snapshot' => 'array',
            'contribution_deadline' => 'date',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'beneficiary_member_id');
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class);
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(WelfareDisbursement::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** Cases a given user may see at all. Private cases: welfare managers and the beneficiary only. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->hasPermission('welfare.view_private')) {
            return $q;
        }

        return $q->where(function (Builder $w) use ($user) {
            $w->where(fn ($m) => $m->where('visibility', 'members')->whereNotIn('status', ['submitted', 'rejected']))
                ->orWhere('beneficiary_member_id', $user->member_id)
                ->orWhere('submitted_by', $user->id);
            if ($user->isLeader()) {
                $w->orWhere(fn ($m) => $m->where('visibility', 'leaders')->whereNotIn('status', ['rejected']));
            }
        });
    }
}
