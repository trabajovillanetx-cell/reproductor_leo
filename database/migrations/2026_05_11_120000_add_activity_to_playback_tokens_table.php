<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playback_tokens', function (Blueprint $table) {
            // Sin ->after() para compatibilidad con SQLite.
            $table->string('user_agent', 512)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('playback_status', 32)->nullable()->default('playing');
        });
    }

    public function down(): void
    {
        Schema::table('playback_tokens', function (Blueprint $table) {
            $table->dropColumn([
                'user_agent',
                'ip_address',
                'last_seen_at',
                'playback_status',
            ]);
        });
    }
};
