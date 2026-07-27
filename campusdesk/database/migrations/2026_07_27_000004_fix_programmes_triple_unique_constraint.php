<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The source markdown uses the same code for both BSc and MSc variants of the
     * same programme within the same department (e.g. code 'EFA' appears three times
     * in the EFA department: B.Ed, M.Ed, and Ph.D; code 'EDL' appears twice: B.Ed
     * and Masters; code 'APY' appears three times in EDUCATIONAL PSYCHOLOGY, etc.).
     *
     * A compound unique on (code, department_id) still collides on these rows.
     * The correct key is (code, department_id, degree_type) — a programme is uniquely
     * identified by its code, the department it belongs to, and its degree level.
     */
    public function up(): void
    {
        Schema::table('programmes', function (Blueprint $table) {
            $table->dropUnique(['code', 'department_id']);
            $table->unique(['code', 'department_id', 'degree_type']);
        });
    }

    public function down(): void
    {
        Schema::table('programmes', function (Blueprint $table) {
            $table->dropUnique(['code', 'department_id', 'degree_type']);
            $table->unique(['code', 'department_id']);
        });
    }
};
