<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $merchant = $request->attributes->get('merchant');

        return response()->json(
            WebhookEndpoint::query()->where('merchant_id', $merchant->id)->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $merchant = $request->attributes->get('merchant');

        $request->validate([
            'url' => 'required|url',
            'events' => 'required|array|min:1',
            'events.*' => 'string',
        ]);

        $endpoint = WebhookEndpoint::create([
            'merchant_id' => $merchant->id,
            'url' => $request->string('url')->toString(),
            'events' => $request->input('events'),
            'secret' => 'whsec_'.Str::random(32),
            'status' => 'active',
        ]);

        return response()->json($endpoint, 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $merchant = $request->attributes->get('merchant');
        $endpoint = WebhookEndpoint::query()
            ->where('merchant_id', $merchant->id)
            ->findOrFail($id);

        $endpoint->delete();

        return response()->json(['message' => 'Webhook endpoint deleted'], 200);
    }
}
