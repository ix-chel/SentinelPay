<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DispatchWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [10, 30, 60];

    protected $endpointId;

    protected $event;

    protected $payload;

    public function __construct(string $endpointId, string $event, array $payload)
    {
        $this->endpointId = $endpointId;
        $this->event = $event;
        $this->payload = $payload;
    }

    public function handle(): void
    {
        $endpoint = WebhookEndpoint::find($this->endpointId);

        if (! $endpoint || $endpoint->status !== 'active') {
            return;
        }

        $fullPayload = [
            'id' => uniqid('evt_'),
            'type' => $this->event,
            'created_at' => now()->toIso8601String(),
            'data' => $this->payload,
        ];

        $signature = WebhookService::signPayload($fullPayload, $endpoint->secret);

        $response = Http::timeout(10)->withHeaders([
            'Stripe-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($endpoint->url, $fullPayload);

        WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => $this->event,
            'request_payload' => $fullPayload,
            'response_status' => $response->status(),
            'response_headers' => $response->headers(),
            'response_body' => $response->body(),
            'successful' => $response->successful(),
            'created_at' => now(),
        ]);

        if ($response->failed()) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 60);
        }
    }
}
