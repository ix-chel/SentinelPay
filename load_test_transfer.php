<?php

/**
 * SentinelPay — Concurrent Load Test Script
 * ==========================================
 * Fires N parallel HTTP requests against the transfer endpoint using PHP's
 * curl_multi API, exercising the pessimistic locking and idempotency layers
 * under real network concurrency.
 *
 * Usage:
 *   php load_test_transfer.php <sender_uuid> <receiver_uuid> [options]
 *
 * Required arguments:
 *   <sender_uuid>    UUID of the sender account   (get from db:seed output)
 *   <receiver_uuid>  UUID of the receiver account (get from db:seed output)
 *
 * Options:
 *   --secret=<key>    HMAC secret  (default: local-dev-hmac-secret-key-change-in-production)
 *   --url=<url>       API base URL (default: http://localhost:8080/api/v1/transfers)
 *   --amount=<amt>    Transfer amount per request (default: 10.00)
 *   --requests=<n>    Number of parallel requests  (default: 50)
 *
 * Example (after db:seed):
 *   php load_test_transfer.php \
 *       "019c8b01-4ca3-72bc-80b8-84226c1f7b21" \
 *       "019c8b01-4ca6-72b4-b01d-02f0e73e9767" \
 *       --secret="my-super-secret-32-char-minimum-key" \
 *       --requests=100 \
 *       --amount=5.00
 *
 * Expected output:
 *   - HTTP 201 for successful transfers
 *   - HTTP 422 for insufficient-funds rejections once the balance is exhausted
 *   - Every request gets a unique idempotency key, so none should return a
 *     cached 201 — each one is a genuinely new transfer attempt.
 */

// ── Parse required positional arguments ──────────────────────────────────────

if ($argc < 3 || in_array($argv[1] ?? "", ["-h", "--help"], true)) {
    fwrite(
        STDERR,
        <<<USAGE

          Usage: php load_test_transfer.php <sender_uuid> <receiver_uuid> [options]

          Required:
            sender_uuid    UUID of the sender account
            receiver_uuid  UUID of the receiver account

          Options:
            --secret=<key>      HMAC_SECRET value          (default: local-dev-hmac-secret-key-change-in-production)
            --url=<url>         Full transfer endpoint URL  (default: http://localhost:8080/api/v1/transfers)
            --amount=<decimal>  Amount per transfer         (default: 10.00)
            --requests=<n>      Number of parallel requests (default: 50)

          Example:
            php load_test_transfer.php \\
                "019c8b01-0000-0000-0000-000000000001" \\
                "019c8b01-0000-0000-0000-000000000002" \\
                --secret="my-real-hmac-secret" \\
                --requests=100

        USAGE
        ,
    );
    exit(1);
}

$senderId = $argv[1];
$receiverId = $argv[2];

// ── Parse optional named arguments ───────────────────────────────────────────

$options = [
    "secret" => "local-dev-hmac-secret-key-change-in-production",
    "url" => "http://localhost:8080/api/v1/transfers",
    "amount" => "10.00",
    "requests" => "50",
];

for ($i = 3; $i < $argc; $i++) {
    if (preg_match('/^--(\w+)=(.+)$/', $argv[$i], $m)) {
        $key = $m[1];
        if (array_key_exists($key, $options)) {
            $options[$key] = $m[2];
        } else {
            fwrite(STDERR, "Unknown option: --{$key}\n");
            exit(1);
        }
    }
}

$apiUrl = $options["url"];
$secret = $options["secret"];
$amount = $options["amount"];
$numRequests = (int) $options["requests"];

// ── Validate inputs ───────────────────────────────────────────────────────────

$uuidPattern =
    '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

if (!preg_match($uuidPattern, $senderId)) {
    fwrite(
        STDERR,
        "Error: sender_uuid does not look like a valid UUID: {$senderId}\n",
    );
    exit(1);
}

if (!preg_match($uuidPattern, $receiverId)) {
    fwrite(
        STDERR,
        "Error: receiver_uuid does not look like a valid UUID: {$receiverId}\n",
    );
    exit(1);
}

if ($senderId === $receiverId) {
    fwrite(STDERR, "Error: sender_uuid and receiver_uuid must be different.\n");
    exit(1);
}

if (!is_numeric($amount) || (float) $amount <= 0) {
    fwrite(
        STDERR,
        "Error: --amount must be a positive decimal (e.g. 10.00).\n",
    );
    exit(1);
}

if ($numRequests < 1 || $numRequests > 1000) {
    fwrite(STDERR, "Error: --requests must be between 1 and 1000.\n");
    exit(1);
}

// ── Print run summary ─────────────────────────────────────────────────────────

echo PHP_EOL;
echo "╔══════════════════════════════════════════════════════════════╗" .
    PHP_EOL;
echo "║           SentinelPay — Concurrent Load Test                 ║" .
    PHP_EOL;
echo "╚══════════════════════════════════════════════════════════════╝" .
    PHP_EOL;
echo PHP_EOL;
echo "  Endpoint  : {$apiUrl}" . PHP_EOL;
echo "  Sender    : {$senderId}" . PHP_EOL;
echo "  Receiver  : {$receiverId}" . PHP_EOL;
echo "  Amount    : {$amount} USD per request" . PHP_EOL;
echo "  Requests  : {$numRequests} (all fired in parallel via curl_multi)" .
    PHP_EOL;
echo PHP_EOL;

// ── Build and fire all requests in parallel ───────────────────────────────────

$mh = curl_multi_init();
$curls = [];

for ($i = 0; $i < $numRequests; $i++) {
    // Every request gets a unique idempotency key so each is treated as a
    // genuinely new transfer — this tests the locking path, not the cache path.
    $idempotencyKey = "load-test-" . bin2hex(random_bytes(16));

    $payload = json_encode([
        "sender_account_id" => $senderId,
        "receiver_account_id" => $receiverId,
        "amount" => $amount,
        "currency" => "USD",
        "idempotency_key" => $idempotencyKey,
    ]);

    $signature = hash_hmac("sha256", $payload, $secret);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Accept: application/json",
            "X-Signature: " . $signature,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    curl_multi_add_handle($mh, $ch);
    $curls[$i] = ["handle" => $ch, "idempotency_key" => $idempotencyKey];
}

// ── Execute all handles concurrently ─────────────────────────────────────────

$running = null;
do {
    $status = curl_multi_exec($mh, $running);
    if ($running) {
        curl_multi_select($mh); // Block until activity rather than busy-looping
    }
} while ($running > 0 && $status === CURLM_OK);

// ── Collect and display results ───────────────────────────────────────────────

$statusCounts = [];
$errors = [];

foreach ($curls as $i => $meta) {
    $ch = $meta["handle"];
    $response = curl_multi_getcontent($ch);
    $info = curl_getinfo($ch);
    $curlErr = curl_error($ch);

    $httpCode = $info["http_code"];
    $statusCounts[$httpCode] = ($statusCounts[$httpCode] ?? 0) + 1;

    if ($curlErr) {
        $errors[] = "Request {$i}: cURL error — {$curlErr}";
    } elseif ($httpCode >= 500) {
        $decoded = json_decode($response, true);
        $message = $decoded["message"] ?? $response;
        $errors[] = "Request {$i}: HTTP {$httpCode} — {$message}";
    }

    // Print individual result line
    $icon = match (true) {
        $httpCode === 201 => "✓",
        $httpCode === 422
            => "↩", // insufficient funds — expected once balance runs out
        $httpCode >= 400 => "✗",
        default => "?",
    };

    $decoded = json_decode($response, true);
    $txnId = $decoded["data"]["transaction_id"] ?? "-";
    $errCode = $decoded["error"] ?? "-";

    printf(
        "  [%3d] %s  HTTP %-3d  | %-36s | key=%.24s…\n",
        $i + 1,
        $icon,
        $httpCode,
        $httpCode === 201 ? "txn={$txnId}" : "err={$errCode}",
        $meta["idempotency_key"],
    );

    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}

curl_multi_close($mh);

// ── Summary ───────────────────────────────────────────────────────────────────

echo PHP_EOL;
echo "══ Results ══════════════════════════════════════════════════════" .
    PHP_EOL;
echo PHP_EOL;

ksort($statusCounts);
foreach ($statusCounts as $code => $count) {
    $label = match ($code) {
        201 => "Transfer completed",
        401 => "Missing signature",
        403 => "Invalid signature / inactive account",
        422 => "Insufficient funds / validation error",
        429 => "Rate limited",
        500 => "Server error",
        default => "Other",
    };
    printf("  HTTP %-3d  %-40s  %d request(s)\n", $code, $label, $count);
}

echo PHP_EOL;

if (!empty($errors)) {
    echo "══ Errors ═══════════════════════════════════════════════════════" .
        PHP_EOL;
    echo PHP_EOL;
    foreach ($errors as $err) {
        echo "  {$err}" . PHP_EOL;
    }
    echo PHP_EOL;
}

$successful = $statusCounts[201] ?? 0;
$totalCharged = bcmul((string) $successful, $amount, 2);

echo "  Total successful transfers : {$successful}" . PHP_EOL;
echo "  Total amount debited       : \${$totalCharged} USD" . PHP_EOL;
echo PHP_EOL;
