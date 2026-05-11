<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\CustomerSubscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminCustomerController extends Controller
{
    public function __construct(
        private CustomerSubscriptionService $subscriptions
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $q = User::query()
            ->where('role', UserRole::Customer)
            ->with([
                'parent',
                'streamingProfiles' => fn ($rel) => $rel->orderBy('sort_order'),
            ]);

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $this->applyCustomerSearch($q, $request->string('search')->trim());
        }

        return view('admin.customers.index', [
            'customers' => $q->orderByDesc('id')->paginate(20)->withQueryString(),
            'plans' => Plan::query()->where('is_active', true)->orderBy('duration_months')->get(),
        ]);
    }

    public function show(User $customer): View
    {
        $this->authorize('view', $customer);
        abort_unless($customer->isCustomer(), 404);

        $customer->load(['parent', 'subscriptions.plan']);

        return view('admin.customers.show', [
            'customer' => $customer,
        ]);
    }

    public function edit(User $customer): View
    {
        $this->authorize('update', $customer);
        abort_unless($customer->isCustomer(), 404);

        return view('admin.customers.edit', [
            'customer' => $customer,
            'resellers' => User::query()->whereIn('role', [UserRole::Reseller, UserRole::Vendor])->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        abort_unless($customer->isCustomer(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$customer->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'parent_id' => ['nullable', 'exists:users,id'],
            'status' => ['required', 'in:active,expired,suspended'],
            /** Formato `datetime-local`: YYYY-MM-DDTHH:mm en zona APP_TIMEZONE */
            'expires_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ]);

        if ($data['parent_id'] !== null) {
            $parent = User::query()->find($data['parent_id']);
            if ($parent === null || (! $parent->isReseller() && ! $parent->isVendor())) {
                return back()->withErrors(['parent_id' => 'Selecciona un revendedor o vendedor válido.'])->withInput();
            }
        }

        $customer->name = $data['name'];
        $customer->email = $data['email'];
        $customer->parent_id = $data['parent_id'];
        $customer->status = UserStatus::from($data['status']);

        if (! empty($data['expires_at'])) {
            $tz = (string) config('app.timezone', 'UTC');
            $customer->expires_at = Carbon::createFromFormat('Y-m-d\TH:i', $data['expires_at'], $tz);
        } else {
            $customer->expires_at = null;
        }

        if (! empty($data['password'])) {
            $customer->password = Hash::make($data['password']);
            $customer->provider_password = $data['password'];
        }

        $customer->save();

        return redirect()->route('admin.customers.show', $customer)->with('success', 'Cliente actualizado.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:users,id'],
        ]);

        $deleted = 0;

        DB::transaction(function () use ($data, $request, &$deleted): void {
            foreach ($data['ids'] as $id) {
                $user = User::query()->find((int) $id);
                if ($user === null || ! $user->isCustomer()) {
                    continue;
                }
                $this->authorize('delete', $user);
                if ($user->id === $request->user()->id) {
                    continue;
                }
                $user->delete();
                $deleted++;
            }
        });

        if ($deleted === 0) {
            return redirect()->route('admin.customers.index')->with('error', 'No se eliminó ningún cliente.');
        }

        return redirect()->route('admin.customers.index')->with('success', $deleted.' cliente(s) eliminado(s).');
    }

    private function applyCustomerSearch(Builder $q, string $raw): void
    {
        if ($raw === '') {
            return;
        }

        $like = '%'.$raw.'%';
        $q->where(function (Builder $w) use ($like, $raw): void {
            $w->where('name', 'like', $like)
                ->orWhere('email', 'like', $like);

            if (ctype_digit($raw)) {
                $w->orWhere('id', (int) $raw);
            }

            $w->orWhereHas('subscriptions', function (Builder $sq) use ($like, $raw): void {
                $sq->where(function (Builder $inner) use ($like, $raw): void {
                    $inner->whereHas('plan', function (Builder $pq) use ($like): void {
                        $pq->where('name', 'like', $like);
                    });
                    if (ctype_digit($raw)) {
                        $inner->orWhere('plan_id', (int) $raw);
                    }
                });
            });
        });
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.customers.create', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('duration_months')->get(),
            'resellers' => User::query()->whereIn('role', [UserRole::Reseller, UserRole::Vendor])->where('status', 'active')->orderBy('name')->get(),
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
            'parent_id' => ['nullable', 'exists:users,id'],
        ]);

        $plan = Plan::query()->findOrFail($data['plan_id']);
        abort_unless($plan->is_active, 422);

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

        $request->merge(['parent_id' => $data['parent_id'] ?? null]);
        $this->subscriptions->setParentForNewCustomer($customer, $request->user());

        $this->subscriptions->assignPlanToCustomer($customer, $plan, $request->user(), false);

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'Cliente creado con plan. Podés configurar los cinco espacios (nombre, avatar y PIN) en «Espacios de reproducción».');
    }

    public function renew(Request $request, User $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        abort_unless($customer->isCustomer(), 404);

        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $plan = Plan::query()->findOrFail($data['plan_id']);
        abort_unless($plan->is_active, 422);

        $this->subscriptions->renewCustomer($customer, $plan, $request->user(), false);

        return back()->with('success', 'Cliente renovado.');
    }

    public function suspend(User $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        abort_unless($customer->isCustomer(), 404);

        $customer->status = \App\Enums\UserStatus::Suspended;
        $customer->save();

        return back()->with('success', 'Cliente suspendido.');
    }

    public function activate(User $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        abort_unless($customer->isCustomer(), 404);

        if ($customer->expires_at !== null && \App\Support\SubscriptionTime::isExpiredByInstant($customer->expires_at)) {
            return back()->with('error', 'El cliente está vencido; renueva antes de activar.');
        }

        $customer->status = \App\Enums\UserStatus::Active;
        $customer->save();

        return back()->with('success', 'Cliente activado.');
    }

    public function destroy(User $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);
        abort_unless($customer->isCustomer(), 404);

        $customer->delete();

        return redirect()->route('admin.customers.index')->with('success', 'Cliente eliminado.');
    }
}
