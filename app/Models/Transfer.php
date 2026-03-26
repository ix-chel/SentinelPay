<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfer extends Model
{
    use HasUuids;

    public const STATUS_COMPLETED = 'succeeded';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    protected $fillable = [
        'merchant_id',
        'idempotency_key',
        'source_account_id',
        'destination_account_id',
        'amount',
        'currency',
        'status',
        'signature',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
