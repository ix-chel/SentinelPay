<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->string('event');
            $table->json('request_payload');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();
            $table->boolean('successful')->default(false);
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
