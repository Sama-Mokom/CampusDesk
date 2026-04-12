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
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
            ->constrained('users')
            ->cascadeOnDelete();
            $table->foreignId('request_type_id')
            ->constrained('request_types')
            ->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->enum('status',[
                'draft',
                'pending',
                'in_review',
                'forwarded',
                'ready',
                'collected',
                'rejected',
            ])->default('pending');
            $table->boolean('is_reopened')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_stages', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['request_type_id']);
        });
        Schema::dropIfExists('requests');
    }
};
