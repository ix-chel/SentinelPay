<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Handles API token authentication via Laravel Sanctum.
 *
 * POST /api/v1/auth/login  — Exchange credentials for a Bearer token
 * POST /api/v1/auth/logout — Revoke the current token
 */
class AuthController extends Controller
{
    /**
     * Authenticate a user and issue a Sanctum API token.
     *
     * Returns a plain-text token the client must include as:
     *   Authorization: Bearer <token>
     *
     * Throttled to 5 attempts per minute (defined in routes/api.php).
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ])->status(401);
        }

        /** @var \App\Models\User $user */
        $user  = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'Authenticated successfully.',
            'data'    => [
                'token'      => $token,
                'token_type' => 'Bearer',
                'user'       => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    /**
     * Revoke the token that was used to authenticate the current request.
     *
     * Requires:  Authorization: Bearer <token>
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Token revoked. You have been logged out.',
        ]);
    }
}
