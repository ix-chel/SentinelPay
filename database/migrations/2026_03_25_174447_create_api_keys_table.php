<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('hashed_key', 64);
            $table->json('scopes');
            $table->unsignedInteger('rate_limit')->default(100);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('hashed_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
