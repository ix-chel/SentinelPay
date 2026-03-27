<?php

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Merchant;
use App\Models\Transfer;

function createTransferForAudit(Merchant $merchant, Account $source, Account $destination, string $amount): Transfer
{
    return Transfer::create([
        'merchant_id' => $merchant->id,
        'idempotency_key' => 'audit-transfer-'.str()->uuid()->toString(),
        'source_account_id' => $source->id,
        'destination_account_id' => $destination->id,
        'amount' => $amount,
        'currency' => 'USD',
        'status' => Transfer::STATUS_COMPLETED,
    ]);
}

describe('audit:ledger command', function () {
    it('passes when the account balance matches the latest ledger balance', function () {
        $merchant = Merchant::factory()->create();
        $auditedAccount = Account::factory()->for($merchant)->withBalance('90.00')->create(['currency' => 'USD']);
        $counterparty = Account::factory()->for($merchant)->withBalance('10.00')->create(['currency' => 'USD']);

        $creditTransfer = createTransferForAudit($merchant, $counterparty, $auditedAccount, '100.00');
        LedgerEntry::create([
            'transfer_id' => $creditTransfer->id,
            'account_id' => $auditedAccount->id,
            'type' => LedgerEntry::TYPE_CREDIT,
            'amount' => '100.00',
            'balance_after' => '100.00',
            'currency' => 'USD',
        ]);

        $debitTransfer = createTransferForAudit($merchant, $auditedAccount, $counterparty, '10.00');
        LedgerEntry::create([
            'transfer_id' => $debitTransfer->id,
            'account_id' => $auditedAccount->id,
            'type' => LedgerEntry::TYPE_DEBIT,
            'amount' => '10.00',
            'balance_after' => '90.00',
            'currency' => 'USD',
        ]);

        $this->artisan('audit:ledger', ['--account' => $auditedAccount->id])
            ->expectsOutputToContain('Passed: 1')
            ->expectsOutputToContain('Failed: 0')
            ->assertSuccessful();
    });

    it('reconciles drifted balances to the latest ledger balance', function () {
        $merchant = Merchant::factory()->create();
        $auditedAccount = Account::factory()->for($merchant)->withBalance('80.00')->create(['currency' => 'USD']);
        $counterparty = Account::factory()->for($merchant)->withBalance('10.00')->create(['currency' => 'USD']);

        $transfer = createTransferForAudit($merchant, $counterparty, $auditedAccount, '90.00');
        LedgerEntry::create([
            'transfer_id' => $transfer->id,
            'account_id' => $auditedAccount->id,
            'type' => LedgerEntry::TYPE_CREDIT,
            'amount' => '90.00',
            'balance_after' => '90.00',
            'currency' => 'USD',
        ]);

        $this->artisan('audit:ledger', [
            '--account' => $auditedAccount->id,
            '--fix' => true,
            '--force' => true,
        ])->expectsOutputToContain('Corrected: 1')
            ->assertSuccessful();

        expect((string) $auditedAccount->fresh()->balance)->toBe('90.00');
    });
});
