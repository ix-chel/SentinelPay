<?php

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\LedgerEntry;
use App\Models\Merchant;
use App\Models\Transfer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

function createMerchantContext(): array
{
    $merchant = Merchant::factory()->create();
    $plainTextKey = 'sp_test_'.Str::random(32);

    ApiKey::create([
        'merchant_id' => $merchant->id,
        'hashed_key' => hash('sha256', $plainTextKey),
        'scopes' => ['*'],
        'rate_limit' => 100,
    ]);

    return [$merchant, $plainTextKey];
}

function signTransferPayload(array $payload): string
{
    return hash_hmac('sha256', json_encode($payload), (string) config('sentinelpay.hmac_secret'));
}

function signedTransferHeaders(string $plainTextKey, array $payload, string $idempotencyKey): array
{
    return [
        'X-API-Key' => $plainTextKey,
        'Idempotency-Key' => $idempotencyKey,
        'X-Signature' => signTransferPayload($payload),
    ];
}

function idempotencyLockKey(string $merchantId, string $idempotencyKey): string
{
    return "transfer-lock:{$merchantId}:{$idempotencyKey}";
}

describe('transfer API', function () {
    it('transfers funds between two merchant accounts and writes ledger entries', function () {
        [$merchant, $plainTextKey] = createMerchantContext();

        $sender = Account::factory()->for($merchant)->withBalance('1000.00')->create([
            'name' => 'Operating',
            'currency' => 'USD',
        ]);
        $receiver = Account::factory()->for($merchant)->withBalance('500.00')->create([
            'name' => 'Reserve',
            'currency' => 'USD',
        ]);

        $payload = [
            'source_account_id' => $sender->id,
            'destination_account_id' => $receiver->id,
            'amount' => '100.00',
            'currency' => 'USD',
        ];

        $response = $this->withHeaders(
            signedTransferHeaders($plainTextKey, $payload, 'feature-transfer-1')
        )->postJson('/api/v1/transfers', $payload);

        $response->assertCreated()
            ->assertJsonPath('status', Transfer::STATUS_COMPLETED)
            ->assertJsonPath('transfer.source_account_id', $sender->id)
            ->assertJsonPath('transfer.destination_account_id', $receiver->id);

        expect((string) $sender->fresh()->balance)->toBe('900.00');
        expect((string) $receiver->fresh()->balance)->toBe('600.00');
        expect(Transfer::count())->toBe(1);
        expect(LedgerEntry::count())->toBe(2);
    });

    it('replays duplicate idempotency keys without double charging', function () {
        [$merchant, $plainTextKey] = createMerchantContext();

        $sender = Account::factory()->for($merchant)->withBalance('1000.00')->create(['currency' => 'USD']);
        $receiver = Account::factory()->for($merchant)->withBalance('0.00')->create(['currency' => 'USD']);

        $payload = [
            'source_account_id' => $sender->id,
            'destination_account_id' => $receiver->id,
            'amount' => '200.00',
            'currency' => 'USD',
        ];

        $headers = signedTransferHeaders($plainTextKey, $payload, 'feature-transfer-2');

        $first = $this->withHeaders($headers)->postJson('/api/v1/transfers', $payload);
        $second = $this->withHeaders($headers)->postJson('/api/v1/transfers', $payload);

        $first->assertCreated();
        $second->assertCreated();
        expect($first->json('transfer.id'))->toBe($second->json('transfer.id'));
        expect((string) $sender->fresh()->balance)->toBe('800.00');
        expect(Transfer::count())->toBe(1);
        expect(LedgerEntry::count())->toBe(2);
    });

    it('rejects insufficient funds', function () {
        [$merchant, $plainTextKey] = createMerchantContext();

        $sender = Account::factory()->for($merchant)->withBalance('50.00')->create(['currency' => 'USD']);
        $receiver = Account::factory()->for($merchant)->withBalance('0.00')->create(['currency' => 'USD']);

        $payload = [
            'source_account_id' => $sender->id,
            'destination_account_id' => $receiver->id,
            'amount' => '100.00',
            'currency' => 'USD',
        ];

        $this->withHeaders(
            signedTransferHeaders($plainTextKey, $payload, 'feature-transfer-3')
        )->postJson('/api/v1/transfers', $payload)->assertStatus(422)
            ->assertJsonPath('error', 'INSUFFICIENT_FUNDS');

        expect((string) $sender->fresh()->balance)->toBe('50.00');
        expect(Transfer::count())->toBe(0);
    });

    it('prevents cross-merchant account access', function () {
        [$merchant, $plainTextKey] = createMerchantContext();
        $otherMerchant = Merchant::factory()->create();

        $sender = Account::factory()->for($merchant)->withBalance('1000.00')->create(['currency' => 'USD']);
        $receiver = Account::factory()->for($otherMerchant)->withBalance('1000.00')->create(['currency' => 'USD']);

        $payload = [
            'source_account_id' => $sender->id,
            'destination_account_id' => $receiver->id,
            'amount' => '10.00',
            'currency' => 'USD',
        ];

        $this->withHeaders(
            signedTransferHeaders($plainTextKey, $payload, 'feature-transfer-4')
        )->postJson('/api/v1/transfers', $payload)->assertStatus(404)
            ->assertJsonPath('error', 'ACCOUNT_NOT_FOUND');
    });

    it('rejects unsigned transfer requests', function () {
        [$merchant, $plainTextKey] = createMerchantContext();

        $sender = Account::factory()->for($merchant)->withBalance('1000.00')->create(['currency' => 'USD']);
        $receiver = Account::factory()->for($merchant)->withBalance('0.00')->create(['currency' => 'USD']);

        $this->withHeaders([
            'X-API-Key' => $plainTextKey,
            'Idempotency-Key' => 'feature-transfer-5',
        ])->postJson('/api/v1/transfers', [
            'source_account_id' => $sender->id,
            'destination_account_id' => $receiver->id,
            'amount' => '25.00',
            'currency' => 'USD',
        ])->assertStatus(401)
            ->assertJsonPath('error', 'Missing X-Signature header.');
    });

    it('returns 409 while the same idempotency key is already being processed', function () {
        [$merchant, $plainTextKey] = createMerchantContext();

        $sender = Account::factory()->for($merchant)->withBalance('1000.00')->create(['currency' => 'USD']);
        $receiver = Account::factory()->for($merchant)->withBalance('0.00')->create(['currency' => 'USD']);

        $payload = [
            'source_account_id' => $sender->id,
            'destination_account_id' => $receiver->id,
            'amount' => '25.00',
            'currency' => 'USD',
        ];

        $lock = Cache::store(config('cache.default'))->lock(idempotencyLockKey((string) $merchant->id, 'feature-transfer-6'), 10);
        expect($lock->get())->toBeTrue();

        try {
            $this->withHeaders(
                signedTransferHeaders($plainTextKey, $payload, 'feature-transfer-6')
            )->postJson('/api/v1/transfers', $payload)->assertStatus(409)
                ->assertJsonPath('error', 'IDEMPOTENCY_REQUEST_IN_PROGRESS');
        } finally {
            $lock->release();
        }
    });
});
