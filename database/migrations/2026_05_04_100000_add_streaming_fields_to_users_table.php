<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('customer')->after('password');
            $table->foreignId('parent_id')->nullable()->after('role')->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('active')->after('parent_id');
            $table->timestamp('expires_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['role', 'parent_id', 'status', 'expires_at']);
        });
    }
};
