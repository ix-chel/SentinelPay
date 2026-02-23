<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * APPEND-ONLY Model.
 * No update() or delete() calls should ever be made on Ledger.
 * Enforced both here and at the PostgreSQL trigger level.
 */
class Ledger extends Model
{
    protected $table = 'ledgers';

    // BigInt primary key (not UUID)
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'account_id',
        'transaction_id',
        'type',
        'amount',
        'balance_after',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    const TYPE_DEBIT  = 'debit';
    const TYPE_CREDIT = 'credit';

    /**
     * Override update to prevent mutation of ledger entries.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \RuntimeException('Ledger is append-only. Update is not permitted.');
    }

    /**
     * Override delete to prevent mutation of ledger entries.
     */
    public function delete(): bool|null
    {
        throw new \RuntimeException('Ledger is append-only. Delete is not permitted.');
    }

    // Relationships

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
