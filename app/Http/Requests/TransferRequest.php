<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferRequest extends FormRequest
{
    /**
     * All incoming transfer requests are considered authorized at the route level.
     * Authentication (API keys, JWT, etc.) is handled by the HMAC middleware and
     * Sanctum token guard layered in routes/api.php.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "sender_account_id" => ["required", "uuid", "exists:accounts,id"],
            "receiver_account_id" => [
                "required",
                "uuid",
                "exists:accounts,id",
                "different:sender_account_id",
            ],
            "amount" => [
                "required",
                "numeric",
                "min:0.01",
                "max:999999999.99",
                'regex:/^\d+(\.\d{1,2})?$/',
            ],

            // Validated against the ISO 4217 allowlist defined in config/sentinelpay.php.
            // This replaces the previous `size:3` rule which accepted arbitrary strings
            // like "LOL" or "XYZ" — any three characters passed before.
            "currency" => [
                "required",
                "string",
                "size:3",
                Rule::in(config("sentinelpay.supported_currencies")),
            ],

            "idempotency_key" => ["required", "string", "min:16", "max:128"],
        ];
    }

    public function messages(): array
    {
        return [
            "receiver_account_id.different" =>
                "Sender and receiver accounts must be different.",
            "amount.regex" => "Amount must have at most 2 decimal places.",
            "idempotency_key.min" =>
                "Idempotency key must be at least 16 characters.",
            "currency.in" =>
                "The currency must be a supported ISO 4217 code (e.g. USD, EUR, GBP).",
        ];
    }

    /**
     * Prepares input for validation — normalises currency to uppercase so that
     * "usd", "Usd", and "USD" are all treated identically before the allowlist
     * check runs.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has("currency")) {
            $this->merge([
                "currency" => strtoupper($this->input("currency")),
            ]);
        }
    }
}
