<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransferService
{
    /**
     * The Redis TTL for idempotency keys (24 hours).
     * After this period, a repeated key will be processed again.
     */
    private const IDEMPOTENCY_TTL_SECONDS = 86400;

    /**
     * Execute a funds transfer with:
     *  1. Redis-based idempotency check (prevents double-charging)
     *  2. PostgreSQL pessimistic locking (prevents race conditions)
     *  3. Append-only ledger entries (immutable audit trail)
     *
     * @throws \App\Exceptions\InsufficientFundsException
     * @throws \App\Exceptions\DuplicateTransactionException
     * @throws \App\Exceptions\AccountInactiveException
     */
    public function transfer(
        string $senderAccountId,
        string $receiverAccountId,
        string $amount,
        string $currency,
        string $idempotencyKey,
        string $signature
    ): Transaction {
        // ──────────────────────────────────────────────────────────
        // Step 1: Idempotency Check (Redis)
        // ──────────────────────────────────────────────────────────
        $cacheKey = "idempotency:{$idempotencyKey}";

        $cachedTransactionId = Cache::get($cacheKey);
        if ($cachedTransactionId !== null) {
            Log::info('Idempotency hit — returning cached transaction', [
                'idempotency_key'  => $idempotencyKey,
                'transaction_id'   => $cachedTransactionId,
            ]);

            $transaction = Transaction::find($cachedTransactionId);
            if ($transaction) {
                return $transaction;
            }
        }

        // ──────────────────────────────────────────────────────────
        // Step 2: Pessimistic Locking inside a DB Transaction
        // ──────────────────────────────────────────────────────────
        $transaction = DB::transaction(function () use (
            $senderAccountId,
            $receiverAccountId,
            $amount,
            $currency,
            $idempotencyKey,
            $signature
        ) {
            // Lock both accounts in a consistent order (lower UUID first)
            // to prevent deadlocks when two concurrent transfers involve the same pair.
            $lockIds = [$senderAccountId, $receiverAccountId];
            sort($lockIds);

            /**
             * lockForUpdate() issues SELECT ... FOR UPDATE in PostgreSQL.
             * This acquires an exclusive row-level lock, preventing any other
             * transaction from reading or writing these rows until we commit.
             */
            $accounts = Account::whereIn('id', $lockIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $sender   = $accounts->get($senderAccountId);
            $receiver = $accounts->get($receiverAccountId);

            // Validate accounts exist and are active
            if (! $sender || ! $receiver) {
                throw new \App\Exceptions\AccountNotFoundException('One or more accounts not found.');
            }

            if (! $sender->is_active || ! $receiver->is_active) {
                throw new \App\Exceptions\AccountInactiveException('One or more accounts are inactive.');
            }

            // Validate currency match
            if ($sender->currency !== $currency || $receiver->currency !== $currency) {
                throw new \InvalidArgumentException("Currency mismatch. Account currency must be {$currency}.");
            }

            // Validate sufficient funds
            $amountDecimal = bcadd($amount, '0', 2);
            if (bccomp($sender->balance, $amountDecimal, 2) < 0) {
                throw new \App\Exceptions\InsufficientFundsException(
                    "Insufficient funds. Available: {$sender->balance}, Requested: {$amountDecimal}"
                );
            }

            // ── Create Transaction record ──────────────────────────
            $transaction = Transaction::create([
                'idempotency_key' => $idempotencyKey,
                'sender_id'       => $senderAccountId,
                'receiver_id'     => $receiverAccountId,
                'amount'          => $amountDecimal,
                'status'          => Transaction::STATUS_PROCESSING,
                'currency'        => $currency,
                'signature'       => $signature,
            ]);

            // ── Debit sender ───────────────────────────────────────
            $newSenderBalance = bcsub($sender->balance, $amountDecimal, 2);
            $sender->balance  = $newSenderBalance;
            $sender->save();

            Ledger::create([
                'account_id'     => $sender->id,
                'transaction_id' => $transaction->id,
                'type'           => Ledger::TYPE_DEBIT,
                'amount'         => $amountDecimal,
                'balance_after'  => $newSenderBalance,
            ]);

            // ── Credit receiver ────────────────────────────────────
            $newReceiverBalance = bcadd($receiver->balance, $amountDecimal, 2);
            $receiver->balance  = $newReceiverBalance;
            $receiver->save();

            Ledger::create([
                'account_id'     => $receiver->id,
                'transaction_id' => $transaction->id,
                'type'           => Ledger::TYPE_CREDIT,
                'amount'         => $amountDecimal,
                'balance_after'  => $newReceiverBalance,
            ]);

            // ── Mark transaction as completed ──────────────────────
            $transaction->status = Transaction::STATUS_COMPLETED;
            $transaction->save();

            return $transaction;
        });

        // ──────────────────────────────────────────────────────────
        // Step 3: Cache idempotency key → transaction ID in Redis
        // ──────────────────────────────────────────────────────────
        Cache::put($cacheKey, $transaction->id, self::IDEMPOTENCY_TTL_SECONDS);

        return $transaction;
    }
}
