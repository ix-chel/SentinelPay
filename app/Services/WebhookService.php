<?php

namespace App\Services;

use App\Jobs\DispatchWebhook;
use App\Models\WebhookEndpoint;

class WebhookService
{
    public static function signPayload(array $payload, string $secret): string
    {
        $jsonPayload = json_encode($payload);

        return hash_hmac('sha256', $jsonPayload, $secret);
    }

    public static function dispatch(string $merchantId, string $event, array $payload): void
    {
        $endpoints = WebhookEndpoint::where('merchant_id', $merchantId)
            ->where('status', 'active')
            ->get();

        foreach ($endpoints as $endpoint) {
            $events = $endpoint->events ?? [];
            if (in_array($event, $events) || in_array('*', $events)) {
                DispatchWebhook::dispatch((string) $endpoint->id, $event, $payload)->afterCommit();
            }
        }
    }
}
