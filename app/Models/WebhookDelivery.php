<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use HasUuids;

    protected $fillable = [
        'webhook_endpoint_id',
        'event',
        'request_payload',
        'response_status',
        'response_headers',
        'response_body',
        'successful',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_headers' => 'array',
            'response_status' => 'integer',
            'successful' => 'boolean',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
