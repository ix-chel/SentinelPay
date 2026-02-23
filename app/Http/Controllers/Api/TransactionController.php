<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AccountInactiveException;
use App\Exceptions\AccountNotFoundException;
use App\Exceptions\InsufficientFundsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransferRequest;
use App\Models\Account;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function __construct(private readonly TransferService $transferService)
    {
    }

    /**
     * POST /api/transfers
     * Initiate a fund transfer between two accounts.
     */
    public function transfer(TransferRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Read the raw signature from the header (already validated by HMAC middleware)
        $signature = $request->header('X-Signature');

        try {
            $transaction = $this->transferService->transfer(
                senderAccountId:   $validated['sender_account_id'],
                receiverAccountId: $validated['receiver_account_id'],
                amount:            (string) $validated['amount'],
                currency:          $validated['currency'],
                idempotencyKey:    $validated['idempotency_key'],
                signature:         $signature
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Transfer completed successfully.',
                'data'    => [
                    'transaction_id'  => $transaction->id,
                    'idempotency_key' => $transaction->idempotency_key,
                    'sender_id'       => $transaction->sender_id,
                    'receiver_id'     => $transaction->receiver_id,
                    'amount'          => $transaction->amount,
                    'currency'        => $transaction->currency,
                    'status'          => $transaction->status,
                    'created_at'      => $transaction->created_at->toIso8601String(),
                ],
            ], JsonResponse::HTTP_CREATED);

        } catch (InsufficientFundsException $e) {
            return response()->json([
                'status'  => 'error',
                'error'   => 'INSUFFICIENT_FUNDS',
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);

        } catch (AccountInactiveException $e) {
            return response()->json([
                'status'  => 'error',
                'error'   => 'ACCOUNT_INACTIVE',
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_FORBIDDEN);

        } catch (AccountNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'error'   => 'ACCOUNT_NOT_FOUND',
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_NOT_FOUND);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status'  => 'error',
                'error'   => 'INVALID_REQUEST',
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Throwable $e) {
            Log::error('Transfer failed with unexpected error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'error'   => 'INTERNAL_ERROR',
                'message' => 'An unexpected error occurred. Please try again.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/accounts/{account}/balance
     * Check the current balance of an account.
     */
    public function balance(Account $account): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => [
                'account_id' => $account->id,
                'balance'    => $account->balance,
                'currency'   => $account->currency,
                'is_active'  => $account->is_active,
            ],
        ]);
    }

    /**
     * GET /api/accounts/{account}/transactions
     * List transactions for a specific account.
     */
    public function transactions(Account $account, Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);

        $transactions = $account->sentTransactions()
            ->union($account->receivedTransactions()->getQuery())
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => $transactions,
        ]);
    }
}
