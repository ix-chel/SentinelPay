<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('webhook_deliveries')->delete();
        DB::table('webhook_endpoints')->delete();
        DB::table('ledger_entries')->delete();
        DB::table('transfers')->delete();
        DB::table('idempotency_keys')->delete();
        DB::table('api_keys')->delete();
        DB::table('accounts')->delete();
        DB::table('merchants')->delete();

        $merchant = Merchant::create([
            'name' => 'Acme Corp',
            'email' => 'admin@acmecorp.com',
        ]);

        $plainTextKey = 'sp_live_demo1234567890';

        ApiKey::create([
            'merchant_id' => $merchant->id,
            'hashed_key' => hash('sha256', $plainTextKey),
            'scopes' => ['*'],
            'rate_limit' => 1000,
        ]);

        $merchant->accounts()->create([
            'name' => 'Main Operating Account',
            'currency' => 'USD',
            'balance' => 10000.00,
            'is_active' => true,
        ]);

        $merchant->accounts()->create([
            'name' => 'Reserve Account',
            'currency' => 'USD',
            'balance' => 50000.00,
            'is_active' => true,
        ]);

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('Test Merchant: '.$merchant->name);
        $this->command->info('Test API Key: '.$plainTextKey);
    }
}
