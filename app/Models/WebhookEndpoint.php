<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    use HasUuids;

    protected $fillable = [
        'merchant_id',
        'url',
        'events',
        'secret',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
