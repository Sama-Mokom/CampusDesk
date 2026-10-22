<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_events', function (Blueprint $table) {
            $table->timestamp('failed_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('request_events', function (Blueprint $table) {
            $table->dropColumn('failed_at');
        });
    }
};
