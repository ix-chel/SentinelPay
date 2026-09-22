<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('Authentication API — Sanctum Token Lifecycle', function () {

    it('authenticates user with valid credentials and returns Bearer token', function () {
        $user = User::factory()->create([
            'email'    => 'alice@sentinelpay.io',
            'password' => Hash::make('SecretPass123!'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'alice@sentinelpay.io',
            'password' => 'SecretPass123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Authenticated successfully.',
                'data'    => [
                    'token_type' => 'Bearer',
                    'user'       => [
                        'id'    => $user->id,
                        'email' => 'alice@sentinelpay.io',
                        'name'  => $user->name,
                    ],
                ],
            ]);

        expect($response->json('data.token'))->not->toBeEmpty();
    });

    it('rejects login with invalid password with 401', function () {
        User::factory()->create([
            'email'    => 'bob@sentinelpay.io',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'bob@sentinelpay.io',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
            ->assertJsonValidationErrors(['email']);
    });

    it('rejects login with non-existent email with 401', function () {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'nonexistent@sentinelpay.io',
            'password' => 'AnyPassword123!',
        ]);

        $response->assertStatus(401)
            ->assertJsonValidationErrors(['email']);
    });

    it('validates required fields on login endpoint with 422', function () {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    });

    it('allows authenticated user to logout and revokes their current token', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test-session')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Token revoked. You have been logged out.',
            ]);

        // Token must be deleted from personal_access_tokens
        expect($user->tokens()->count())->toBe(0);
    });

    it('rejects logout without authentication token with 401', function () {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    });

    it('prevents access to protected endpoints using a revoked token', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('revoked-test-token')->plainTextToken;

        // Verify valid access before logout
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/accounts/{$account->id}/balance")
            ->assertStatus(200);

        // Logout
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        // Clear the in-memory guard cache so Sanctum re-evaluates the token from database
        $this->app['auth']->forgetGuards();

        // Subsequent request with the revoked token must be rejected with 401
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/accounts/{$account->id}/balance")
            ->assertStatus(401);
    });

});
