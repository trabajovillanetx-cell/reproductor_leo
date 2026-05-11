<?php

namespace App\Policies;

use App\Models\Content;
use App\Models\User;

class ContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function viewForPlayback(User $user, Content $content): bool
    {
        return $user->isCustomer()
            && $user->hasActiveSubscription()
            && $content->is_active;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Content $content): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Content $content): bool
    {
        return $user->isAdmin();
    }
}
