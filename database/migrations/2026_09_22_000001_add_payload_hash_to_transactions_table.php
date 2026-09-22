<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('payload_hash', 64)->nullable()->after('idempotency_key');
        });

        // Safe backfill for any existing transactions to maintain data integrity
        $transactions = DB::table('transactions')->whereNull('payload_hash')->get();
        foreach ($transactions as $tx) {
            $normalizedAmount = bcadd((string) $tx->amount, '0', 2);
            $normalizedCurrency = strtoupper(trim($tx->currency));
            $payload = [
                'amount' => $normalizedAmount,
                'currency' => $normalizedCurrency,
                'receiver_id' => $tx->receiver_id,
                'sender_id' => $tx->sender_id,
            ];
            ksort($payload);
            $canonicalString = json_encode($payload, JSON_THROW_ON_ERROR);
            $payloadHash = hash('sha256', $canonicalString);

            DB::table('transactions')
                ->where('id', $tx->id)
                ->update(['payload_hash' => $payloadHash]);
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('payload_hash');
        });
    }
};
