<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('playback_tokens', function (Blueprint $table) {
            $table->unsignedInteger('position_seconds')->default(0)->after('playback_status');
            $table->unsignedInteger('duration_seconds')->default(0)->after('position_seconds');
        });
    }
    public function down(): void {
        Schema::table('playback_tokens', function (Blueprint $table) {
            $table->dropColumn(['position_seconds', 'duration_seconds']);
        });
    }
};
