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
        //
        Schema::table('status_history', function (Blueprint $table) {
            $table->dropForeign(['changed_by']);
            $table->dropColumn('changed_by');
        });

        // 2. Re-create as nullable FK referencing staff_profiles — nullable so audit
        //    history survives a staff record being deleted (nullOnDelete).
        Schema::table('status_history', function (Blueprint $table) {
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('staff_profiles')
                ->nullOnDelete();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
       Schema::table('status_history', function (Blueprint $table) {
            $table->dropForeign(['changed_by']);
            $table->dropColumn('changed_by');
        });

        // 2. Restore original foreign key pointing back to users (NULLABLE)
        Schema::table('status_history', function (Blueprint $table) {
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }
};
