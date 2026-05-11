<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_folder_posters', function (Blueprint $table) {
            $table->id();
            $table->string('folder_path', 512)->unique();
            $table->string('poster_url', 2048);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_folder_posters');
    }
};
