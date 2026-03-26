<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_after', 18, 2);
            $table->char('currency', 3);
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
