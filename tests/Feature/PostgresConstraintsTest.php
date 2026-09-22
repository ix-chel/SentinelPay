<?php

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

describe('PostgreSQL Integrity — Constraints & Append-Only Triggers', function () {

    it('enforces accounts_balance_non_negative CHECK constraint at PostgreSQL level', function () {
        $account = Account::factory()->withBalance('100.00')->create(['currency' => 'USD']);

        $exceptionCaught = false;
        try {
            DB::transaction(function () use ($account) {
                DB::statement("UPDATE accounts SET balance = -50.00 WHERE id = '{$account->id}'");
            });
        } catch (QueryException $e) {
            $exceptionCaught = true;
            expect($e->getMessage())->toContain('accounts_balance_non_negative');
        }

        expect($exceptionCaught)->toBeTrue('PostgreSQL failed to enforce accounts_balance_non_negative CHECK constraint');

        // Connection remains usable and state intact
        $account->refresh();
        expect((float) $account->balance)->toBe(100.00);
        expect(Account::where('id', $account->id)->exists())->toBeTrue();
    });

    it('enforces transactions_amount_positive CHECK constraint at PostgreSQL level', function () {
        $sender = Account::factory()->withBalance('1000.00')->create(['currency' => 'USD']);
        $receiver = Account::factory()->withBalance('0.00')->create(['currency' => 'USD']);
        $id = Str::uuid()->toString();
        $key = Str::uuid()->toString();

        $exceptionCaught = false;
        try {
            DB::transaction(function () use ($id, $key, $sender, $receiver) {
                DB::statement("
                    INSERT INTO transactions (id, idempotency_key, sender_id, receiver_id, amount, status, currency, signature, created_at, updated_at)
                    VALUES ('{$id}', '{$key}', '{$sender->id}', '{$receiver->id}', -25.00, 'pending', 'USD', 'sig', NOW(), NOW())
                ");
            });
        } catch (QueryException $e) {
            $exceptionCaught = true;
            expect($e->getMessage())->toContain('transactions_amount_positive');
        }

        expect($exceptionCaught)->toBeTrue('PostgreSQL failed to enforce transactions_amount_positive CHECK constraint');

        // Verify zero amount is also rejected by the CHECK (amount > 0)
        $zeroExceptionCaught = false;
        try {
            DB::transaction(function () use ($id, $key, $sender, $receiver) {
                DB::statement("
                    INSERT INTO transactions (id, idempotency_key, sender_id, receiver_id, amount, status, currency, signature, created_at, updated_at)
                    VALUES ('{$id}', '{$key}', '{$sender->id}', '{$receiver->id}', 0.00, 'pending', 'USD', 'sig', NOW(), NOW())
                ");
            });
        } catch (QueryException $e) {
            $zeroExceptionCaught = true;
            expect($e->getMessage())->toContain('transactions_amount_positive');
        }

        expect($zeroExceptionCaught)->toBeTrue('PostgreSQL failed to enforce positive amount on zero value');
        expect(Transaction::where('id', $id)->exists())->toBeFalse();
    });

    it('enforces ledgers_amount_positive CHECK constraint at PostgreSQL level', function () {
        $sender = Account::factory()->withBalance('1000.00')->create(['currency' => 'USD']);
        $receiver = Account::factory()->withBalance('0.00')->create(['currency' => 'USD']);
        $tx = Transaction::create([
            'idempotency_key' => Str::uuid()->toString(),
            'sender_id'       => $sender->id,
            'receiver_id'     => $receiver->id,
            'amount'          => '50.00',
            'status'          => Transaction::STATUS_COMPLETED,
            'currency'        => 'USD',
            'signature'       => 'sig',
        ]);

        $exceptionCaught = false;
        try {
            DB::transaction(function () use ($sender, $tx) {
                DB::statement("
                    INSERT INTO ledgers (account_id, transaction_id, type, amount, balance_after, created_at, updated_at)
                    VALUES ('{$sender->id}', '{$tx->id}', 'debit', -10.00, 950.00, NOW(), NOW())
                ");
            });
        } catch (QueryException $e) {
            $exceptionCaught = true;
            expect($e->getMessage())->toContain('ledgers_amount_positive');
        }

        expect($exceptionCaught)->toBeTrue('PostgreSQL failed to enforce ledgers_amount_positive CHECK constraint');
        expect(Ledger::where('transaction_id', $tx->id)->exists())->toBeFalse();
    });

    it('enforces ledgers_balance_after_non_negative CHECK constraint at PostgreSQL level', function () {
        $sender = Account::factory()->withBalance('1000.00')->create(['currency' => 'USD']);
        $receiver = Account::factory()->withBalance('0.00')->create(['currency' => 'USD']);
        $tx = Transaction::create([
            'idempotency_key' => Str::uuid()->toString(),
            'sender_id'       => $sender->id,
            'receiver_id'     => $receiver->id,
            'amount'          => '50.00',
            'status'          => Transaction::STATUS_COMPLETED,
            'currency'        => 'USD',
            'signature'       => 'sig',
        ]);

        $exceptionCaught = false;
        try {
            DB::transaction(function () use ($sender, $tx) {
                DB::statement("
                    INSERT INTO ledgers (account_id, transaction_id, type, amount, balance_after, created_at, updated_at)
                    VALUES ('{$sender->id}', '{$tx->id}', 'debit', 50.00, -10.00, NOW(), NOW())
                ");
            });
        } catch (QueryException $e) {
            $exceptionCaught = true;
            expect($e->getMessage())->toContain('ledgers_balance_after_non_negative');
        }

        expect($exceptionCaught)->toBeTrue('PostgreSQL failed to enforce ledgers_balance_after_non_negative CHECK constraint');
        expect(Ledger::where('transaction_id', $tx->id)->exists())->toBeFalse();
    });

    it('enforces ledger_no_update PostgreSQL trigger preventing direct SQL UPDATE', function () {
        $sender = Account::factory()->withBalance('1000.00')->create(['currency' => 'USD']);
        $receiver = Account::factory()->withBalance('0.00')->create(['currency' => 'USD']);
        $tx = Transaction::create([
            'idempotency_key' => Str::uuid()->toString(),
            'sender_id'       => $sender->id,
            'receiver_id'     => $receiver->id,
            'amount'          => '100.00',
            'status'          => Transaction::STATUS_COMPLETED,
            'currency'        => 'USD',
            'signature'       => 'sig',
        ]);

        // Create a valid ledger entry
        DB::statement("
            INSERT INTO ledgers (account_id, transaction_id, type, amount, balance_after, created_at, updated_at)
            VALUES ('{$sender->id}', '{$tx->id}', 'debit', 100.00, 900.00, NOW(), NOW())
        ");

        $ledgerId = DB::getPdo()->lastInsertId();

        $exceptionCaught = false;
        try {
            DB::transaction(function () use ($ledgerId) {
                DB::statement("UPDATE ledgers SET amount = 999.00 WHERE id = {$ledgerId}");
            });
        } catch (QueryException $e) {
            $exceptionCaught = true;
            expect($e->getMessage())->toContain('Ledger is append-only. UPDATE and DELETE operations are not permitted.');
        }

        expect($exceptionCaught)->toBeTrue('PostgreSQL trigger ledger_no_update did not fire on direct SQL UPDATE');

        // Confirm ledger record remains unmutated
        $row = DB::table('ledgers')->where('id', $ledgerId)->first();
        expect((float) $row->amount)->toBe(100.00);
    });

    it('enforces ledger_no_delete PostgreSQL trigger preventing direct SQL DELETE', function () {
        $sender = Account::factory()->withBalance('1000.00')->create(['currency' => 'USD']);
        $receiver = Account::factory()->withBalance('0.00')->create(['currency' => 'USD']);
        $tx = Transaction::create([
            'idempotency_key' => Str::uuid()->toString(),
            'sender_id'       => $sender->id,
            'receiver_id'     => $receiver->id,
            'amount'          => '100.00',
            'status'          => Transaction::STATUS_COMPLETED,
            'currency'        => 'USD',
            'signature'       => 'sig',
        ]);

        // Create a valid ledger entry
        DB::statement("
            INSERT INTO ledgers (account_id, transaction_id, type, amount, balance_after, created_at, updated_at)
            VALUES ('{$sender->id}', '{$tx->id}', 'debit', 100.00, 900.00, NOW(), NOW())
        ");

        $ledgerId = DB::getPdo()->lastInsertId();

        $exceptionCaught = false;
        try {
            DB::transaction(function () use ($ledgerId) {
                DB::statement("DELETE FROM ledgers WHERE id = {$ledgerId}");
            });
        } catch (QueryException $e) {
            $exceptionCaught = true;
            expect($e->getMessage())->toContain('Ledger is append-only. UPDATE and DELETE operations are not permitted.');
        }

        expect($exceptionCaught)->toBeTrue('PostgreSQL trigger ledger_no_delete did not fire on direct SQL DELETE');

        // Confirm ledger record still exists
        expect(DB::table('ledgers')->where('id', $ledgerId)->exists())->toBeTrue();
    });

});
