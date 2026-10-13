<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Member extends Model
{
    use HasPublicId;

    public const STATUSES = ['invited', 'active', 'suspended', 'resigned', 'archived', 'non_member'];

    /** Statuses that carry monthly contribution obligations. */
    public const CONTRIBUTING = ['active', 'suspended'];

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joined_on' => 'date',
            'is_minor' => 'boolean',
            'is_demo' => 'boolean',
            'whatsapp_opt_in_at' => 'datetime',
            'whatsapp_opt_out_at' => 'datetime',
            'leader_notes' => 'encrypted',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(FamilyRelationship::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(MembershipStatusHistory::class)->latest('created_at');
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class);
    }

    public function verifiedPayments(): HasMany
    {
        return $this->hasMany(VerifiedPayment::class);
    }

    public function paymentSubmissions(): HasMany
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function scopeContributing(Builder $q): Builder
    {
        return $q->whereIn('membership_status', self::CONTRIBUTING);
    }

    public function scopeDirectory(Builder $q): Builder
    {
        return $q->whereIn('membership_status', ['active', 'invited', 'suspended']);
    }

    public function displayName(): string
    {
        return $this->preferred_name ?: $this->full_name;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->full_name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1).mb_substr(end($parts) ?: '', 0, 1));
    }

    /** Can $viewer see this member's birthday (day + month only — never the year)? */
    public function birthdayVisibleTo(?User $viewer): bool
    {
        if (! $this->date_of_birth || ! $viewer) {
            return false;
        }
        if ($viewer->member_id === $this->id || $viewer->hasPermission('members.view_private')) {
            return true;
        }

        // Children's records default to "leaders"; a guardian or leader must
        // explicitly opt a child in to family-wide birthday visibility.
        return $this->birthday_visibility === 'members';
    }

    public function nextBirthday(?Carbon $from = null): ?Carbon
    {
        if (! $this->date_of_birth) {
            return null;
        }
        $from = ($from ?? now())->copy()->startOfDay();
        $month = $this->date_of_birth->month;
        $day = min($this->date_of_birth->day, Carbon::create($from->year, $month, 1)->daysInMonth);
        $next = Carbon::create($from->year, $month, $day)->startOfDay();

        return $next->lt($from) ? $next->addYear() : $next;
    }

    public function wantsCategory(string $category): bool
    {
        if ($this->whatsapp_opt_out_at) {
            return false;
        }
        $pref = $this->notificationPreferences->firstWhere('category', $category);

        return $pref ? $pref->enabled : true;
    }
}
