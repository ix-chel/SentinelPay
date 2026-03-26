<?php

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Transfer;
use Illuminate\Support\Str;

describe('true concurrency', function () {
    function buildChildEnv(): array
    {
        $env = [
            'PATH' => getenv('PATH') ?: '',
            'SystemRoot' => getenv('SystemRoot') ?: '',
            'WINDIR' => getenv('WINDIR') ?: '',
            'ComSpec' => getenv('ComSpec') ?: '',
            'TEMP' => getenv('TEMP') ?: sys_get_temp_dir(),
            'TMP' => getenv('TMP') ?: sys_get_temp_dir(),
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => env('DB_CONNECTION', 'pgsql'),
            'DB_HOST' => env('DB_HOST', 'postgres'),
            'DB_PORT' => (string) env('DB_PORT', '5432'),
            'DB_DATABASE' => env('DB_DATABASE', 'sentinelpay_test'),
            'DB_USERNAME' => env('DB_USERNAME', 'sentinelpay'),
            'DB_PASSWORD' => (string) env('DB_PASSWORD', 'secret'),
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'BROADCAST_CONNECTION' => 'null',
            'HMAC_SECRET' => (string) env('HMAC_SECRET', 'test-hmac-secret-key-for-phpunit'),
            'NO_COLOR' => '1',
        ];

        return array_filter($env, fn ($value) => $value !== '');
    }

    function spawnTransferProcess(
        string $senderId,
        string $receiverId,
        string $amount,
        string $currency,
        string $idempotencyKey,
    ): array {
        $phpBinary = PHP_BINARY;
        $artisanPath = base_path('artisan');

        $command = implode(' ', [
            escapeshellarg($phpBinary),
            escapeshellarg($artisanPath),
            'sentinelpay:test-transfer',
            escapeshellarg($senderId),
            escapeshellarg($receiverId),
            escapeshellarg($amount),
            escapeshellarg($currency),
            escapeshellarg($idempotencyKey),
            '--no-ansi',
        ]);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, null, buildChildEnv());

        if (! is_resource($process)) {
            throw new RuntimeException("proc_open failed for idempotency key: {$idempotencyKey}");
        }

        fclose($pipes[0]);

        return [
            'process' => $process,
            'pipes' => $pipes,
            'key' => $idempotencyKey,
        ];
    }

    function collectProcessResult(array $process): array
    {
        $stdout = stream_get_contents($process['pipes'][1]);
        $stderr = stream_get_contents($process['pipes'][2]);

        fclose($process['pipes'][1]);
        fclose($process['pipes'][2]);

        $exitCode = proc_close($process['process']);
        $parsed = json_decode(trim($stdout), true);

        return [
            'exit_code' => $exitCode,
            'key' => $process['key'],
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
            'parsed' => $parsed,
            'status' => $parsed['status'] ?? 'error',
        ];
    }

    it('prevents double-spend when 10 processes race to debit the same account', function () {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is not available in this environment.');
        }

        $sender = Account::factory()->withBalance('1000.00')->create(['currency' => 'USD']);
        $receiver = Account::factory()->withBalance('0.00')->create([
            'merchant_id' => $sender->merchant_id,
            'currency' => 'USD',
        ]);

        $spawned = [];

        for ($i = 0; $i < 10; $i++) {
            $spawned[] = spawnTransferProcess(
                senderId: $sender->id,
                receiverId: $receiver->id,
                amount: '150.00',
                currency: 'USD',
                idempotencyKey: 'race-test-'.Str::uuid()->toString(),
            );
        }

        $successCount = 0;
        $failCount = 0;
        $errorCount = 0;

        foreach ($spawned as $process) {
            $result = collectProcessResult($process);

            match ($result['status']) {
                'success' => $successCount++,
                'failed' => $failCount++,
                default => $errorCount++,
            };
        }

        $sender->refresh();
        $receiver->refresh();

        $finalSenderBalance = bcadd((string) $sender->balance, '0', 2);
        $finalReceiverBalance = bcadd((string) $receiver->balance, '0', 2);
        $totalTransferred = bcmul((string) $successCount, '150.00', 2);
        $totalMoney = bcadd($finalSenderBalance, $finalReceiverBalance, 2);

        expect($errorCount)->toBe(0);
        expect(bccomp($finalSenderBalance, '0', 2))->toBeGreaterThanOrEqual(0);
        expect($totalMoney)->toBe('1000.00');
        expect($finalSenderBalance)->toBe(bcsub('1000.00', $totalTransferred, 2));
        expect($finalReceiverBalance)->toBe($totalTransferred);
        expect(LedgerEntry::count())->toBe($successCount * 2);
        expect(Transfer::whereIn('status', [
            Transfer::STATUS_PENDING,
            Transfer::STATUS_PROCESSING,
        ])->count())->toBe(0);
        expect($successCount + $failCount)->toBe(10);
    });
});
