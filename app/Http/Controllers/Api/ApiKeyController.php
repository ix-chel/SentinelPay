<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $merchant = $request->attributes->get('merchant');

        $request->validate([
            'scopes' => 'array',
            'rate_limit' => 'nullable|integer|min:1',
        ]);

        $plainTextKey = 'sp_live_'.Str::random(40);

        $apiKey = $merchant->apiKeys()->create([
            'hashed_key' => hash('sha256', $plainTextKey),
            'scopes' => $request->input('scopes', ['*']),
            'rate_limit' => $request->integer('rate_limit', 100),
        ]);

        return response()->json([
            'api_key' => $plainTextKey,
            'id' => $apiKey->id,
            'scopes' => $apiKey->scopes,
            'message' => 'Please copy this key now. It will not be shown again.',
        ], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $merchant = $request->attributes->get('merchant');
        $apiKey = $merchant->apiKeys()->findOrFail($id);
        $apiKey->delete();

        return response()->json(['message' => 'API Key revoked'], 200);
    }
}
