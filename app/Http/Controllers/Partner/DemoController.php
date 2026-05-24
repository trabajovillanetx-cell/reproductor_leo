<?php

namespace App\Http\Controllers\Partner;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class DemoController extends Controller
{
    public function create(string $routePrefix): View
    {
        $hours = (int) SiteSetting::get('demo_duration_hours', '1');

        return view('partner.demos.create', [
            'routePrefix'       => $routePrefix,
            'demoDurationHours' => $hours,
        ]);
    }

    public function store(Request $request, string $routePrefix): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $hours   = (int) SiteSetting::get('demo_duration_hours', '1');
        $expires = now('America/Bogota')->addHours($hours);

        User::query()->create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'provider_password' => $data['password'],
            'role'              => UserRole::Customer,
            'status'            => UserStatus::Active,
            'parent_id'         => $request->user()->id,
            'expires_at'        => $expires,
            'is_demo'           => true,
        ]);

        return redirect()
            ->route($routePrefix . '.customers.index')
            ->with('success', "✅ Demo creado. Vence el {$expires->format('d/m/Y H:i')} (hora Bogotá).");
    }
}
