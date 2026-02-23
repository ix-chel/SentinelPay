<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->decimal('balance', 20, 2)->default(0.00);
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
        });

        // Ensure balance never goes negative at the DB level
        DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_balance_non_negative CHECK (balance >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
