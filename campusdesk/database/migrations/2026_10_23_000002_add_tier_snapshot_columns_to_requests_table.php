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
        Schema::table('requests', function (Blueprint $table) {
            $table->foreignId('request_type_tier_id')
                ->nullable()
                ->after('request_type_id')
                ->constrained('request_type_tiers')
                ->nullOnDelete();
            $table->string('tier_name_snapshot', 100)->default('Standard')->after('request_type_tier_id');
            $table->unsignedBigInteger('fee_amount')->default(0)->after('tier_name_snapshot');
            $table->unsignedBigInteger('amount_paid')->default(0)->after('fee_amount');
            $table->string('currency', 3)->default('XAF')->after('amount_paid');
            $table->unsignedSmallInteger('turnaround_days_snapshot')->default(3)->after('currency');
            $table->unsignedTinyInteger('tier_priority')->default(2)->after('turnaround_days_snapshot');
            $table->timestamp('paid_at')->nullable()->after('tier_priority');
            $table->timestamp('target_ready_at')->nullable()->after('paid_at');

            $table->index(['status', 'tier_priority', 'created_at'], 'requests_queue_priority_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropIndex('requests_queue_priority_idx');
            $table->dropForeign(['request_type_tier_id']);
            $table->dropColumn([
                'request_type_tier_id',
                'tier_name_snapshot',
                'fee_amount',
                'amount_paid',
                'currency',
                'turnaround_days_snapshot',
                'tier_priority',
                'paid_at',
                'target_ready_at',
            ]);
        });
    }
};
