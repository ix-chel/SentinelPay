<?php

use App\Exceptions\AccountInactiveException;
use App\Exceptions\AccountNotFoundException;
use App\Exceptions\InsufficientFundsException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__.'/../routes/web.php',
        api:      __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health:   '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'hmac' => \App\Http\Middleware\VerifyHmacSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Domain exceptions → structured JSON responses
        $exceptions->render(function (InsufficientFundsException $e): JsonResponse {
            return response()->json([
                'error'   => 'INSUFFICIENT_FUNDS',
                'message' => $e->getMessage(),
            ], 422);
        });

        $exceptions->render(function (AccountInactiveException $e): JsonResponse {
            return response()->json([
                'error'   => 'ACCOUNT_INACTIVE',
                'message' => $e->getMessage(),
            ], 403);
        });

        $exceptions->render(function (AccountNotFoundException $e): JsonResponse {
            return response()->json([
                'error'   => 'ACCOUNT_NOT_FOUND',
                'message' => $e->getMessage(),
            ], 404);
        });
    })->create();

