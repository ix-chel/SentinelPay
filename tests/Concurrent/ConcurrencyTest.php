<?php

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

// ──────────────────────────────────────────────────────────────────────────────
// True Concurrency Test — proc_open parallel PHP processes
//
// Unlike the sequential loop in TransferTest.php, this suite spawns N separate
// PHP processes that all connect to the same PostgreSQL database simultaneously.
// This is the only reliable way to exercise the SELECT FOR UPDATE locking path
// in PHP: each process is a distinct OS-level thread with its own database
// connection, so they genuinely compete for the same row locks.
//
// Prerequisites:
//   - PostgreSQL must be running and sentinelpay_test DB must exist
//   - proc_open() must be enabled (not disabled in php.ini)
//   - The artisan binary must be executable
//
// This test suite uses DatabaseMigrations (configured in tests/Pest.php) so
// factory-created rows are COMMITTED to the database — not hidden inside an
// outer test transaction — and are therefore visible to child processes.
// ──────────────────────────────────────────────────────────────────────────────

describe("True Concurrency — proc_open Parallel Processes", function () {
    // ── Helper: build the environment array for child processes ───────────────
    //
    // Child processes started via proc_open inherit the OS environment but NOT
    // PHPUnit's in-process env overrides (set via phpunit.xml <env> tags). We
    // re-apply them explicitly so every child connects to the test database with
    // the correct credentials and configuration.

    function buildChildEnv(): array
    {
        // $_SERVER contains non-string values (argv is an array, argc is an int,
        // etc.) that proc_open cannot convert to OS environment strings, causing
        // "Array to string conversion" errors. We therefore build the environment
        // explicitly using getenv() for system variables we actually need, and
        // override everything Laravel-specific ourselves.

        $env = [
            // ── System paths — PHP and PostgreSQL need these on Windows ───────
            "PATH" => getenv("PATH") ?: "",
            "SystemRoot" => getenv("SystemRoot") ?: "",
            "WINDIR" => getenv("WINDIR") ?: "",
            "ComSpec" => getenv("ComSpec") ?: "",
            "TEMP" => getenv("TEMP") ?: sys_get_temp_dir(),
            "TMP" => getenv("TMP") ?: sys_get_temp_dir(),

            // ── Force testing environment ─────────────────────────────────────
            "APP_ENV" => "testing",
            "APP_DEBUG" => "false",

            // ── Database — must point at the test DB, not production ──────────
            "DB_CONNECTION" => env("DB_CONNECTION", "pgsql"),
            "DB_HOST" => env("DB_HOST", "127.0.0.1"),
            "DB_PORT" => (string) env("DB_PORT", "5432"),
            "DB_DATABASE" => env("DB_DATABASE", "sentinelpay_test"),
            "DB_USERNAME" => env("DB_USERNAME", "postgres"),
            "DB_PASSWORD" => (string) env("DB_PASSWORD", ""),

            // ── Use array cache so each child has isolated idempotency state ──
            "CACHE_STORE" => "array",

            // ── Run jobs synchronously — no broker needed during tests ────────
            "QUEUE_CONNECTION" => "sync",

            // ── Session / broadcast — not used in CLI context ─────────────────
            "SESSION_DRIVER" => "array",
            "BROADCAST_CONNECTION" => "null",

            // ── HMAC secret — must match what the command uses internally ─────
            "HMAC_SECRET" => (string) env(
                "HMAC_SECRET",
                "test-hmac-secret-key-for-phpunit",
            ),

            // ── Suppress ANSI colour codes so JSON parsing is not confused ────
            "NO_COLOR" => "1",
        ];

        // Strip any keys whose value is an empty string to keep the env lean
        // (getenv() returns false for missing vars; we defaulted those to "").
        return array_filter($env, fn($v) => $v !== "");
    }

    // ── Helper: spawn one child process ──────────────────────────────────────

    function spawnTransferProcess(
        string $senderId,
        string $receiverId,
        string $amount,
        string $currency,
        string $idempotencyKey,
    ): array {
        $phpBinary = PHP_BINARY;
        $artisanPath = base_path("artisan");

        $cmd = implode(" ", [
            escapeshellarg($phpBinary),
            escapeshellarg($artisanPath),
            "sentinelpay:test-transfer",
            escapeshellarg($senderId),
            escapeshellarg($receiverId),
            escapeshellarg($amount),
            escapeshellarg($currency),
            escapeshellarg($idempotencyKey),
            "--no-ansi",
        ]);

        $descriptors = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout — JSON result line
            2 => ["pipe", "w"], // stderr — Laravel logs / errors
        ];

        $process = proc_open($cmd, $descriptors, $pipes, null, buildChildEnv());

        if (!is_resource($process)) {
            throw new \RuntimeException(
                "proc_open failed for idempotency key: {$idempotencyKey}",
            );
        }

        // Close stdin immediately — the command does not read from it
        fclose($pipes[0]);

        return [
            "process" => $process,
            "pipes" => $pipes,
            "key" => $idempotencyKey,
        ];
    }

    // ── Helper: wait for a spawned process and collect its result ─────────────

    function collectProcessResult(array $proc): array
    {
        $stdout = stream_get_contents($proc["pipes"][1]);
        $stderr = stream_get_contents($proc["pipes"][2]);

        fclose($proc["pipes"][1]);
        fclose($proc["pipes"][2]);

        $exitCode = proc_close($proc["process"]);

        // The command writes a single JSON line to stdout
        $parsed = json_decode(trim($stdout), true);

        return [
            "exit_code" => $exitCode,
            "key" => $proc["key"],
            "stdout" => trim($stdout),
            "stderr" => trim($stderr),
            "parsed" => $parsed,
            "status" => $parsed["status"] ?? "error",
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 1: 10 parallel processes, $150 each from a $1,000 balance
    //
    // Expected outcome:
    //   • 6 transfers succeed   (6 × $150 = $900 ≤ $1,000)
    //   • 4 transfers fail with INSUFFICIENT_FUNDS
    //   • sender.balance + receiver.balance = $1,000  (money conserved)
    //   • sender.balance ≥ 0                          (no overdraft)
    //   • ledger entries = successCount × 2
    //   • zero transactions stuck in pending/processing
    // ─────────────────────────────────────────────────────────────────────────

    it(
        "prevents double-spend when 10 processes race to debit the same account",
        function () {
            if (!function_exists("proc_open")) {
                $this->markTestSkipped(
                    "proc_open is not available in this environment.",
                );
            }

            $initialBalance = "1000.00";
            $transferAmount = "150.00";
            $concurrency = 10;

            // DatabaseMigrations (set in tests/Pest.php for Concurrent/) means
            // factory creates() here commit their rows to the real test database,
            // making them visible to the child processes that follow.
            $sender = Account::factory()
                ->withBalance($initialBalance)
                ->create(["currency" => "USD"]);
            $receiver = Account::factory()
                ->withBalance("0.00")
                ->create(["currency" => "USD"]);

            // ── Spawn all processes in parallel ───────────────────────────────────
            $spawned = [];

            for ($i = 0; $i < $concurrency; $i++) {
                $spawned[] = spawnTransferProcess(
                    senderId: $sender->id,
                    receiverId: $receiver->id,
                    amount: $transferAmount,
                    currency: "USD",
                    idempotencyKey: "race-test-" . Str::uuid()->toString(),
                );
            }

            // ── Collect results — all processes are already running concurrently ──
            $results = [];
            $successCount = 0;
            $failCount = 0;
            $errorCount = 0;

            foreach ($spawned as $proc) {
                $result = collectProcessResult($proc);
                $results[] = $result;

                match ($result["status"]) {
                    "success" => $successCount++,
                    "failed" => $failCount++,
                    default => $errorCount++,
                };
            }

            // ── Assert: no unexpected errors from child processes ─────────────────
            $errorDetails = array_filter(
                $results,
                fn($r) => $r["status"] === "error",
            );

            expect($errorCount)->toBe(
                0,
                implode(
                    "\n",
                    array_map(
                        fn(
                            $r,
                        ) => "Process error [{$r["key"]}]: stdout={$r["stdout"]} stderr={$r["stderr"]}",
                        $errorDetails,
                    ),
                ),
            );

            // ── Assert: financial integrity ───────────────────────────────────────
            $sender->refresh();
            $receiver->refresh();

            $finalSenderBalance = bcadd((string) $sender->balance, "0", 2);
            $finalReceiverBalance = bcadd((string) $receiver->balance, "0", 2);
            $totalTransferred = bcmul(
                (string) $successCount,
                $transferAmount,
                2,
            );
            $totalMoney = bcadd($finalSenderBalance, $finalReceiverBalance, 2);

            // 1. No negative balance (overdraft protection)
            expect(bccomp($finalSenderBalance, "0", 2))->toBeGreaterThanOrEqual(
                0,
                "Sender balance went negative: {$finalSenderBalance}",
            );

            // 2. Total money in system is conserved (no money created or destroyed)
            expect($totalMoney)->toBe(
                $initialBalance,
                "Money conservation violated: {$finalSenderBalance} + {$finalReceiverBalance} ≠ {$initialBalance}",
            );

            // 3. Sender balance = initial − (successCount × amount)
            $expectedSenderBalance = bcsub(
                $initialBalance,
                $totalTransferred,
                2,
            );
            expect($finalSenderBalance)->toBe(
                $expectedSenderBalance,
                "Sender balance mismatch. Expected {$expectedSenderBalance}, got {$finalSenderBalance}",
            );

            // 4. Receiver balance = successCount × amount
            expect($finalReceiverBalance)->toBe(
                $totalTransferred,
                "Receiver balance mismatch. Expected {$totalTransferred}, got {$finalReceiverBalance}",
            );

            // 5. Ledger has exactly 2 entries per successful transfer (1 debit + 1 credit)
            $ledgerCount = Ledger::count();
            expect($ledgerCount)->toBe(
                $successCount * 2,
                "Expected {$successCount}×2 = " .
                    $successCount * 2 .
                    " ledger entries, got {$ledgerCount}",
            );

            // 6. No transactions left in a non-terminal state
            $stuckCount = Transaction::whereIn("status", [
                Transaction::STATUS_PENDING,
                Transaction::STATUS_PROCESSING,
            ])->count();

            expect($stuckCount)->toBe(
                0,
                "{$stuckCount} transaction(s) are stuck in a non-terminal state",
            );

            // 7. Every attempt has a definitive outcome (success or clean rejection)
            expect($successCount + $failCount)->toBe(
                $concurrency,
                "Not all {$concurrency} attempts resolved: {$successCount} success + {$failCount} fail ≠ {$concurrency}",
            );

            // Diagnostic dump — visible with --verbose
            dump([
                "initial_balance" => $initialBalance,
                "transfer_amount" => $transferAmount,
                "concurrent_processes" => $concurrency,
                "successful_transfers" => $successCount,
                "failed_transfers" => $failCount,
                "final_sender_balance" => $finalSenderBalance,
                "final_receiver_balance" => $finalReceiverBalance,
                "total_money_in_system" => $totalMoney,
                "ledger_entries" => $ledgerCount,
            ]);
        },
    );

    // ─────────────────────────────────────────────────────────────────────────
    // Test 2: 20 processes, opposite-direction transfers (A→B and B→A)
    //
    // This is the classic deadlock scenario. Without sorted UUID locking,
    // Transfer A (alice→bob) and Transfer B (bob→alice) would acquire locks in
    // opposite orders and deadlock. The sorted locking in TransferService
    // prevents this: both always lock the lower UUID first.
    //
    // Expected outcome: all 20 processes resolve without deadlock or timeout.
    // ─────────────────────────────────────────────────────────────────────────

    it(
        "prevents deadlock when 20 processes race with transfers in both directions",
        function () {
            if (!function_exists("proc_open")) {
                $this->markTestSkipped(
                    "proc_open is not available in this environment.",
                );
            }

            $concurrency = 20; // 10 A→B + 10 B→A
            $amount = "10.00";

            $accountA = Account::factory()
                ->withBalance("5000.00")
                ->create(["currency" => "USD"]);
            $accountB = Account::factory()
                ->withBalance("5000.00")
                ->create(["currency" => "USD"]);

            $totalBefore = bcadd("5000.00", "5000.00", 2); // $10,000.00

            // ── Spawn 10 A→B and 10 B→A processes concurrently ───────────────────
            $spawned = [];

            for ($i = 0; $i < $concurrency / 2; $i++) {
                // A → B
                $spawned[] = spawnTransferProcess(
                    senderId: $accountA->id,
                    receiverId: $accountB->id,
                    amount: $amount,
                    currency: "USD",
                    idempotencyKey: "deadlock-ab-" . Str::uuid()->toString(),
                );

                // B → A
                $spawned[] = spawnTransferProcess(
                    senderId: $accountB->id,
                    receiverId: $accountA->id,
                    amount: $amount,
                    currency: "USD",
                    idempotencyKey: "deadlock-ba-" . Str::uuid()->toString(),
                );
            }

            // ── Collect all results ───────────────────────────────────────────────
            $successCount = 0;
            $failCount = 0;
            $errorCount = 0;

            foreach ($spawned as $proc) {
                $result = collectProcessResult($proc);
                match ($result["status"]) {
                    "success" => $successCount++,
                    "failed" => $failCount++,
                    default => $errorCount++,
                };
            }

            // ── Assert: no deadlock-induced errors ────────────────────────────────
            expect($errorCount)->toBe(
                0,
                "{$errorCount} process(es) terminated with an unexpected error — possible deadlock",
            );

            // ── Assert: money conserved across both accounts ──────────────────────
            $accountA->refresh();
            $accountB->refresh();

            $totalAfter = bcadd(
                bcadd((string) $accountA->balance, "0", 2),
                bcadd((string) $accountB->balance, "0", 2),
                2,
            );

            expect($totalAfter)->toBe(
                $totalBefore,
                "Total money changed: before={$totalBefore}, after={$totalAfter}",
            );

            // ── Assert: no stuck transactions ─────────────────────────────────────
            $stuck = Transaction::whereIn("status", [
                Transaction::STATUS_PENDING,
                Transaction::STATUS_PROCESSING,
            ])->count();

            expect($stuck)->toBe(
                0,
                "{$stuck} transaction(s) stuck in a non-terminal state",
            );

            // All attempts resolved definitively
            expect($successCount + $failCount)->toBe($concurrency);

            dump([
                "concurrent_processes" => $concurrency,
                "a_to_b_processes" => $concurrency / 2,
                "b_to_a_processes" => $concurrency / 2,
                "successful_transfers" => $successCount,
                "failed_transfers" => $failCount,
                "account_a_balance" => bcadd(
                    (string) $accountA->balance,
                    "0",
                    2,
                ),
                "account_b_balance" => bcadd(
                    (string) $accountB->balance,
                    "0",
                    2,
                ),
                "total_money_before" => $totalBefore,
                "total_money_after" => $totalAfter,
            ]);
        },
    );
});
