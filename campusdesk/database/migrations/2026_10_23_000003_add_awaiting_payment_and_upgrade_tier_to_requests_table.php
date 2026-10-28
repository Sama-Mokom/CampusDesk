<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->foreignId('pending_upgrade_tier_id')
                ->nullable()
                ->after('request_type_tier_id')
                ->constrained('request_type_tiers')
                ->nullOnDelete();
        });

        // Modify status column/enum to support 'awaiting_payment'
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('draft', 'awaiting_payment', 'pending', 'in_review', 'forwarded', 'ready', 'collected', 'rejected') NOT NULL DEFAULT 'awaiting_payment'");
        } else {
            Schema::table('requests', function (Blueprint $table) {
                $table->string('status', 30)->default('awaiting_payment')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropForeign(['pending_upgrade_tier_id']);
            $table->dropColumn('pending_upgrade_tier_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('draft', 'pending', 'in_review', 'forwarded', 'ready', 'collected', 'rejected') NOT NULL DEFAULT 'pending'");
        } else {
            Schema::table('requests', function (Blueprint $table) {
                $table->string('status', 30)->default('pending')->change();
            });
        }
    }
};
