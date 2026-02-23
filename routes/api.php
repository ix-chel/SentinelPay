<?php

use App\Http\Controllers\Api\TransactionController;
use App\Http\Middleware\VerifyHmacSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| All payment routes are protected by the HMAC signature verification
| middleware to prevent payload tampering.
*/

Route::prefix('v1')->group(function () {

    // Health check — no signature required
    Route::get('/health', fn () => response()->json([
        'status'    => 'ok',
        'service'   => 'SentinelPay',
        'timestamp' => now()->toIso8601String(),
    ]));

    // Payment endpoints — require HMAC signature
    Route::middleware([VerifyHmacSignature::class])->group(function () {
        Route::post('/transfers', [TransactionController::class, 'transfer']);
    });

    // Account information endpoints (no HMAC required for reads)
    Route::get('/accounts/{account}/balance', [TransactionController::class, 'balance']);
    Route::get('/accounts/{account}/transactions', [TransactionController::class, 'transactions']);
});
