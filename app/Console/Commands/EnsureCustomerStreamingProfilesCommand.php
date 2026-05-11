<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Services\StreamingProfileProvisioningService;
use Illuminate\Console\Command;

class EnsureCustomerStreamingProfilesCommand extends Command
{
    protected $signature = 'streaming:ensure-profiles';

    protected $description = 'Asegura 5 perfiles con PIN por cada cuenta cliente (utilidad de migración).';

    public function handle(): int
    {
        foreach (User::query()->where('role', UserRole::Customer)->cursor() as $user) {
            StreamingProfileProvisioningService::ensureFiveProfilesFor($user);
        }

        $still = User::query()
            ->where('role', UserRole::Customer)
            ->get()
            ->filter(fn (User $u) => $u->streamingProfiles()->count() < CustomerProfile::PER_ACCOUNT_LIMIT)
            ->count();

        $this->info('Listo. Clientes con menos de cinco perfiles: '.$still.' (objetivo 0).');

        return self::SUCCESS;
    }
}
