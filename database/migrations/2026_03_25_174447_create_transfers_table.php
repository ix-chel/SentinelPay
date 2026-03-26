<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('source_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('destination_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('idempotency_key');
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3);
            $table->string('status');
            $table->string('signature')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
