<?php

namespace App\Services;

use App\Exceptions\AccountInactiveException;
use App\Exceptions\AccountNotFoundException;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientFundsException;
use App\Jobs\TransactionNotificationJob;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransferService
{
    /**
     * Cache TTL for idempotency keys (24 hours).
     */
    private const IDEMPOTENCY_TTL_SECONDS = 86400;

    /**
     * Execute a funds transfer with:
     *  1. PostgreSQL-backed idempotency verification (Cache acceleration + DB uniqueness)
     *  2. PostgreSQL pessimistic locking (eliminates race conditions / overdrafts)
     *  3. Append-only ledger entries (immutable audit trail)
     *  4. Async notification dispatch (sync in local dev/tests, broker in multi-node)
     *
     * @throws \App\Exceptions\InsufficientFundsException
     * @throws \App\Exceptions\AccountInactiveException
     * @throws \App\Exceptions\AccountNotFoundException
     * @throws \InvalidArgumentException
     */
    public function transfer(
        string $senderAccountId,
        string $receiverAccountId,
        string $amount,
        string $currency,
        string $idempotencyKey,
        string $signature,
    ): Transaction {
        // ──────────────────────────────────────────────────────────────────────
        // Step 1: Idempotency Check (Fast-path Cache + PostgreSQL Source of Truth)
        //
        // Invariant: same idempotency key → same transaction → never double-charge.
        // PostgreSQL's unique constraint on `idempotency_key` is the authoritative
        // source of truth. The cache layer (Redis in cluster, file/array locally)
        // serves purely as an acceleration read-path.
        // ──────────────────────────────────────────────────────────────────────
        $payloadHash = $this->calculatePayloadFingerprint(
            $senderAccountId,
            $receiverAccountId,
            $amount,
            $currency,
        );

        $cacheKey = "idempotency:{$idempotencyKey}";

        $cachedTransactionId = Cache::get($cacheKey);

        if ($cachedTransactionId !== null) {
            $transaction = Transaction::find($cachedTransactionId);

            if ($transaction) {
                $existingHash = $transaction->payload_hash ?? $this->calculatePayloadFingerprint(
                    $transaction->sender_id,
                    $transaction->receiver_id,
                    (string) $transaction->amount,
                    $transaction->currency,
                );

                if ($existingHash !== $payloadHash) {
                    Log::warning(
                        "[Transfer] Idempotency conflict detected in cache — payload mismatch.",
                        [
                            "idempotency_key" => $idempotencyKey,
                            "cached_transaction_id" => $transaction->id,
                        ],
                    );
                    throw new IdempotencyConflictException(
                        "Idempotency key '{$idempotencyKey}' has already been used with a different request payload.",
                    );
                }

                Log::info(
                    "[Transfer] Idempotency cache hit — returning existing transaction.",
                    [
                        "idempotency_key" => $idempotencyKey,
                        "transaction_id" => $cachedTransactionId,
                    ],
                );

                return $transaction;
            }

            Log::warning(
                "[Transfer] Idempotency cache pointed to a missing transaction — checking database.",
                [
                    "idempotency_key" => $idempotencyKey,
                    "cached_id" => $cachedTransactionId,
                ],
            );
            Cache::forget($cacheKey);
        }

        // Database source-of-truth check for idempotency
        $existingTransaction = Transaction::where("idempotency_key", $idempotencyKey)->first();
        if ($existingTransaction) {
            $existingHash = $existingTransaction->payload_hash ?? $this->calculatePayloadFingerprint(
                $existingTransaction->sender_id,
                $existingTransaction->receiver_id,
                (string) $existingTransaction->amount,
                $existingTransaction->currency,
            );

            if ($existingHash !== $payloadHash) {
                Log::warning(
                    "[Transfer] Idempotency conflict detected in database — payload mismatch.",
                    [
                        "idempotency_key" => $idempotencyKey,
                        "transaction_id" => $existingTransaction->id,
                    ],
                );
                throw new IdempotencyConflictException(
                    "Idempotency key '{$idempotencyKey}' has already been used with a different request payload.",
                );
            }

            Log::info(
                "[Transfer] PostgreSQL idempotency hit — returning existing transaction.",
                [
                    "idempotency_key" => $idempotencyKey,
                    "transaction_id" => $existingTransaction->id,
                ],
            );
            Cache::put($cacheKey, $existingTransaction->id, self::IDEMPOTENCY_TTL_SECONDS);
            return $existingTransaction;
        }

        // ──────────────────────────────────────────────────────────────────────
        // Step 2: Pessimistic Locking inside a DB Transaction
        //
        // TOCTOU note: two requests with the same idempotency_key can both miss
        // the Redis cache above if they arrive simultaneously before either has
        // committed. The UniqueConstraintViolationException catch block below
        // handles this race by re-fetching the winner's committed record instead
        // of surfacing a 500 to the client.
        // ──────────────────────────────────────────────────────────────────────
        try {
            $transaction = DB::transaction(function () use (
                $senderAccountId,
                $receiverAccountId,
                $amount,
                $currency,
                $idempotencyKey,
                $signature,
                $payloadHash,
            ): Transaction {
                // Sort both UUIDs before locking to guarantee a consistent
                // acquisition order regardless of which direction the transfer
                // flows. Without this, Transfer A (alice→bob) and Transfer B
                // (bob→alice) running concurrently would deadlock each other.
                $lockIds = [$senderAccountId, $receiverAccountId];
                sort($lockIds);

                // SELECT … FOR UPDATE acquires an exclusive row-level lock on
                // both accounts until this transaction commits or rolls back.
                // Any other transaction trying to touch these rows will block.
                $accounts = Account::whereIn("id", $lockIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy("id");

                $sender = $accounts->get($senderAccountId);
                $receiver = $accounts->get($receiverAccountId);

                // ── Validate accounts ──────────────────────────────────────
                if (!$sender || !$receiver) {
                    throw new AccountNotFoundException(
                        "One or more accounts not found.",
                    );
                }

                if (!$sender->is_active || !$receiver->is_active) {
                    throw new AccountInactiveException(
                        "One or more accounts are inactive.",
                    );
                }

                // ── Validate currency match ────────────────────────────────
                if (
                    $sender->currency !== $currency ||
                    $receiver->currency !== $currency
                ) {
                    throw new \InvalidArgumentException(
                        "Currency mismatch. Account currency must be {$currency}.",
                    );
                }

                // ── Validate sufficient funds ──────────────────────────────
                $amountDecimal = bcadd($amount, "0", 2);

                if (bccomp($sender->balance, $amountDecimal, 2) < 0) {
                    throw new InsufficientFundsException(
                        "Insufficient funds. Available: {$sender->balance}, Requested: {$amountDecimal}",
                    );
                }

                // ── Create Transaction record ──────────────────────────────
                // Inserted as STATUS_PROCESSING so a crash after this point
                // leaves an identifiable record for reconciliation rather than
                // silently disappearing.
                $transaction = Transaction::create([
                    "idempotency_key" => $idempotencyKey,
                    "payload_hash" => $payloadHash,
                    "sender_id" => $senderAccountId,
                    "receiver_id" => $receiverAccountId,
                    "amount" => $amountDecimal,
                    "status" => Transaction::STATUS_PROCESSING,
                    "currency" => $currency,
                    "signature" => $signature,
                ]);

                // ── Debit sender ───────────────────────────────────────────
                $newSenderBalance = bcsub($sender->balance, $amountDecimal, 2);
                $sender->balance = $newSenderBalance;
                $sender->save();

                Ledger::create([
                    "account_id" => $sender->id,
                    "transaction_id" => $transaction->id,
                    "type" => Ledger::TYPE_DEBIT,
                    "amount" => $amountDecimal,
                    "balance_after" => $newSenderBalance,
                ]);

                // ── Credit receiver ────────────────────────────────────────
                $newReceiverBalance = bcadd(
                    $receiver->balance,
                    $amountDecimal,
                    2,
                );
                $receiver->balance = $newReceiverBalance;
                $receiver->save();

                Ledger::create([
                    "account_id" => $receiver->id,
                    "transaction_id" => $transaction->id,
                    "type" => Ledger::TYPE_CREDIT,
                    "amount" => $amountDecimal,
                    "balance_after" => $newReceiverBalance,
                ]);

                // ── Mark transaction completed ─────────────────────────────
                $transaction->status = Transaction::STATUS_COMPLETED;
                $transaction->save();

                Log::info(
                    "[Transfer] Transfer completed successfully inside DB transaction.",
                    [
                        "transaction_id" => $transaction->id,
                        "idempotency_key" => $idempotencyKey,
                        "amount" => $amountDecimal,
                        "currency" => $currency,
                        "sender_id" => $senderAccountId,
                        "receiver_id" => $receiverAccountId,
                    ],
                );

                return $transaction;
            });
        } catch (UniqueConstraintViolationException $e) {
            // ── TOCTOU resolution ──────────────────────────────────────────
            //
            // Two concurrent requests both missed the Redis cache above and
            // both attempted to INSERT a Transaction row with the same
            // idempotency_key. PostgreSQL's UNIQUE constraint let exactly one
            // commit; the other raised this exception.
            //
            // Instead of propagating a 500, we re-fetch the winner's committed
            // record and behave as if we had found it in the cache from the start.
            Log::info(
                "[Transfer] TOCTOU idempotency race detected — re-fetching committed transaction.",
                [
                    "idempotency_key" => $idempotencyKey,
                ],
            );

            $transaction = Transaction::where(
                "idempotency_key",
                $idempotencyKey,
            )->firstOrFail();

            $existingHash = $transaction->payload_hash ?? $this->calculatePayloadFingerprint(
                $transaction->sender_id,
                $transaction->receiver_id,
                (string) $transaction->amount,
                $transaction->currency,
            );

            if ($existingHash !== $payloadHash) {
                Log::warning(
                    "[Transfer] Idempotency conflict detected in TOCTOU race — payload mismatch.",
                    [
                        "idempotency_key" => $idempotencyKey,
                        "transaction_id" => $transaction->id,
                    ],
                );
                throw new IdempotencyConflictException(
                    "Idempotency key '{$idempotencyKey}' has already been used with a different request payload.",
                );
            }

            // Warm the cache so subsequent requests skip this path entirely.
            Cache::put(
                $cacheKey,
                $transaction->id,
                self::IDEMPOTENCY_TTL_SECONDS,
            );

            return $transaction;
        }

        // ──────────────────────────────────────────────────────────────────────
        // Step 3: Cache idempotency key → transaction ID
        //
        // Stored AFTER the DB transaction commits so that if the app crashes
        // between commit and here, the next request falls through to the DB
        // path rather than caching a non-existent ID.
        // ──────────────────────────────────────────────────────────────────────
        Cache::put($cacheKey, $transaction->id, self::IDEMPOTENCY_TTL_SECONDS);

        // ──────────────────────────────────────────────────────────────────────
        // Step 4: Dispatch async notification
        //
        // Pushed onto the queue AFTER caching so the notification always refers
        // to a committed, cacheable transaction. In production the job is routed
        // to RabbitMQ; in the test environment QUEUE_CONNECTION=sync means it
        // runs inline so tests remain deterministic without a broker.
        // ──────────────────────────────────────────────────────────────────────
        TransactionNotificationJob::dispatch($transaction->id);

        return $transaction;
    }

    /**
     * Compute a deterministic SHA-256 fingerprint for financially relevant transfer parameters.
     *
     * Canonicalization rules:
     * - amount: normalized to 2 decimal places using bcadd($amount, '0', 2)
     * - currency: uppercase and trimmed
     * - sender_id and receiver_id: string UUIDs
     * - keys sorted alphabetically to guarantee stable JSON representation
     */
    public function calculatePayloadFingerprint(
        string $senderAccountId,
        string $receiverAccountId,
        string $amount,
        string $currency,
    ): string {
        $normalizedAmount = bcadd($amount, "0", 2);
        $normalizedCurrency = strtoupper(trim($currency));

        $payload = [
            "amount" => $normalizedAmount,
            "currency" => $normalizedCurrency,
            "receiver_id" => $receiverAccountId,
            "sender_id" => $senderAccountId,
        ];
        ksort($payload);

        $canonicalString = json_encode($payload, JSON_THROW_ON_ERROR);

        return hash("sha256", $canonicalString);
    }
}
