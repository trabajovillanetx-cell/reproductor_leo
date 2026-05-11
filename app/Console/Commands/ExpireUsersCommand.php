<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubscriptionTime;
use Illuminate\Console\Command;

class ExpireUsersCommand extends Command
{
    protected $signature = 'users:expire';

    protected $description = 'Marca clientes vencidos y suscripciones activas expiradas.';

    public function handle(): int
    {
        $usersUpdated = 0;
        User::query()
            ->where('role', UserRole::Customer)
            ->whereNotNull('expires_at')
            ->where('status', UserStatus::Active)
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$usersUpdated): void {
                foreach ($users as $user) {
                    if (SubscriptionTime::isExpiredByInstant($user->expires_at)) {
                        $user->status = UserStatus::Expired;
                        $user->save();
                        $usersUpdated++;
                    }
                }
            });

        $subsUpdated = 0;
        Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('expires_at')
            ->orderBy('id')
            ->chunkById(200, function ($subs) use (&$subsUpdated): void {
                foreach ($subs as $sub) {
                    if (SubscriptionTime::isExpiredByInstant($sub->expires_at)) {
                        $sub->status = SubscriptionStatus::Expired;
                        $sub->save();
                        $subsUpdated++;
                    }
                }
            });

        $this->info("Usuarios actualizados a expirado: {$usersUpdated}");
        $this->info("Suscripciones actualizadas a expirado: {$subsUpdated}");

        return self::SUCCESS;
    }
}
