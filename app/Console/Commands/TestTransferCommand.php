<?php

namespace App\Console\Commands;

use App\Exceptions\AccountInactiveException;
use App\Exceptions\AccountNotFoundException;
use App\Exceptions\InsufficientFundsException;
use App\Models\Account;
use App\Services\TransferService;
use Illuminate\Console\Command;

class TestTransferCommand extends Command
{
    protected $signature = 'sentinelpay:test-transfer
                            {sender_id        : UUID of the sender account}
                            {receiver_id      : UUID of the receiver account}
                            {amount           : Decimal amount to transfer}
                            {currency         : ISO 4217 currency code (e.g. USD)}
                            {idempotency_key  : Unique key for this transfer attempt}';

    protected $description = '[Test harness] Execute a single transfer for the concurrency test suite';

    public function __construct(private readonly TransferService $transferService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $senderId = $this->argument('sender_id');
        $receiverId = $this->argument('receiver_id');
        $amount = $this->argument('amount');
        $currency = strtoupper($this->argument('currency'));
        $idempotencyKey = $this->argument('idempotency_key');

        try {
            $senderAccount = Account::query()->findOrFail($senderId);

            $result = $this->transferService->transfer(
                merchantId: (string) $senderAccount->merchant_id,
                senderAccountId: $senderId,
                receiverAccountId: $receiverId,
                amount: $amount,
                currency: $currency,
                idempotencyKey: $idempotencyKey,
                signature: 'concurrency-test-internal-signature',
                requestPath: 'artisan/sentinelpay:test-transfer',
            );

            $transfer = $result['transfer'];

            $this->line(json_encode([
                'status' => 'success',
                'transaction_id' => $transfer->id,
                'amount' => $transfer->amount,
                'currency' => $transfer->currency,
            ]));

            return self::SUCCESS;
        } catch (InsufficientFundsException $e) {
            $this->line(json_encode([
                'status' => 'failed',
                'reason' => 'insufficient_funds',
                'detail' => $e->getMessage(),
            ]));

            return self::FAILURE;
        } catch (AccountInactiveException $e) {
            $this->line(json_encode([
                'status' => 'failed',
                'reason' => 'account_inactive',
                'detail' => $e->getMessage(),
            ]));

            return self::FAILURE;
        } catch (AccountNotFoundException $e) {
            $this->line(json_encode([
                'status' => 'failed',
                'reason' => 'account_not_found',
                'detail' => $e->getMessage(),
            ]));

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->line(json_encode([
                'status' => 'error',
                'reason' => 'unexpected_error',
                'detail' => $e->getMessage(),
            ]));

            return 2;
        }
    }
}
