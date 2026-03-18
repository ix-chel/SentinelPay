<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with demo accounts and API tokens for testing.
     */
    public function run(): void
    {
        // ── Create demo users ─────────────────────────────────────────────────
        $alice = User::factory()->create([
            "name" => "Alice Demo",
            "email" => "alice@sentinelpay.io",
        ]);

        $bob = User::factory()->create([
            "name" => "Bob Demo",
            "email" => "bob@sentinelpay.io",
        ]);

        // ── Create accounts ───────────────────────────────────────────────────
        $aliceAccount = Account::factory()
            ->withBalance(10000.0)
            ->create([
                "user_id" => $alice->id,
                "currency" => "USD",
            ]);

        $bobAccount = Account::factory()
            ->withBalance(5000.0)
            ->create([
                "user_id" => $bob->id,
                "currency" => "USD",
            ]);

        // ── Issue Sanctum API tokens ──────────────────────────────────────────
        // Plain-text tokens are only available immediately after createToken().
        // They are hashed before storage and cannot be recovered later, so we
        // print them here for the developer to copy into their HTTP client or
        // the load-test script.
        $aliceToken = $alice->createToken("alice-demo-token")->plainTextToken;
        $bobToken = $bob->createToken("bob-demo-token")->plainTextToken;

        // ── Output summary ────────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info(
            "╔══════════════════════════════════════════════════════════════════╗",
        );
        $this->command->info(
            "║               SentinelPay — Demo Seed Complete                  ║",
        );
        $this->command->info(
            "╚══════════════════════════════════════════════════════════════════╝",
        );
        $this->command->newLine();

        $this->command->table(
            ["User", "Email", "Account ID", "Balance"],
            [
                [
                    "Alice",
                    "alice@sentinelpay.io",
                    $aliceAccount->id,
                    '$10,000.00 USD',
                ],
                [
                    "Bob",
                    "bob@sentinelpay.io",
                    $bobAccount->id,
                    ' $5,000.00 USD',
                ],
            ],
        );

        $this->command->newLine();
        $this->command->warn(
            "⚠️  Save these tokens now — they cannot be recovered after this point.",
        );
        $this->command->newLine();
        $this->command->line(
            "  <fg=cyan>Alice Bearer token:</> " . $aliceToken,
        );
        $this->command->line("  <fg=cyan>Bob   Bearer token:</> " . $bobToken);
        $this->command->newLine();
        $this->command->line("  Use as:  Authorization: Bearer <token>");
        $this->command->newLine();

        $this->command->info(
            "  Load-test UUIDs (copy into load_test_transfer.php):",
        );
        $this->command->line(
            '  $senderId   = \'' . $aliceAccount->id . '\'; // Alice',
        );
        $this->command->line(
            '  $receiverId = \'' . $bobAccount->id . '\';   // Bob',
        );
        $this->command->newLine();
    }
}
