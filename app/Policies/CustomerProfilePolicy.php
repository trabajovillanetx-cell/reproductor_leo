<?php

namespace App\Policies;

use App\Models\CustomerProfile;
use App\Models\User;

class CustomerProfilePolicy
{
    public function manage(User $auth, CustomerProfile $profile): bool
    {
        $owner = $profile->user;
        if (! $owner->isCustomer()) {
            return false;
        }

        if ($auth->isAdmin()) {
            return true;
        }

        return ($auth->isReseller() || $auth->isVendor())
            && (int) $owner->parent_id === (int) $auth->id;
    }
}
