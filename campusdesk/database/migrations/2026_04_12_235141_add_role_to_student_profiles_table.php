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
    Schema::table('student_profiles', function (Blueprint $table) {
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('faculty_id')->constrained()->cascadeOnDelete();
        $table->foreignId('department_id')->constrained()->cascadeOnDelete();
        $table->foreignId('programme_id')->constrained()->cascadeOnDelete();
        $table->string('matricule')->unique();
        $table->enum('level', ['L100', 'L200', 'L300', 'L400', 'L500', 'L600']);
        $table->enum('status', ['active', 'on_leave', 'graduated', 'suspended'])
              ->default('active');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            //
        });
    }
};
