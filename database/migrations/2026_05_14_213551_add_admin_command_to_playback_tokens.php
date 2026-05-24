<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playback_tokens', function (Blueprint $table) {
            $table->string('admin_command')->nullable()->after('playback_status');
            $table->string('admin_command_data')->nullable()->after('admin_command');
        });
    }

    public function down(): void
    {
        Schema::table('playback_tokens', function (Blueprint $table) {
            $table->dropColumn(['admin_command', 'admin_command_data']);
        });
    }
};
