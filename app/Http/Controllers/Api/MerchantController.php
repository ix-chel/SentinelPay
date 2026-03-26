<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MerchantController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:merchants,email',
        ]);

        $merchant = Merchant::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
        ]);

        $plainTextKey = 'sp_live_'.Str::random(40);

        $merchant->apiKeys()->create([
            'hashed_key' => hash('sha256', $plainTextKey),
            'scopes' => ['*'],
            'rate_limit' => 100,
        ]);

        return response()->json([
            'merchant' => $merchant,
            'initial_api_key' => $plainTextKey,
            'message' => 'Copy this API Key now. It will not be shown again.',
        ], 201);
    }
}
