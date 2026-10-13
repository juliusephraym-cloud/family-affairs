<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasPublicId;

    public const CATEGORIES = ['event', 'welfare', 'bereavement', 'charity', 'investment', 'admin', 'gift', 'other'];

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'paid_on' => 'date', 'approved_at' => 'datetime'];
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paidFrom(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'paid_from_account_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
