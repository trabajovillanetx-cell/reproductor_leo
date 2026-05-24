<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SiteSetting;

return new class extends Migration
{
    public function up(): void
    {
        // Inserta el valor por defecto: 1 hora
        SiteSetting::put('demo_duration_hours', '1');
    }

    public function down(): void
    {
        SiteSetting::query()->where('key', 'demo_duration_hours')->delete();
    }
};
