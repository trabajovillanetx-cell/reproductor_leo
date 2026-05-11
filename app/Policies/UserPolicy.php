<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->isAdmin() || $auth->isReseller() || $auth->isVendor();
    }

    public function view(User $auth, User $model): bool
    {
        if ($auth->isAdmin()) {
            return true;
        }

        if (($auth->isReseller() || $auth->isVendor()) && $model->isCustomer() && $model->parent_id === $auth->id) {
            return true;
        }

        return false;
    }

    public function create(User $auth): bool
    {
        return $auth->isAdmin() || $auth->isReseller() || $auth->isVendor();
    }

    public function createReseller(User $auth): bool
    {
        return $auth->isAdmin() || $auth->isReseller();
    }

    public function createVendor(User $auth): bool
    {
        return $auth->isAdmin() || $auth->isReseller();
    }

    public function createCustomer(User $auth): bool
    {
        return $auth->isAdmin() || $auth->isReseller() || $auth->isVendor();
    }

    public function update(User $auth, User $model): bool
    {
        if ($auth->isAdmin()) {
            return true;
        }

        if (($auth->isReseller() || $auth->isVendor()) && $model->isCustomer() && $model->parent_id === $auth->id) {
            return true;
        }

        return false;
    }

    public function delete(User $auth, User $model): bool
    {
        if (! $auth->isAdmin()) {
            return false;
        }

        if ($model->id === $auth->id) {
            return false;
        }

        return true;
    }

    public function manageCredits(User $auth, User $holder): bool
    {
        return $auth->isAdmin() && $holder->holdsCredits();
    }

    public function assignParent(User $auth): bool
    {
        return $auth->isAdmin();
    }

    /** Gestionar hijos en la red (revendedor/vendedor bajo mi cuenta). */
    public function manageNetworkChild(User $auth, User $child): bool
    {
        if ($auth->isAdmin()) {
            return true;
        }

        if (! $auth->isReseller()) {
            return false;
        }

        return $child->parent_id === $auth->id
            && ($child->isReseller() || $child->isVendor());
    }
}
