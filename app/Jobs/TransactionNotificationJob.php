<?php

namespace App\Jobs;

use App\Models\Transfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TransactionNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public readonly string $transactionId) {}

    public function handle(): void
    {
        $transfer = Transfer::with([
            'merchant',
            'sourceAccount',
            'destinationAccount',
        ])->find($this->transactionId);

        if (! $transfer) {
            Log::warning('TransactionNotificationJob: transfer not found, skipping.', [
                'transaction_id' => $this->transactionId,
            ]);

            return;
        }

        Log::info('[Notification] Transfer completed.', [
            'transaction_id' => $transfer->id,
            'merchant_id' => $transfer->merchant_id,
            'amount' => $transfer->amount,
            'currency' => $transfer->currency,
            'source_account_id' => $transfer->source_account_id,
            'destination_account_id' => $transfer->destination_account_id,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[Notification] TransactionNotificationJob permanently failed.', [
            'transaction_id' => $this->transactionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
