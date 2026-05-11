<?php

namespace App\Http\Controllers\Reseller;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\ResellerCredit;
use App\Models\User;
use App\Services\CreditLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class NetworkController extends Controller
{
    public function __construct(
        private CreditLedgerService $ledger
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->isReseller(), 403);

        $children = User::query()
            ->where('parent_id', $request->user()->id)
            ->whereIn('role', [UserRole::Reseller, UserRole::Vendor])
            ->with('resellerCredits')
            ->orderBy('name')
            ->get();

        return view('reseller.network.index', [
            'children' => $children,
            'balance' => $this->ledger->balance($request->user()),
        ]);
    }

    public function createReseller(Request $request): View
    {
        abort_unless($request->user()->isReseller(), 403);
        $this->authorize('createReseller', User::class);

        return view('reseller.network.create-reseller', [
            'balance' => $this->ledger->balance($request->user()),
        ]);
    }

    public function storeReseller(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isReseller(), 403);
        $this->authorize('createReseller', User::class);

        $actor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'credits' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $credits = (int) $data['credits'];

        if ($credits > $this->ledger->balance($actor)) {
            return back()->withErrors([
                'credits' => 'Créditos insuficientes. Contactá a tu proveedor.',
            ])->withInput();
        }

        DB::transaction(function () use ($actor, $data, $credits): void {
            $child = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => UserRole::Reseller,
                'status' => UserStatus::Active,
                'parent_id' => $actor->id,
                'expires_at' => null,
            ]);

            ResellerCredit::query()->create([
                'reseller_id' => $child->id,
                'credits' => 0,
            ]);

            if ($credits > 0) {
                $this->ledger->transfer($actor, $child, $credits);
            }
        });

        return redirect()->route('reseller.network.index')->with('success', 'Revendedor creado.');
    }

    public function createVendor(Request $request): View
    {
        abort_unless($request->user()->isReseller(), 403);
        $this->authorize('createVendor', User::class);

        return view('reseller.network.create-vendor', [
            'balance' => $this->ledger->balance($request->user()),
        ]);
    }

    public function storeVendor(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isReseller(), 403);
        $this->authorize('createVendor', User::class);

        $actor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'credits' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $credits = (int) $data['credits'];

        if ($credits > $this->ledger->balance($actor)) {
            return back()->withErrors([
                'credits' => 'Créditos insuficientes. Contactá a tu proveedor.',
            ])->withInput();
        }

        DB::transaction(function () use ($actor, $data, $credits): void {
            $child = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => UserRole::Vendor,
                'status' => UserStatus::Active,
                'parent_id' => $actor->id,
                'expires_at' => null,
            ]);

            ResellerCredit::query()->create([
                'reseller_id' => $child->id,
                'credits' => 0,
            ]);

            if ($credits > 0) {
                $this->ledger->transfer($actor, $child, $credits);
            }
        });

        return redirect()->route('reseller.network.index')->with('success', 'Vendedor creado.');
    }
}
