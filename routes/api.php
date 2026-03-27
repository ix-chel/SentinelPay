<?php

use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\VerifyApiKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['status' => 'ok']));

    // Admin / Signup endpoints
    Route::post('/merchants', [MerchantController::class, 'store']);

    // API Key Protected Endpoints
    Route::middleware([VerifyApiKey::class])->group(function () {
        // Merchant self-service API keys
        Route::post('/keys', [ApiKeyController::class, 'store']);
        Route::delete('/keys/{id}', [ApiKeyController::class, 'destroy']);

        // Transfers
        Route::post('/transfers', [TransferController::class, 'transfer'])->middleware('hmac');
        Route::get('/transfers/{id}', [TransferController::class, 'show']);
        Route::get('/balances', [TransferController::class, 'balance']);
        Route::get('/ledger/{accountId}', [TransferController::class, 'ledger']);

        // Webhooks
        Route::get('/webhooks', [WebhookController::class, 'index']);
        Route::post('/webhooks', [WebhookController::class, 'store']);
        Route::delete('/webhooks/{id}', [WebhookController::class, 'destroy']);
    });
});
