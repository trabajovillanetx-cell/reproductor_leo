<?php

namespace Database\Seeders;

use App\Enums\ContentType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Content;
use App\Models\Plan;
use App\Models\ResellerCredit;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'parent_id' => null,
            'expires_at' => null,
        ]);

        $reseller = User::query()->create([
            'name' => 'Revendedor Demo',
            'email' => 'reseller@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => UserRole::Reseller,
            'status' => UserStatus::Active,
            'parent_id' => null,
            'expires_at' => null,
        ]);

        ResellerCredit::query()->create([
            'reseller_id' => $reseller->id,
            'credits' => 500,
        ]);

        $plan1 = Plan::query()->create(['name' => '1 mes', 'duration_months' => 1, 'price' => 9.99, 'description' => 'Plan mensual', 'is_active' => true]);
        Plan::query()->create(['name' => '3 meses', 'duration_months' => 3, 'price' => 24.99, 'description' => 'Trimestral', 'is_active' => true]);
        Plan::query()->create(['name' => '6 meses', 'duration_months' => 6, 'price' => 44.99, 'description' => 'Semestral', 'is_active' => true]);
        Plan::query()->create(['name' => '12 meses', 'duration_months' => 12, 'price' => 79.99, 'description' => 'Anual', 'is_active' => true]);
        Plan::query()->create(['name' => 'Personalizado 2 meses', 'duration_months' => 2, 'price' => 15, 'description' => 'Ejemplo plan custom', 'is_active' => true]);

        $activeExpires = now()->addDays(30);
        $customerActive = User::query()->create([
            'name' => 'Cliente Activo',
            'email' => 'customer@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'parent_id' => $reseller->id,
            'expires_at' => $activeExpires,
        ]);

        Subscription::query()->create([
            'user_id' => $customerActive->id,
            'plan_id' => $plan1->id,
            'starts_at' => now()->subDays(2),
            'expires_at' => $activeExpires,
            'status' => SubscriptionStatus::Active,
            'created_by' => $reseller->id,
        ]);

        $expiredAt = now()->subDay();
        $customerExpired = User::query()->create([
            'name' => 'Cliente Vencido',
            'email' => 'expired@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => UserRole::Customer,
            'status' => UserStatus::Expired,
            'parent_id' => $reseller->id,
            'expires_at' => $expiredAt,
        ]);

        Subscription::query()->create([
            'user_id' => $customerExpired->id,
            'plan_id' => $plan1->id,
            'starts_at' => now()->subMonths(2),
            'expires_at' => $expiredAt,
            'status' => SubscriptionStatus::Expired,
            'created_by' => $reseller->id,
        ]);

        $catVod = Category::query()->create([
            'name' => 'Demo VOD',
            'type' => ContentType::Vod,
            'is_active' => true,
        ]);

        $catLive = Category::query()->create([
            'name' => 'Demo Live',
            'type' => ContentType::Live,
            'is_active' => true,
        ]);

        Content::query()->create([
            'category_id' => $catVod->id,
            'title' => 'Big Buck Bunny (MP4 demo)',
            'description' => 'Vídeo de demostración legal (Blender Foundation).',
            'type' => ContentType::Vod,
            'stream_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
            'poster_url' => null,
            'duration' => 596,
            'is_active' => true,
        ]);

        Content::query()->create([
            'category_id' => $catLive->id,
            'title' => 'Stream HLS demo',
            'description' => 'Flujo HLS de prueba (Mux test stream).',
            'type' => ContentType::Live,
            'stream_url' => 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
            'poster_url' => null,
            'duration' => null,
            'is_active' => true,
        ]);

        $this->command->info('Usuarios demo: admin@example.com / reseller@example.com / customer@example.com / expired@example.com — contraseña: password');
    }
}
