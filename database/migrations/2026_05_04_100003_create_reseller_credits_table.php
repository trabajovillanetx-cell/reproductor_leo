<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('credits')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_credits');
    }
};
