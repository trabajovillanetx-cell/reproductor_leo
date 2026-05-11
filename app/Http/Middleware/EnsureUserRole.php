<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    private function redirectAwayFromAdminArea(User $user): Response
    {
        return match ($user->role) {
            UserRole::Reseller => redirect()->route('reseller.dashboard'),
            UserRole::Vendor => redirect()->route('vendor.dashboard'),
            UserRole::Customer => redirect()->route('app.profiles.index'),
            default => abort(403, 'No autorizado.'),
        };
    }

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $allowed = [];
        foreach ($roles as $chunk) {
            foreach (array_map('trim', explode(',', $chunk)) as $part) {
                if ($part !== '') {
                    $allowed[] = UserRole::from($part);
                }
            }
        }

        if ($allowed === [] || ! in_array($user->role, $allowed, true)) {
            // Rutas /admin/*: solo administración; si otro rol entra por URL o marcador, lo llevamos a su panel.
            if (in_array(UserRole::Admin, $allowed, true)) {
                return $this->redirectAwayFromAdminArea($user);
            }

            abort(403, 'No autorizado.');
        }

        return $next($request);
    }
}
