<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\CustomerSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerSubscriptionService $subscriptions
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $q = User::query()
            ->whereDirectCustomerOf((int) auth()->id())
            ->with(['streamingProfiles' => fn ($rel) => $rel->orderBy('sort_order')]);

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $s = '%'.$request->string('search').'%';
            $q->where(function ($w) use ($s): void {
                $w->where('name', 'like', $s)->orWhere('email', 'like', $s);
            });
        }

        return view('partner.customers.index', [
            'customers' => $q->orderByDesc('id')->paginate(20)->withQueryString(),
            'plans' => Plan::query()->where('is_active', true)->orderBy('duration_months')->get(),
            'routePrefix' => 'vendor',
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('vendor.customers.create', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('duration_months')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $plan = Plan::query()->findOrFail($data['plan_id']);
        abort_unless($plan->is_active, 422);

        $this->subscriptions->assertActorHasCredits($request->user(), $plan->duration_months);

        $customer = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'provider_password' => $data['password'],
            'role' => UserRole::Customer,
            'status' => \App\Enums\UserStatus::Active,
            'parent_id' => null,
            'expires_at' => null,
        ]);

        $this->subscriptions->setParentForNewCustomer($customer, $request->user());
        $this->subscriptions->assignPlanToCustomer($customer, $plan, $request->user(), true);

        return redirect()->route('vendor.customers.index')->with('success', 'Cliente creado.');
    }

    public function renew(Request $request, User $customer): RedirectResponse
    {
        $this->subscriptions->ensureCustomerActor($customer, $request->user());
        $this->authorize('update', $customer);

        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $plan = Plan::query()->findOrFail($data['plan_id']);
        abort_unless($plan->is_active, 422);

        $this->subscriptions->assertActorHasCredits($request->user(), $plan->duration_months);
        $this->subscriptions->renewCustomer($customer, $plan, $request->user(), true);

        return back()->with('success', 'Cliente renovado.');
    }

    public function suspend(Request $request, User $customer): RedirectResponse
    {
        $this->subscriptions->ensureCustomerActor($customer, $request->user());
        $this->authorize('update', $customer);

        $customer->status = \App\Enums\UserStatus::Suspended;
        $customer->save();

        return back()->with('success', 'Cliente suspendido.');
    }

    public function activate(Request $request, User $customer): RedirectResponse
    {
        $this->subscriptions->ensureCustomerActor($customer, $request->user());
        $this->authorize('update', $customer);

        if ($customer->expires_at !== null && \App\Support\SubscriptionTime::isExpiredByInstant($customer->expires_at)) {
            return back()->with('error', 'Cliente vencido; renueva el plan antes de activar.');
        }

        $customer->status = \App\Enums\UserStatus::Active;
        $customer->save();

        return back()->with('success', 'Cliente activado.');
    }
}
