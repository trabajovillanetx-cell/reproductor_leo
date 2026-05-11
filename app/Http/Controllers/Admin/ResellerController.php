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

class ResellerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $q = User::query()
            ->where('role', UserRole::Reseller)
            ->with('resellerCredits');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $this->applyResellerSearch($q, $request->string('search')->trim());
        }

        return view('admin.resellers.index', [
            'resellers' => $q->orderBy('name')->paginate(15)->withQueryString(),
        ]);
    }

    public function show(User $reseller): View
    {
        $this->authorize('view', $reseller);
        abort_unless($reseller->isReseller(), 404);

        $reseller->load(['resellerCredits', 'customers']);

        return view('admin.resellers.show', compact('reseller'));
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
                if ($user === null || ! $user->isReseller()) {
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
            return redirect()->route('admin.resellers.index')->with('error', 'No se eliminó ningún revendedor.');
        }

        return redirect()->route('admin.resellers.index')->with('success', $deleted.' revendedor(es) eliminado(s).');
    }

    private function applyResellerSearch(Builder $q, string $raw): void
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
        $this->authorize('createReseller', User::class);

        return view('admin.resellers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('createReseller', User::class);

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
            'role' => UserRole::Reseller,
            'status' => UserStatus::Active,
            'parent_id' => null,
            'expires_at' => null,
        ]);

        ResellerCredit::query()->create([
            'reseller_id' => $user->id,
            'credits' => (int) ($data['credits'] ?? 0),
        ]);

        return redirect()->route('admin.resellers.index')->with('success', 'Revendedor creado.');
    }

    public function edit(User $reseller): View
    {
        $this->authorize('update', $reseller);
        abort_unless($reseller->isReseller(), 404);

        $reseller->load('resellerCredits');

        return view('admin.resellers.edit', compact('reseller'));
    }

    public function update(Request $request, User $reseller): RedirectResponse
    {
        $this->authorize('update', $reseller);
        abort_unless($reseller->isReseller(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$reseller->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'status' => ['required', 'in:active,suspended'],
        ]);

        $reseller->name = $data['name'];
        $reseller->email = $data['email'];
        $reseller->status = UserStatus::from($data['status']);

        if (! empty($data['password'])) {
            $reseller->password = Hash::make($data['password']);
        }

        $reseller->save();

        return redirect()->route('admin.resellers.index')->with('success', 'Revendedor actualizado.');
    }

    public function updateCredits(Request $request, User $reseller): RedirectResponse
    {
        $this->authorize('manageCredits', $reseller);
        abort_unless($reseller->isReseller(), 404);

        $data = $request->validate([
            'credits' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        ResellerCredit::query()->updateOrCreate(
            ['reseller_id' => $reseller->id],
            ['credits' => $data['credits']]
        );

        return back()->with('success', 'Créditos actualizados.');
    }

    public function destroy(User $reseller): RedirectResponse
    {
        $this->authorize('delete', $reseller);
        abort_unless($reseller->isReseller(), 404);

        $reseller->delete();

        return redirect()->route('admin.resellers.index')->with('success', 'Revendedor eliminado.');
    }
}
