<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The source markdown has many departments sharing the same programme code
     * (e.g. code 'INT' appears in GS/PB, INTERPRETATION, and TRANSLATION under ASTI;
     * code 'TRA' appears in the same three departments; code 'ZOO' appears multiple
     * times in ANIMAL BIOLOGY, etc.).
     *
     * A global unique on code alone causes ProgrammeSeeder's updateOrCreate() to
     * overwrite the department_id of the first-inserted row whenever a later department
     * shares the same code — silently corrupting the data.
     *
     * Fix: drop the single-column unique, add a compound unique on (code, department_id)
     * so each department may legitimately have its own programme with a shared code.
     */
    public function up(): void
    {
        Schema::table('programmes', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['code', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('programmes', function (Blueprint $table) {
            $table->dropUnique(['code', 'department_id']);
            $table->unique(['code']);
        });
    }
};
