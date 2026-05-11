<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->string('avatar_url', 2048)->nullable()->after('name');
        });

        Schema::table('access_logs', function (Blueprint $table) {
            $table->foreignId('customer_profile_id')
                ->nullable()
                ->after('user_id')
                ->constrained('customer_profiles')
                ->nullOnDelete();
        });

        Schema::table('playback_tokens', function (Blueprint $table) {
            $table->foreignId('customer_profile_id')
                ->nullable()
                ->after('user_id')
                ->constrained('customer_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('playback_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_profile_id');
        });

        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_profile_id');
        });

        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropColumn('avatar_url');
        });
    }
};
