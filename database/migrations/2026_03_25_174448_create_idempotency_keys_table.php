<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key');
            $table->string('request_path');
            $table->unsignedSmallInteger('response_code');
            $table->json('response_body');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
