<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idempotency_key', 128)->unique();
            $table->uuid('sender_id')->index();
            $table->uuid('receiver_id')->index();
            $table->decimal('amount', 20, 2);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'reversed'])->default('pending');
            $table->string('currency', 3)->default('USD');
            $table->text('signature');
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->foreign('sender_id')->references('id')->on('accounts')->onDelete('restrict');
            $table->foreign('receiver_id')->references('id')->on('accounts')->onDelete('restrict');
        });

        // Ensure positive amounts only
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_amount_positive CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
