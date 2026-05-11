<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\ResellerCredit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminVendorController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $q = User::query()
            ->where('role', UserRole::Vendor)
            ->with('resellerCredits');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $this->applySearch($q, $request->string('search')->trim());
        }

        return view('admin.vendors.index', [
            'vendors' => $q->orderBy('name')->paginate(15)->withQueryString(),
        ]);
    }

    public function show(User $vendor): View
    {
        $this->authorize('view', $vendor);
        abort_unless($vendor->isVendor(), 404);

        $vendor->load(['resellerCredits', 'customers']);

        return view('admin.vendors.show', ['vendor' => $vendor]);
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
                if ($user === null || ! $user->isVendor()) {
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
            return redirect()->route('admin.vendors.index')->with('error', 'No se eliminó ningún vendedor.');
        }

        return redirect()->route('admin.vendors.index')->with('success', $deleted.' vendedor(es) eliminado(s).');
    }

    private function applySearch(Builder $q, string $raw): void
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
        });
    }

    public function create(): View
    {
        $this->authorize('createVendor', User::class);

        return view('admin.vendors.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('createVendor', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'credits' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => UserRole::Vendor,
            'status' => UserStatus::Active,
            'parent_id' => null,
            'expires_at' => null,
        ]);

        ResellerCredit::query()->create([
            'reseller_id' => $user->id,
            'credits' => (int) ($data['credits'] ?? 0),
        ]);

        return redirect()->route('admin.vendors.index')->with('success', 'Vendedor creado.');
    }

    public function edit(User $vendor): View
    {
        $this->authorize('update', $vendor);
        abort_unless($vendor->isVendor(), 404);

        $vendor->load('resellerCredits');

        return view('admin.vendors.edit', ['vendor' => $vendor]);
    }

    public function update(Request $request, User $vendor): RedirectResponse
    {
        $this->authorize('update', $vendor);
        abort_unless($vendor->isVendor(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$vendor->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'status' => ['required', 'in:active,suspended'],
        ]);

        $vendor->name = $data['name'];
        $vendor->email = $data['email'];
        $vendor->status = UserStatus::from($data['status']);

        if (! empty($data['password'])) {
            $vendor->password = Hash::make($data['password']);
        }

        $vendor->save();

        return redirect()->route('admin.vendors.index')->with('success', 'Vendedor actualizado.');
    }

    public function updateCredits(Request $request, User $vendor): RedirectResponse
    {
        $this->authorize('manageCredits', $vendor);
        abort_unless($vendor->isVendor(), 404);

        $data = $request->validate([
            'credits' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        ResellerCredit::query()->updateOrCreate(
            ['reseller_id' => $vendor->id],
            ['credits' => $data['credits']]
        );

        return back()->with('success', 'Créditos actualizados.');
    }

    public function destroy(User $vendor): RedirectResponse
    {
        $this->authorize('delete', $vendor);
        abort_unless($vendor->isVendor(), 404);

        $vendor->delete();

        return redirect()->route('admin.vendors.index')->with('success', 'Vendedor eliminado.');
    }
}
