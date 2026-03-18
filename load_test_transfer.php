<?php

$apiUrl = 'http://localhost:8080/api/v1/transfers';
$secret = 'local-dev-hmac-secret-key-change-in-production';
$senderId = '019c8b01-4ca3-72bc-80b8-84226c1f7b21'; // Alice
$receiverId = '019c8b01-4ca6-72b4-b01d-02f0e73e9767'; // Bob
$numRequests = 50;
$amount = '10.00';

$mh = curl_multi_init();
$curls = [];

for ($i = 0; $i < $numRequests; $i++) {
    $idempotencyKey = 'idempotency-key-' . bin2hex(random_bytes(16));
    $payload = json_encode([
        'sender_account_id' => $senderId,
        'receiver_account_id' => $receiverId,
        'amount' => $amount,
        'currency' => 'USD',
        'idempotency_key' => $idempotencyKey,
    ]);

    $signature = hash_hmac('sha256', $payload, $secret);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Signature: ' . $signature,
    ]);

    curl_multi_add_handle($mh, $ch);
    $curls[$i] = $ch;
}

$running = null;
do {
    curl_multi_exec($mh, $running);
} while ($running > 0);

foreach ($curls as $i => $ch) {
    $response = curl_multi_getcontent($ch);
    $info = curl_getinfo($ch);
    echo "Request $i: HTTP {$info['http_code']} - $response\n";
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}

curl_multi_close($mh);
