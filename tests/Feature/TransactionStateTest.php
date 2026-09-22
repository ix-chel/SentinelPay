<?php

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Services\TransferService;
use Illuminate\Support\Str;

describe('Transaction State Integrity & Atomic Lifecycle', function () {

    it('persists a successful transaction with terminal status completed', function () {
        $sender = Account::factory()->withBalance('1000.00')->create(['currency' => 'USD']);
        $receiver = Account::factory()->withBalance('200.00')->create(['currency' => 'USD']);
        $key = Str::uuid()->toString();

        $service = app(TransferService::class);
        $txn = $service->transfer(
            senderAccountId:   $sender->id,
            receiverAccountId: $receiver->id,
            amount:            '150.00',
            currency:          'USD',
            idempotencyKey:    $key,
            signature:         'sig-state-test',
        );

        expect($txn->status)->toBe(Transaction::STATUS_COMPLETED)
            ->and($txn->amount)->toBe('150.00')
            ->and($txn->currency)->toBe('USD')
            ->and($txn->payload_hash)->not->toBeNull()
            ->and($txn->sender_id)->toBe($sender->id)
            ->and($txn->receiver_id)->toBe($receiver->id);

        // Verify in database
        $dbTxn = Transaction::where('idempotency_key', $key)->first();
        expect($dbTxn)->not->toBeNull()
            ->and($dbTxn->status)->toBe(Transaction::STATUS_COMPLETED);

        // Verify exactly 2 ledger entries
        $ledgers = Ledger::where('transaction_id', $txn->id)->get();
        expect($ledgers->count())->toBe(2);

        $debit = $ledgers->firstWhere('type', Ledger::TYPE_DEBIT);
        $credit = $ledgers->firstWhere('type', Ledger::TYPE_CREDIT);

        expect($debit->account_id)->toBe($sender->id)
            ->and((float) $debit->amount)->toBe(150.00)
            ->and((float) $debit->balance_after)->toBe(850.00);

        expect($credit->account_id)->toBe($receiver->id)
            ->and((float) $credit->amount)->toBe(150.00)
            ->and((float) $credit->balance_after)->toBe(350.00);
    });

    it('ensures failed transfers leave no inconsistent or orphan transaction rows in database', function () {
        $sender = Account::factory()->withBalance('50.00')->create(['currency' => 'USD']);
        $receiver = Account::factory()->withBalance('0.00')->create(['currency' => 'USD']);
        $key = Str::uuid()->toString();

        $service = app(TransferService::class);

        // Transfer fails validation inside service
        expect(fn () => $service->transfer(
            senderAccountId:   $sender->id,
            receiverAccountId: $receiver->id,
            amount:            '200.00',
            currency:          'USD',
            idempotencyKey:    $key,
            signature:         'sig',
        ))->toThrow(\App\Exceptions\InsufficientFundsException::class);

        // Architecture verification: Failed transfers roll back completely.
        // No non-terminal (pending, processing) or orphan rows persist.
        expect(Transaction::where('idempotency_key', $key)->exists())->toBeFalse();
        expect(Transaction::whereIn('status', [Transaction::STATUS_PENDING, Transaction::STATUS_PROCESSING])->count())->toBe(0);
        expect(Ledger::count())->toBe(0);

        // Balances remain intact
        $sender->refresh();
        $receiver->refresh();
        expect((float) $sender->balance)->toBe(50.00);
        expect((float) $receiver->balance)->toBe(0.00);
    });

    it('ensures inactive account transfers leave no transaction rows in database', function () {
        $sender = Account::factory()->inactive()->withBalance('1000.00')->create(['currency' => 'USD']);
        $receiver = Account::factory()->withBalance('0.00')->create(['currency' => 'USD']);
        $key = Str::uuid()->toString();

        $service = app(TransferService::class);

        expect(fn () => $service->transfer(
            senderAccountId:   $sender->id,
            receiverAccountId: $receiver->id,
            amount:            '100.00',
            currency:          'USD',
            idempotencyKey:    $key,
            signature:         'sig',
        ))->toThrow(\App\Exceptions\AccountInactiveException::class);

        expect(Transaction::where('idempotency_key', $key)->exists())->toBeFalse();
        expect(Ledger::count())->toBe(0);
    });

});
