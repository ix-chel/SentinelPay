<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    use HasUuids;

    protected $fillable = [
        'merchant_id',
        'hashed_key',
        'scopes',
        'rate_limit',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'rate_limit' => 'integer',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
