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
            // Drop the original enum column
            $table->dropColumn('level');
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            // Re-add the enum column with updated values
            $table->enum('level', ['100', '200', '300', '400', '500', '600'])
                  ->after('matricule'); // Places it back in the original position
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            // Drop the new enum column
            $table->dropColumn('level');
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            // Revert back to the original enum values
            $table->enum('level', ['L100', 'L200', 'L300', 'L400', 'L500', 'L600'])
                  ->after('matricule');
        });
    }
};