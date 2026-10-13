<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Meeting extends Model
{
    use HasPublicId;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'attendance_required' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(MeetingAgendaItem::class)->orderBy('position');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    public function minutes(): HasOne
    {
        return $this->hasOne(MeetingMinute::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class);
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'organizer_member_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'attachable');
    }
}
