<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Change programmes.degree_type from the original old-style enum values
     * (BSc, BEng, MEng, MSc, PhD) to the canonical seeder values
     * (BACHELOR, CERTIFICATE, MASTER, PHD).
     *
     * MySQL requires ALTER TABLE ... MODIFY COLUMN to change enum values.
     * The column is a string-backed enum so a raw statement is the safest approach
     * (Blueprint::enum() on an existing column requires a drop+re-add cycle which
     * is not safe on a populated table without a data migration step).
     *
     * Note: any existing rows with old-style values (BSc, BEng, etc.) will need a
     * data migration before this runs on a populated database. Against a fresh
     * migrate:fresh this is a non-issue.
     */
    public function up(): void
    {
        // SQLite does not support MODIFY COLUMN; the column is stored as TEXT
        // with no enforcement of enum values, so the drop+re-add cycle is
        // unnecessary — SQLite already accepts any string value.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("
            ALTER TABLE `programmes`
            MODIFY COLUMN `degree_type`
            ENUM('BACHELOR','CERTIFICATE','MASTER','PHD') NOT NULL
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("
            ALTER TABLE `programmes`
            MODIFY COLUMN `degree_type`
            ENUM('BSc','BEng','MEng','MSc','PhD') NOT NULL
        ");
    }
};
