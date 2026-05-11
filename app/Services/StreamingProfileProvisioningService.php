<?php

namespace App\Services;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class StreamingProfileProvisioningService
{
    public static function ensureFiveProfilesFor(User $user): void
    {
        if (! $user->isCustomer()) {
            return;
        }

        while ($user->streamingProfiles()->count() < CustomerProfile::PER_ACCOUNT_LIMIT) {
            $n = $user->streamingProfiles()->count();
            $defaultPin = str_pad((string) ($n + 1), 4, '0', STR_PAD_LEFT);
            CustomerProfile::query()->create([
                'user_id' => $user->id,
                'name' => 'Perfil '.($n + 1),
                'pin_hash' => Hash::make($defaultPin),
                'sort_order' => $n,
            ]);
        }
    }

    /** @param  iterable<User>|\Illuminate\Support\Collection<int, User>  $users */
    public static function bulkEnsure(iterable $users): int
    {
        $fixed = 0;
        foreach ($users as $u) {
            if ($u instanceof User && $u->isCustomer()) {
                $before = $u->streamingProfiles()->count();
                self::ensureFiveProfilesFor($u);
                $after = $u->streamingProfiles()->count();
                if ($after > $before) {
                    $fixed++;
                }
            }
        }

        return $fixed;
    }
}
