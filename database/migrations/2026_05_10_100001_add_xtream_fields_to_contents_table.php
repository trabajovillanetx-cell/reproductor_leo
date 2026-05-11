<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->foreignId('xtream_source_id')->nullable()->after('category_id')->constrained('xtream_sources')->cascadeOnDelete();
            $table->string('stream_id', 64)->nullable()->after('xtream_source_id');
            $table->string('source_type', 32)->nullable()->after('stream_id');
        });

        Schema::table('contents', function (Blueprint $table) {
            $table->unique(['xtream_source_id', 'stream_id', 'type'], 'contents_xtream_stream_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropUnique('contents_xtream_stream_type_unique');
            $table->dropConstrainedForeignId('xtream_source_id');
            $table->dropColumn(['stream_id', 'source_type']);
        });
    }
};
