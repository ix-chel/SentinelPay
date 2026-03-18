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
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    /**
     * POST /api/v1/transfers
     * Initiate a fund transfer between two accounts.
     */
    public function transfer(TransferRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Read the raw signature from the header (already validated by HMAC middleware)
        $signature = $request->header("X-Signature");

        try {
            $transaction = $this->transferService->transfer(
                senderAccountId: $validated["sender_account_id"],
                receiverAccountId: $validated["receiver_account_id"],
                amount: (string) $validated["amount"],
                currency: $validated["currency"],
                idempotencyKey: $validated["idempotency_key"],
                signature: $signature,
            );

            return response()->json(
                [
                    "status" => "success",
                    "message" => "Transfer completed successfully.",
                    "data" => [
                        "transaction_id" => $transaction->id,
                        "idempotency_key" => $transaction->idempotency_key,
                        "sender_id" => $transaction->sender_id,
                        "receiver_id" => $transaction->receiver_id,
                        "amount" => $transaction->amount,
                        "currency" => $transaction->currency,
                        "status" => $transaction->status,
                        "created_at" => $transaction->created_at->toIso8601String(),
                    ],
                ],
                JsonResponse::HTTP_CREATED,
            );
        } catch (InsufficientFundsException $e) {
            return response()->json(
                [
                    "status" => "error",
                    "error" => "INSUFFICIENT_FUNDS",
                    "message" => $e->getMessage(),
                ],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (AccountInactiveException $e) {
            return response()->json(
                [
                    "status" => "error",
                    "error" => "ACCOUNT_INACTIVE",
                    "message" => $e->getMessage(),
                ],
                JsonResponse::HTTP_FORBIDDEN,
            );
        } catch (AccountNotFoundException $e) {
            return response()->json(
                [
                    "status" => "error",
                    "error" => "ACCOUNT_NOT_FOUND",
                    "message" => $e->getMessage(),
                ],
                JsonResponse::HTTP_NOT_FOUND,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(
                [
                    "status" => "error",
                    "error" => "INVALID_REQUEST",
                    "message" => $e->getMessage(),
                ],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (\Throwable $e) {
            Log::error("Transfer failed with unexpected error", [
                "message" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    "status" => "error",
                    "error" => "INTERNAL_ERROR",
                    "message" =>
                        "An unexpected error occurred. Please try again.",
                ],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * GET /api/v1/accounts/{account}/balance
     *
     * Returns the current balance for the given account.
     * The authenticated user may only query accounts they own.
     *
     * Requires:  Authorization: Bearer <sanctum-token>
     */
    public function balance(Request $request, Account $account): JsonResponse
    {
        // Scope to the authenticated user — prevent one user reading another's balance.
        if ($account->user_id !== $request->user()->id) {
            return response()->json(
                [
                    "status" => "error",
                    "error" => "FORBIDDEN",
                    "message" => "This account does not belong to you.",
                ],
                JsonResponse::HTTP_FORBIDDEN,
            );
        }

        return response()->json([
            "status" => "success",
            "data" => [
                "account_id" => $account->id,
                "balance" => $account->balance,
                "currency" => $account->currency,
                "is_active" => $account->is_active,
            ],
        ]);
    }

    /**
     * GET /api/v1/accounts/{account}/transactions?per_page=20
     *
     * Returns paginated sent + received transactions for the given account.
     * The authenticated user may only query accounts they own.
     *
     * Requires:  Authorization: Bearer <sanctum-token>
     */
    public function transactions(
        Request $request,
        Account $account,
    ): JsonResponse {
        // Scope to the authenticated user — prevent one user reading another's history.
        if ($account->user_id !== $request->user()->id) {
            return response()->json(
                [
                    "status" => "error",
                    "error" => "FORBIDDEN",
                    "message" => "This account does not belong to you.",
                ],
                JsonResponse::HTTP_FORBIDDEN,
            );
        }

        $perPage = min((int) $request->query("per_page", 20), 100);

        // UNION of sent and received transactions so the caller sees the full
        // picture for this account in a single paginated response.
        $transactions = $account
            ->sentTransactions()
            ->union($account->receivedTransactions()->getQuery())
            ->latest()
            ->paginate($perPage);

        return response()->json([
            "status" => "success",
            "data" => $transactions,
        ]);
    }
}
