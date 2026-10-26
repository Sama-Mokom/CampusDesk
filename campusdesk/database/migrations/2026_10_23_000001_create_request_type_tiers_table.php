<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('request_type_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_type_id')->constrained('request_types')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('fee_amount'); // amount in whole XAF
            $table->string('currency', 3)->default('XAF');
            $table->unsignedSmallInteger('turnaround_days');
            $table->unsignedTinyInteger('priority')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->timestamps();

            $table->unique(['request_type_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_type_tiers');
    }
};
