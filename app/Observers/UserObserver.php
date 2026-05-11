<?php

namespace App\Observers;

use App\Models\User;
use App\Services\StreamingProfileProvisioningService;

class UserObserver
{
    public function created(User $user): void
    {
        StreamingProfileProvisioningService::ensureFiveProfilesFor($user);
    }
}
