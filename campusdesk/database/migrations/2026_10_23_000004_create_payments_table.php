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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('request_type_tier_id')->constrained('request_type_tiers')->cascadeOnDelete();
            $table->string('external_id', 36)->unique();
            $table->string('provider', 30)->default('fake');
            $table->string('provider_transaction_id', 100)->nullable();
            $table->string('payment_type', 30)->default('initial'); // initial | reopen_upgrade
            $table->string('checkout_mode', 30)->default('hosted'); // hosted | direct_push
            $table->string('payment_method', 30)->nullable(); // mtn_momo | orange_money
            $table->string('payer_phone', 20)->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('XAF');
            $table->string('status', 30)->default('pending'); // pending | processing | successful | failed | expired | cancelled
            $table->text('checkout_url')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->json('raw_last_payload')->nullable();
            $table->timestamp('initiated_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_transaction_id']);
            $table->index(['request_id', 'status']);
            $table->index(['status', 'initiated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
