<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    /**
     * All incoming transfer requests are considered authorized at the route level.
     * Authentication (API keys, JWT, etc.) would be layered separately in production.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sender_account_id'   => ['required', 'uuid', 'exists:accounts,id'],
            'receiver_account_id' => ['required', 'uuid', 'exists:accounts,id', 'different:sender_account_id'],
            'amount'              => ['required', 'numeric', 'min:0.01', 'max:999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'currency'            => ['required', 'string', 'size:3'],
            'idempotency_key'     => ['required', 'string', 'min:16', 'max:128'],
        ];
    }

    public function messages(): array
    {
        return [
            'receiver_account_id.different' => 'Sender and receiver accounts must be different.',
            'amount.regex'                  => 'Amount must have at most 2 decimal places.',
            'idempotency_key.min'           => 'Idempotency key must be at least 16 characters.',
        ];
    }

    /**
     * Prepares input for validation — normalizes currency to uppercase.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge([
                'currency' => strtoupper($this->input('currency')),
            ]);
        }
    }
}
