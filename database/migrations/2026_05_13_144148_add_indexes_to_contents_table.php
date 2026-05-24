<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->index(['type', 'is_active'], 'idx_contents_type_active');
            $table->index(['is_active', 'id'], 'idx_contents_active_id');
            $table->index('category_id', 'idx_contents_category');
            $table->index('library_folder', 'idx_contents_library_folder');
            $table->index(['type', 'is_active', 'id'], 'idx_contents_type_active_id');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropIndex('idx_contents_type_active');
            $table->dropIndex('idx_contents_active_id');
            $table->dropIndex('idx_contents_category');
            $table->dropIndex('idx_contents_library_folder');
            $table->dropIndex('idx_contents_type_active_id');
        });
    }
};
