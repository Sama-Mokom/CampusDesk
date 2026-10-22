<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->string('event_name');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->unsignedBigInteger('aggregate_sequence');
            $table->string('delivery_status')->default('pending');
            $table->json('payload');
            $table->json('audiences');
            $table->timestamps();

            $table->unique(['request_id', 'aggregate_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_events');
    }
};
