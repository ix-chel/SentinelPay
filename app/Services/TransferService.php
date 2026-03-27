<?php

namespace App\Services;

use App\Exceptions\AccountInactiveException;
use App\Exceptions\AccountNotFoundException;
use App\Exceptions\IdempotencyRequestInProgressException;
use App\Exceptions\InsufficientFundsException;
use App\Models\Account;
use App\Models\IdempotencyKey;
use App\Models\LedgerEntry;
use App\Models\Transfer;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TransferService
{
    private const IDEMPOTENCY_TTL_SECONDS = 86400;

    private const IDEMPOTENCY_LOCK_SECONDS = 10;

    private const IDEMPOTENCY_LOCK_WAIT_SECONDS = 2;

    /**
     * @return array{response: array{transfer: array<string, mixed>, status: string}, responseCode: int, transfer: Transfer}
     */
    public function transfer(
        string $merchantId,
        string $senderAccountId,
        string $receiverAccountId,
        string $amount,
        string $currency,
        string $idempotencyKey,
        ?string $signature = null,
        string $requestPath = 'api/v1/transfers',
    ): array {
        $cacheKey = $this->cacheKey($merchantId, $idempotencyKey);
        $cachedTransferId = Cache::get($cacheKey);

        if (is_string($cachedTransferId)) {
            $cachedTransfer = $this->findTransfer($merchantId, $idempotencyKey, $cachedTransferId);
            if ($cachedTransfer !== null) {
                return $this->responsePayload($cachedTransfer);
            }

            Cache::forget($cacheKey);
        }

        try {
            return $this->lockStore()->lock($this->lockKey($merchantId, $idempotencyKey), self::IDEMPOTENCY_LOCK_SECONDS)
                ->block(self::IDEMPOTENCY_LOCK_WAIT_SECONDS, function () use (
                    $merchantId,
                    $senderAccountId,
                    $receiverAccountId,
                    $amount,
                    $currency,
                    $idempotencyKey,
                    $signature,
                    $requestPath,
                    $cacheKey,
                ): array {
                    $storedResponse = $this->resolveStoredResponse($merchantId, $idempotencyKey);
                    if ($storedResponse !== null) {
                        Cache::put($cacheKey, $storedResponse['transfer']->id, now()->addSeconds(self::IDEMPOTENCY_TTL_SECONDS));

                        return $storedResponse;
                    }

                    try {
                        $transferId = DB::transaction(function () use (
                            $merchantId,
                            $senderAccountId,
                            $receiverAccountId,
                            $amount,
                            $currency,
                            $idempotencyKey,
                            $signature,
                        ): string {
                            if ($senderAccountId === $receiverAccountId) {
                                throw new InvalidArgumentException('Source and destination accounts must be different.');
                            }

                            $lockIds = [$senderAccountId, $receiverAccountId];
                            sort($lockIds);

                            $accounts = Account::query()
                                ->where('merchant_id', $merchantId)
                                ->whereIn('id', $lockIds)
                                ->lockForUpdate()
                                ->get()
                                ->keyBy('id');

                            /** @var Account|null $sender */
                            $sender = $accounts->get($senderAccountId);
                            /** @var Account|null $receiver */
                            $receiver = $accounts->get($receiverAccountId);

                            if (! $sender || ! $receiver) {
                                throw new AccountNotFoundException('One or more accounts not found.');
                            }

                            if (! $sender->is_active || ! $receiver->is_active) {
                                throw new AccountInactiveException('One or more accounts are inactive.');
                            }

                            if ($sender->currency !== $currency || $receiver->currency !== $currency) {
                                throw new InvalidArgumentException("Currency mismatch. Account currency must be {$currency}.");
                            }

                            $amountDecimal = bcadd($amount, '0', 2);

                            if (bccomp((string) $sender->balance, $amountDecimal, 2) < 0) {
                                throw new InsufficientFundsException(
                                    "Insufficient funds. Available: {$sender->balance}, Requested: {$amountDecimal}",
                                );
                            }

                            $transfer = Transfer::create([
                                'merchant_id' => $merchantId,
                                'idempotency_key' => $idempotencyKey,
                                'source_account_id' => $senderAccountId,
                                'destination_account_id' => $receiverAccountId,
                                'amount' => $amountDecimal,
                                'currency' => $currency,
                                'status' => Transfer::STATUS_PROCESSING,
                                'signature' => $signature,
                            ]);

                            $newSenderBalance = bcsub((string) $sender->balance, $amountDecimal, 2);
                            $newReceiverBalance = bcadd((string) $receiver->balance, $amountDecimal, 2);

                            $sender->forceFill(['balance' => $newSenderBalance])->save();
                            $receiver->forceFill(['balance' => $newReceiverBalance])->save();

                            $timestamp = now();

                            LedgerEntry::query()->insert([
                                [
                                    'id' => (string) Str::uuid(),
                                    'transfer_id' => $transfer->id,
                                    'account_id' => $sender->id,
                                    'type' => LedgerEntry::TYPE_DEBIT,
                                    'amount' => $amountDecimal,
                                    'balance_after' => $newSenderBalance,
                                    'currency' => $currency,
                                    'created_at' => $timestamp,
                                    'updated_at' => $timestamp,
                                ],
                                [
                                    'id' => (string) Str::uuid(),
                                    'transfer_id' => $transfer->id,
                                    'account_id' => $receiver->id,
                                    'type' => LedgerEntry::TYPE_CREDIT,
                                    'amount' => $amountDecimal,
                                    'balance_after' => $newReceiverBalance,
                                    'currency' => $currency,
                                    'created_at' => $timestamp,
                                    'updated_at' => $timestamp,
                                ],
                            ]);

                            $transfer->forceFill(['status' => Transfer::STATUS_COMPLETED])->save();

                            return (string) $transfer->id;
                        }, 5);
                    } catch (UniqueConstraintViolationException) {
                        $existingTransfer = $this->findTransfer($merchantId, $idempotencyKey);
                        if ($existingTransfer === null) {
                            throw new IdempotencyRequestInProgressException('A request with the same Idempotency-Key is currently being processed.');
                        }

                        $response = $this->persistAndBuildResponse($merchantId, $idempotencyKey, $requestPath, $existingTransfer);
                        Cache::put($cacheKey, $existingTransfer->id, now()->addSeconds(self::IDEMPOTENCY_TTL_SECONDS));

                        return $response;
                    }

                    $transfer = $this->findTransfer($merchantId, $idempotencyKey, $transferId);
                    if ($transfer === null) {
                        throw new IdempotencyRequestInProgressException('Transfer commit completed but the response is not yet available.');
                    }

                    $response = $this->persistAndBuildResponse($merchantId, $idempotencyKey, $requestPath, $transfer);
                    Cache::put($cacheKey, $transfer->id, now()->addSeconds(self::IDEMPOTENCY_TTL_SECONDS));

                    WebhookService::dispatch($merchantId, 'transfer.succeeded', $transfer->toArray());

                    return $response;
                });
        } catch (LockTimeoutException) {
            $storedResponse = $this->resolveStoredResponse($merchantId, $idempotencyKey);
            if ($storedResponse !== null) {
                Cache::put($cacheKey, $storedResponse['transfer']->id, now()->addSeconds(self::IDEMPOTENCY_TTL_SECONDS));

                return $storedResponse;
            }

            throw new IdempotencyRequestInProgressException('A request with the same Idempotency-Key is currently being processed.');
        }
    }

    private function cacheKey(string $merchantId, string $idempotencyKey): string
    {
        return "transfer:{$merchantId}:{$idempotencyKey}";
    }

    private function lockKey(string $merchantId, string $idempotencyKey): string
    {
        return "transfer-lock:{$merchantId}:{$idempotencyKey}";
    }

    private function lockStore(): Repository
    {
        $defaultStore = (string) config('cache.default', 'array');

        return Cache::store($defaultStore === 'redis' ? 'redis' : $defaultStore);
    }

    private function findTransfer(string $merchantId, string $idempotencyKey, ?string $transferId = null): ?Transfer
    {
        $query = Transfer::query()
            ->where('merchant_id', $merchantId)
            ->where('idempotency_key', $idempotencyKey);

        if ($transferId !== null) {
            $query->whereKey($transferId);
        }

        /** @var Transfer|null $transfer */
        $transfer = $query
            ->with(['merchant', 'sourceAccount', 'destinationAccount'])
            ->first();

        return $transfer;
    }

    /**
     * @return array{response: array{transfer: array<string, mixed>, status: string}, responseCode: int, transfer: Transfer}|null
     */
    private function resolveStoredResponse(
        string $merchantId,
        string $idempotencyKey,
        bool $requireExisting = false,
    ): ?array {
        $stored = IdempotencyKey::query()
            ->where('merchant_id', $merchantId)
            ->where('idempotency_key', $idempotencyKey)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        $transfer = $this->findTransfer($merchantId, $idempotencyKey);

        if (! $stored) {
            if ($requireExisting && $transfer !== null) {
                return $this->responsePayload($transfer);
            }

            return null;
        }

        if ($transfer === null) {
            return null;
        }

        return [
            'response' => $stored->response_body,
            'responseCode' => $stored->response_code,
            'transfer' => $transfer,
        ];
    }

    /**
     * @return array{response: array{transfer: array<string, mixed>, status: string}, responseCode: int, transfer: Transfer}
     */
    private function persistAndBuildResponse(
        string $merchantId,
        string $idempotencyKey,
        string $requestPath,
        Transfer $transfer,
    ): array {
        $response = $this->responsePayload($transfer);

        IdempotencyKey::query()->updateOrCreate(
            [
                'merchant_id' => $merchantId,
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'request_path' => $requestPath,
                'response_code' => $response['responseCode'],
                'response_body' => $response['response'],
                'expires_at' => now()->addHours(24),
            ],
        );

        return $response;
    }

    /**
     * @return array{response: array{transfer: array<string, mixed>, status: string}, responseCode: int, transfer: Transfer}
     */
    private function responsePayload(Transfer $transfer): array
    {
        return [
            'response' => [
                'transfer' => $transfer->toArray(),
                'status' => $transfer->status,
            ],
            'responseCode' => 201,
            'transfer' => $transfer,
        ];
    }
}
