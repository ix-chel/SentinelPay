<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;

    protected $table = 'transactions';

    protected $fillable = [
        'idempotency_key',
        'sender_id',
        'receiver_id',
        'amount',
        'status',
        'currency',
        'signature',
        'failure_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_FAILED     = 'failed';
    const STATUS_REVERSED   = 'reversed';

    // Relationships

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receiver_id');
    }

    public function ledgerEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Ledger::class, 'transaction_id');
    }
}
