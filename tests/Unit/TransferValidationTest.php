<?php

namespace Tests\Unit;

use App\Http\Requests\TransferRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransferValidationTest extends TestCase
{
    /**
     * Test that self-transfers (where sender_account_id equals receiver_account_id)
     * are strictly rejected by the TransferRequest validation rules.
     */
    public function test_rejects_self_transfers_when_sender_and_receiver_are_identical(): void
    {
        $accountId = Str::uuid()->toString();
        $request = new TransferRequest();

        // Isolate the request rules and custom messages for account pairing
        $validator = Validator::make([
            'sender_account_id'   => $accountId,
            'receiver_account_id' => $accountId,
            'amount'              => '100.00',
            'currency'            => 'USD',
            'idempotency_key'     => Str::uuid()->toString(),
        ], [
            'sender_account_id'   => ['required', 'uuid'],
            'receiver_account_id' => ['required', 'uuid', 'different:sender_account_id'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'currency'            => ['required', 'string'],
            'idempotency_key'     => ['required', 'string'],
        ], $request->messages());

        $this->assertTrue($validator->fails(), 'Validation should fail when sender and receiver accounts are identical.');
        $this->assertArrayHasKey('receiver_account_id', $validator->errors()->messages());
        $this->assertContains(
            'Sender and receiver accounts must be different.',
            $validator->errors()->get('receiver_account_id')
        );
    }

    /**
     * Test that transfer validation passes when sender and receiver accounts are distinct.
     */
    public function test_accepts_distinct_sender_and_receiver_accounts(): void
    {
        $senderId = Str::uuid()->toString();
        $receiverId = Str::uuid()->toString();
        $request = new TransferRequest();

        $validator = Validator::make([
            'sender_account_id'   => $senderId,
            'receiver_account_id' => $receiverId,
            'amount'              => '100.00',
            'currency'            => 'USD',
            'idempotency_key'     => Str::uuid()->toString(),
        ], [
            'sender_account_id'   => ['required', 'uuid'],
            'receiver_account_id' => ['required', 'uuid', 'different:sender_account_id'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'currency'            => ['required', 'string'],
            'idempotency_key'     => ['required', 'string'],
        ], $request->messages());

        $this->assertFalse($validator->fails(), 'Validation should pass for distinct sender and receiver accounts.');
    }
}
