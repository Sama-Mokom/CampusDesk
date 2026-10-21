<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_events', function (Blueprint $table) {
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('request_events', function (Blueprint $table) {
            $table->dropColumn([
                'attempt_count',
                'last_attempt_at',
                'published_at',
                'last_error',
            ]);
        });
    }
};
