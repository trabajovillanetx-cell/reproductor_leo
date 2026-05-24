<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });

        // Generar username para usuarios existentes basado en el nombre
        $users = DB::table('users')->whereNull('username')->get();
        foreach ($users as $user) {
            $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $user->name));
            if ($base === '') $base = 'user';
            $username = $base;
            $i = 1;
            while (DB::table('users')->where('username', $username)->where('id', '!=', $user->id)->exists()) {
                $username = $base . $i;
                $i++;
            }
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
