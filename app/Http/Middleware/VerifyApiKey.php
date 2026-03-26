<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next, ...$scopes): Response
    {
        $key = $request->header('X-API-Key') ?? $request->bearerToken();
        if (! $key) {
            return response()->json(['error' => 'API Key is missing'], 401);
        }

        $hashedKey = hash('sha256', $key);
        $apiKey = ApiKey::where('hashed_key', $hashedKey)->first();

        if (! $apiKey) {
            return response()->json(['error' => 'Invalid API Key'], 401);
        }

        if (! empty($scopes)) {
            $hasScope = false;
            foreach ($scopes as $scope) {
                if (in_array($scope, $apiKey->scopes ?? []) || in_array('*', $apiKey->scopes ?? [])) {
                    $hasScope = true;
                    break;
                }
            }
            if (! $hasScope) {
                return response()->json(['error' => 'Insufficient scopes'], 403);
            }
        }

        $limit = $apiKey->rate_limit ?? 100;
        if (RateLimiter::tooManyAttempts('api-key:'.$apiKey->id, $limit)) {
            return response()->json(['error' => 'Too Many Requests'], 429);
        }
        RateLimiter::hit('api-key:'.$apiKey->id);

        $apiKey->update(['last_used_at' => now()]);
        $request->attributes->add(['merchant' => $apiKey->merchant, 'api_key' => $apiKey]);

        return $next($request);
    }
}
