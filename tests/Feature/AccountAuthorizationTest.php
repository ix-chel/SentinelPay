<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

describe('Account Authorization & Ownership Boundaries', function () {

    it('allows owner to query their own account balance', function () {
        $user = User::factory()->create();
        $account = Account::factory()->withBalance('1250.00')->create([
            'user_id'  => $user->id,
            'currency' => 'USD',
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/accounts/{$account->id}/balance");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'account_id' => $account->id,
                    'balance'    => '1250.00',
                    'currency'   => 'USD',
                    'is_active'  => true,
                ],
            ]);
    });

    it('forbids a user from querying another users account balance', function () {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();

        $bobAccount = Account::factory()->withBalance('5000.00')->create([
            'user_id' => $bob->id,
        ]);

        $aliceToken = $alice->createToken('alice-token')->plainTextToken;

        // Alice attempts to inspect Bob's balance
        $response = $this->withHeader('Authorization', "Bearer {$aliceToken}")
            ->getJson("/api/v1/accounts/{$bobAccount->id}/balance");

        $response->assertStatus(403)
            ->assertJson([
                'status'  => 'error',
                'error'   => 'FORBIDDEN',
                'message' => 'This account does not belong to you.',
            ]);
    });

    it('allows owner to query their own transactions', function () {
        $user = User::factory()->create();
        $account = Account::factory()->withBalance('2000.00')->create([
            'user_id' => $user->id,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/accounts/{$account->id}/transactions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'data',
                    'current_page',
                    'total',
                ],
            ]);
    });

    it('forbids a user from querying another users transaction history', function () {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();

        $bobAccount = Account::factory()->withBalance('5000.00')->create([
            'user_id' => $bob->id,
        ]);

        $aliceToken = $alice->createToken('alice-token')->plainTextToken;

        // Alice attempts to inspect Bob's transaction history
        $response = $this->withHeader('Authorization', "Bearer {$aliceToken}")
            ->getJson("/api/v1/accounts/{$bobAccount->id}/transactions");

        $response->assertStatus(403)
            ->assertJson([
                'status'  => 'error',
                'error'   => 'FORBIDDEN',
                'message' => 'This account does not belong to you.',
            ]);
    });

    it('rejects unauthenticated requests to balance and transaction endpoints', function () {
        $account = Account::factory()->create();

        $this->getJson("/api/v1/accounts/{$account->id}/balance")
            ->assertStatus(401);

        $this->getJson("/api/v1/accounts/{$account->id}/transactions")
            ->assertStatus(401);
    });

    it('forbids authenticated user from transferring funds out of an account they do not own', function () {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();

        $bobAccount = Account::factory()->withBalance('1000.00')->create([
            'user_id'  => $bob->id,
            'currency' => 'USD',
        ]);
        $charlieAccount = Account::factory()->withBalance('0.00')->create([
            'currency' => 'USD',
        ]);

        $aliceToken = $alice->createToken('alice-token')->plainTextToken;

        $payload = [
            'sender_account_id'   => $bobAccount->id, // Bob's account
            'receiver_account_id' => $charlieAccount->id,
            'amount'              => '100.00',
            'currency'            => 'USD',
            'idempotency_key'     => Str::uuid()->toString(),
        ];
        $secret = config('sentinelpay.hmac_secret');
        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        // Alice sends a request with her Sanctum token + valid HMAC signature, trying to debit Bob's account
        $response = $this->withHeader('Authorization', "Bearer {$aliceToken}")
            ->postJson('/api/v1/transfers', $payload, [
                'X-Signature' => $signature,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'status'  => 'error',
                'error'   => 'FORBIDDEN',
                'message' => 'This account does not belong to you.',
            ]);

        // Verify Bob's balance is untouched
        $bobAccount->refresh();
        expect((float) $bobAccount->balance)->toBe(1000.00);
        expect(Transaction::count())->toBe(0);
    });

    it('allows authenticated user to transfer funds out of their own account', function () {
        $alice = User::factory()->create();
        $aliceAccount = Account::factory()->withBalance('1000.00')->create([
            'user_id'  => $alice->id,
            'currency' => 'USD',
        ]);
        $bobAccount = Account::factory()->withBalance('0.00')->create([
            'currency' => 'USD',
        ]);

        $aliceToken = $alice->createToken('alice-token')->plainTextToken;

        $payload = [
            'sender_account_id'   => $aliceAccount->id,
            'receiver_account_id' => $bobAccount->id,
            'amount'              => '150.00',
            'currency'            => 'USD',
            'idempotency_key'     => Str::uuid()->toString(),
        ];
        $secret = config('sentinelpay.hmac_secret');
        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        $response = $this->withHeader('Authorization', "Bearer {$aliceToken}")
            ->postJson('/api/v1/transfers', $payload, [
                'X-Signature' => $signature,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Transfer completed successfully.',
            ]);

        $aliceAccount->refresh();
        $bobAccount->refresh();
        expect((float) $aliceAccount->balance)->toBe(850.00);
        expect((float) $bobAccount->balance)->toBe(150.00);
    });

    it('allows M2M HMAC-only transfer without user token (backward compatibility)', function () {
        $sender = Account::factory()->withBalance('1000.00')->create(['currency' => 'USD']);
        $receiver = Account::factory()->withBalance('0.00')->create(['currency' => 'USD']);

        $payload = [
            'sender_account_id'   => $sender->id,
            'receiver_account_id' => $receiver->id,
            'amount'              => '100.00',
            'currency'            => 'USD',
            'idempotency_key'     => Str::uuid()->toString(),
        ];
        $secret = config('sentinelpay.hmac_secret');
        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        // No Authorization header; HMAC signature only
        $response = $this->postJson('/api/v1/transfers', $payload, [
            'X-Signature' => $signature,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Transfer completed successfully.',
            ]);
    });

});
