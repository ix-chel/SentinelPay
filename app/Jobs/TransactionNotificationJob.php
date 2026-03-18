<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatched after every successful transfer to notify both parties.
 *
 * In production this job would:
 *  - Send an email receipt to the sender
 *  - Send a credit notification to the receiver
 *  - POST to any registered webhooks
 *  - Publish an event to a downstream analytics pipeline
 *
 * By implementing ShouldQueue the job is pushed onto the configured queue
 * driver (RabbitMQ in production, sync in testing) so the HTTP response
 * is returned to the client immediately without waiting for delivery.
 */
class TransactionNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted before being marked failed.
     * RabbitMQ will re-queue the message up to this many times on failure.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying a failed attempt (exponential feel).
     */
    public int $backoff = 10;

    /**
     * @param string $transactionId  The UUID of the completed Transaction record.
     */
    public function __construct(public readonly string $transactionId)
    {
    }

    /**
     * Execute the notification job.
     *
     * Eager-loads sender and receiver accounts with their owning users so a
     * single query covers all data needed for every notification channel.
     */
    public function handle(): void
    {
        $transaction = Transaction::with([
            'sender.user',
            'receiver.user',
        ])->find($this->transactionId);

        if (! $transaction) {
            // The transaction was deleted between dispatch and execution — nothing to do.
            Log::warning('TransactionNotificationJob: transaction not found, skipping.', [
                'transaction_id' => $this->transactionId,
            ]);
            return;
        }

        $senderEmail   = $transaction->sender->user->email   ?? 'unknown';
        $receiverEmail = $transaction->receiver->user->email ?? 'unknown';

        // ── Sender receipt ─────────────────────────────────────────────────────
        Log::info('[Notification] Transfer debit receipt dispatched to sender.', [
            'transaction_id'  => $transaction->id,
            'recipient_email' => $senderEmail,
            'amount'          => $transaction->amount,
            'currency'        => $transaction->currency,
            'new_balance'     => $transaction->sender->balance,
        ]);

        // ── Receiver credit alert ──────────────────────────────────────────────
        Log::info('[Notification] Incoming credit alert dispatched to receiver.', [
            'transaction_id'  => $transaction->id,
            'recipient_email' => $receiverEmail,
            'amount'          => $transaction->amount,
            'currency'        => $transaction->currency,
            'new_balance'     => $transaction->receiver->balance,
        ]);

        // ── Placeholder: webhook / analytics pipeline ──────────────────────────
        // WebhookDispatcher::send($transaction);
        // AnalyticsPipeline::record('transfer.completed', $transaction->toArray());
    }

    /**
     * Handle a job that has exceeded its maximum number of attempts.
     * Logs the failure so it surfaces in monitoring dashboards.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[Notification] TransactionNotificationJob permanently failed.', [
            'transaction_id' => $this->transactionId,
            'error'          => $exception->getMessage(),
        ]);
    }
}
