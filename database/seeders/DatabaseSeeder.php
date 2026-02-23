<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with demo accounts for testing.
     */
    public function run(): void
    {
        // Create a demo user with two accounts for transfer testing
        $alice = User::factory()->create([
            'name'  => 'Alice Demo',
            'email' => 'alice@sentinelpay.io',
        ]);

        $bob = User::factory()->create([
            'name'  => 'Bob Demo',
            'email' => 'bob@sentinelpay.io',
        ]);

        Account::factory()->withBalance(10000.00)->create([
            'user_id'  => $alice->id,
            'currency' => 'USD',
        ]);

        Account::factory()->withBalance(5000.00)->create([
            'user_id'  => $bob->id,
            'currency' => 'USD',
        ]);

        $this->command->info('✅ Seeded: alice@sentinelpay.io (balance: $10,000), bob@sentinelpay.io (balance: $5,000)');
    }
}
