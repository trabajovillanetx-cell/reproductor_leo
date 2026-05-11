<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Support\SubscriptionTime;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if ($user->role !== UserRole::Customer) {
            abort(403, 'Solo clientes pueden acceder a esta sección.');
        }

        if ($request->routeIs('app.plan_expired')) {
            return $next($request);
        }

        if ($user->expires_at === null) {
            return redirect()
                ->route('app.plan_expired')
                ->with('warning', 'No tienes un plan activo. Contacta a tu proveedor.');
        }

        if ($user->status === UserStatus::Suspended) {
            return redirect()
                ->route('app.plan_expired')
                ->with('warning', 'Tu cuenta está suspendida. Contacta a tu proveedor.');
        }

        $dateExpired = SubscriptionTime::isExpiredByInstant($user->expires_at);

        /** Si hay vigencia por fecha pero el estado quedó en “expired” (p. ej. tras una renovación), reactivamos. */
        if (! $dateExpired && $user->status === UserStatus::Expired) {
            $user->status = UserStatus::Active;
            $user->save();
        }

        if ($dateExpired) {
            if ($user->status !== UserStatus::Expired) {
                $user->status = UserStatus::Expired;
                $user->save();
            }

            return redirect()
                ->route('app.plan_expired')
                ->with('warning', 'Tu plan ha vencido. Contacta a tu proveedor.');
        }

        /** Estado “expired” pero con fecha vigente ya se corrigió arriba; si sigue así, tratamos igual que vencido. */
        if ($user->status === UserStatus::Expired) {
            return redirect()
                ->route('app.plan_expired')
                ->with('warning', 'Tu plan ha vencido. Contacta a tu proveedor.');
        }

        return $next($request);
    }
}
