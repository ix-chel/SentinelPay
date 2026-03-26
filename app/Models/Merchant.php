<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'name',
        'email',
    ];

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }
}
