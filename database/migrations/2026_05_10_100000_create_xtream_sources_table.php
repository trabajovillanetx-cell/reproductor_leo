<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xtream_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->string('username');
            $table->text('password');
            $table->boolean('is_active')->default(true);
            $table->foreignId('live_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('vod_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xtream_sources');
    }
};
