<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Decision extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'confirmed_at' => 'datetime'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(MeetingAgendaItem::class, 'agenda_item_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }
}
