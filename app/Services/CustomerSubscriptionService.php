<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubscriptionTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerSubscriptionService
{
    public function __construct(
        private CreditLedgerService $credits
    ) {}

    public function assertActorHasCredits(User $actor, int $months): void
    {
        if (! $actor->holdsCredits()) {
            return;
        }

        if ($this->credits->balance($actor) < $months) {
            throw ValidationException::withMessages([
                'plan_id' => 'Créditos insuficientes. Contactá a tu proveedor.',
            ]);
        }
    }

    /** @deprecated use assertActorHasCredits */
    public function assertResellerHasCredits(User $reseller, int $months): void
    {
        $this->assertActorHasCredits($reseller, $months);
    }

    public function deductCredits(User $actor, int $months): void
    {
        if ($months <= 0 || ! $actor->holdsCredits()) {
            return;
        }

        $this->credits->decrement($actor, $months);
    }

    public function assignPlanToCustomer(User $customer, Plan $plan, User $actor, bool $deductActorCredits): void
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages([
                'plan_id' => 'El plan no está activo.',
            ]);
        }

        if ($deductActorCredits && $actor->holdsCredits()) {
            $this->assertActorHasCredits($actor, $plan->duration_months);
        }

        DB::transaction(function () use ($customer, $plan, $actor, $deductActorCredits): void {
            if ($deductActorCredits && $actor->holdsCredits()) {
                $this->deductCredits($actor, $plan->duration_months);
            }

            $base = $customer->expires_at !== null && SubscriptionTime::isDateWindowOpen($customer->expires_at)
                ? $customer->expires_at->copy()
                : now();

            $expires = $base->copy()->addMonths($plan->duration_months);

            $customer->expires_at = $expires;
            $customer->status = UserStatus::Active;
            $customer->save();

            Subscription::create([
                'user_id' => $customer->id,
                'plan_id' => $plan->id,
                'starts_at' => now(),
                'expires_at' => $expires,
                'status' => SubscriptionStatus::Active,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function renewCustomer(User $customer, Plan $plan, User $actor, bool $deductActorCredits): void
    {
        $this->assignPlanToCustomer($customer, $plan, $actor, $deductActorCredits);
    }

    public function ensureCustomerActor(User $customer, User $actor): void
    {
        if ($actor->isAdmin()) {
            return;
        }

        if ($customer->isCustomer() && $customer->parent_id === $actor->id) {
            if ($actor->isReseller() || $actor->isVendor()) {
                return;
            }
        }

        abort(403);
    }

    /**
     * El padre del cliente siempre es quien lo crea (revendedor o vendedor conectado).
     * Nunca se asigna el dueño de la red superior: así cada uno solo ve y gestiona sus propios clientes.
     */
    public function setParentForNewCustomer(User $customer, User $actor): void
    {
        if ($actor->isReseller() || $actor->isVendor()) {
            $customer->parent_id = $actor->id;
        }

        if ($actor->isAdmin() && request()->filled('parent_id')) {
            $parentId = (int) request('parent_id');
            $parent = User::query()->findOrFail($parentId);
            if (! $parent->isReseller() && ! $parent->isVendor()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'El padre debe ser un revendedor o vendedor.',
                ]);
            }
            $customer->parent_id = $parent->id;
        }

        $customer->role = UserRole::Customer;
        $customer->save();
    }
}
