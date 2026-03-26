<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransferController extends Controller
{
    public function __construct(private readonly TransferService $transferService) {}

    public function transfer(Request $request): JsonResponse
    {
        $merchant = $request->attributes->get('merchant');

        $request->validate([
            'source_account_id' => 'required|uuid',
            'destination_account_id' => 'required|uuid|different:source_account_id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => ['required', 'string', 'size:3', Rule::in(config('sentinelpay.supported_currencies'))],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');
        if (! $idempotencyKey) {
            return response()->json(['error' => 'Idempotency-Key header is required'], 400);
        }

        $result = $this->transferService->transfer(
            merchantId: (string) $merchant->id,
            senderAccountId: $request->string('source_account_id')->toString(),
            receiverAccountId: $request->string('destination_account_id')->toString(),
            amount: (string) $request->input('amount'),
            currency: strtoupper($request->string('currency')->toString()),
            idempotencyKey: $idempotencyKey,
            signature: $request->header('X-Signature'),
            requestPath: $request->path(),
        );

        return response()->json($result['response'], $result['responseCode']);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $merchant = $request->attributes->get('merchant');

        $transfer = Transfer::query()
            ->where('merchant_id', $merchant->id)
            ->with(['sourceAccount', 'destinationAccount'])
            ->findOrFail($id);

        return response()->json($transfer);
    }

    public function balance(Request $request): JsonResponse
    {
        $merchant = $request->attributes->get('merchant');

        $accounts = Account::query()
            ->where('merchant_id', $merchant->id)
            ->get(['id', 'balance', 'currency', 'name', 'is_active']);

        return response()->json(['accounts' => $accounts]);
    }

    public function ledger(Request $request, string $accountId): JsonResponse
    {
        $merchant = $request->attributes->get('merchant');

        $account = Account::query()
            ->where('merchant_id', $merchant->id)
            ->findOrFail($accountId);

        $entries = LedgerEntry::query()
            ->where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($entries);
    }
}
