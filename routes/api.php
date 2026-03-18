<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Middleware\VerifyHmacSignature;
use Illuminate\Support\Facades\Route;

//
// API Routes
// ----------
//
// Rate limiting strategy:
//   POST /auth/login                   ->  5 req/min   (brute-force protection)
//   POST /transfers                    -> 30 req/min   (abuse + cost protection)
//   GET  /accounts/{uuid}/balance      -> 60 req/min   (read — moderate)
//   GET  /accounts/{uuid}/transactions -> 60 req/min   (read — moderate)
//   GET  /health                       -> 120 req/min  (monitoring probes)
//
// Authentication strategy:
//   Mutating payment endpoints -> HMAC-SHA256 signature (VerifyHmacSignature)
//   Read account endpoints     -> Sanctum Bearer token  (auth:sanctum)
//   Auth endpoints             -> public (no guard, rate-limited only)
//

Route::prefix("v1")->group(function () {
    // ── Health check — public, loosely throttled ──────────────────────────────
    Route::get(
        "/health",
        fn() => response()->json([
            "status" => "ok",
            "service" => "SentinelPay",
            "timestamp" => now()->toIso8601String(),
        ]),
    )->middleware("throttle:120,1");

    // ── Authentication ────────────────────────────────────────────────────────
    // Login is aggressively throttled to slow down credential-stuffing attacks.
    // Logout requires a valid Sanctum token so a stolen token can be revoked.
    Route::prefix("auth")->group(function () {
        Route::post("/login", [AuthController::class, "login"])->middleware(
            "throttle:5,1",
        );

        Route::post("/logout", [AuthController::class, "logout"])->middleware([
            "auth:sanctum",
            "throttle:60,1",
        ]);
    });

    // ── Payment endpoint — HMAC signature required ────────────────────────────
    // The HMAC middleware validates the X-Signature header before the request
    // ever reaches the controller, preventing tampered payloads from being
    // processed. Rate-limited to 30 req/min per IP to deter flooding.
    Route::middleware([VerifyHmacSignature::class, "throttle:30,1"])->group(
        function () {
            Route::post("/transfers", [
                TransactionController::class,
                "transfer",
            ]);
        },
    );

    // ── Account read endpoints — Sanctum Bearer token required ───────────────
    // Protected by auth:sanctum so only the account's owner can read its data.
    // The controller additionally checks account->user_id === auth()->id() as
    // a second line of defence against horizontal privilege escalation.
    Route::middleware(["auth:sanctum", "throttle:60,1"])->group(function () {
        Route::get("/accounts/{account}/balance", [
            TransactionController::class,
            "balance",
        ]);
        Route::get("/accounts/{account}/transactions", [
            TransactionController::class,
            "transactions",
        ]);
    });
});
