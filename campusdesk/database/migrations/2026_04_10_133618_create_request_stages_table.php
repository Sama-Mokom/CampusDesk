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
        Schema::create('request_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence_order');
            $table->enum('status', ['pending', 'in_review', "approved", 'rejected'])
            ->default('pending');
            $table->unsignedBigInteger('handled_by')->nullable()->foreignId('handled_by')
            ->references('id')
            ->on('users')
            ->nullOnDelete()
            ->nullOnUpdate();
            $table->text('staff_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
          Schema::table('request_stages', function (Blueprint $table) {
            $table->dropForeign(['handled_by']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['request_id']);
        });
        Schema::dropIfExists('request_stages');
    }
};
