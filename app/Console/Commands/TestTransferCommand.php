<?php

namespace App\Console\Commands;

use App\Exceptions\AccountInactiveException;
use App\Exceptions\AccountNotFoundException;
use App\Exceptions\InsufficientFundsException;
use App\Services\TransferService;
use Illuminate\Console\Command;

/**
 * Single-transfer artisan command invoked by the concurrency test harness.
 *
 * The ConcurrencyTest spawns N parallel processes, each running this command
 * with a unique idempotency key. Because every process is a separate PHP
 * runtime connected to the same PostgreSQL database, this exercises the real
 * SELECT FOR UPDATE locking path — something an in-process sequential loop
 * cannot do.
 *
 * Exit codes:
 *   0  — transfer completed successfully
 *   1  — transfer rejected (insufficient funds, inactive account, etc.)
 *   2  — unexpected error
 *
 * Output: a single line of JSON written to stdout so the parent process can
 * parse results without scraping human-readable text.
 *
 * Usage (manual):
 *   php artisan sentinelpay:test-transfer <sender> <receiver> <amount> <currency> <idempotency_key>
 */
class TestTransferCommand extends Command
{
    protected $signature = 'sentinelpay:test-transfer
                            {sender_id        : UUID of the sender account}
                            {receiver_id      : UUID of the receiver account}
                            {amount           : Decimal amount to transfer}
                            {currency         : ISO 4217 currency code (e.g. USD)}
                            {idempotency_key  : Unique key for this transfer attempt}';

    protected $description = '[Test harness] Execute a single transfer — used by the concurrency test suite';

    public function __construct(private readonly TransferService $transferService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $senderId       = $this->argument('sender_id');
        $receiverId     = $this->argument('receiver_id');
        $amount         = $this->argument('amount');
        $currency       = strtoupper($this->argument('currency'));
        $idempotencyKey = $this->argument('idempotency_key');

        try {
            $transaction = $this->transferService->transfer(
                senderAccountId:   $senderId,
                receiverAccountId: $receiverId,
                amount:            $amount,
                currency:          $currency,
                idempotencyKey:    $idempotencyKey,
                signature:         'concurrency-test-internal-signature',
            );

            $this->line(json_encode([
                'status'         => 'success',
                'transaction_id' => $transaction->id,
                'amount'         => $transaction->amount,
                'currency'       => $transaction->currency,
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
