<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Giver -> recipient. The recipient lives only in an encrypted payload
 * (bound to the giver id so payloads cannot be swapped between rows).
 * Never serialise this model to the browser.
 */
class SecretoAssignment extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $hidden = ['recipient_payload'];

    protected function casts(): array
    {
        return ['recipient_payload' => 'encrypted:array'];
    }

    public function giver(): BelongsTo
    {
        return $this->belongsTo(SecretoParticipant::class, 'giver_participant_id');
    }

    public function recipientParticipantId(): ?int
    {
        $p = $this->recipient_payload;

        return ($p['giver'] ?? null) === $this->giver_participant_id ? (int) $p['recipient'] : null;
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'secreto_season_id' => $this->secreto_season_id];
    }
}
