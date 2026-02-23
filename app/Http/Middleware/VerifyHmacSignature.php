<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates HMAC-SHA256 signatures on incoming payment requests.
 *
 * The client MUST include the header:
 *   X-Signature: <hmac_sha256_hex(request_body, HMAC_SECRET)>
 *
 * This prevents payload tampering in transit.
 */
class VerifyHmacSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature');

        if (empty($signature)) {
            return response()->json([
                'error'   => 'Missing X-Signature header.',
                'message' => 'All payment requests must include an HMAC-SHA256 signature.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $rawBody = $request->getContent();
        $secret  = config('sentinelpay.hmac_secret');

        if (empty($secret)) {
            abort(500, 'HMAC_SECRET is not configured.');
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        // Use hash_equals for timing-safe comparison to prevent timing attacks
        if (! hash_equals($expected, $signature)) {
            return response()->json([
                'error'   => 'Invalid signature.',
                'message' => 'The X-Signature header does not match the request payload.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
