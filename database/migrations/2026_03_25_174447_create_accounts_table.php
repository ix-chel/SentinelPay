<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('balance', 18, 2)->default(0);
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['merchant_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
