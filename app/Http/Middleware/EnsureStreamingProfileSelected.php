<?php

namespace App\Http\Middleware;

use App\Models\CustomerProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Obliga al cliente activo a haber elegido un perfil (PIN correcto).
 */
class EnsureStreamingProfileSelected
{
    /** @var list<string> */
    private array $unlessRouteNames = [
        'app.profiles.index',
        'app.profiles.select',
        'app.profiles.switch',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->isCustomer()) {
            abort(403);
        }

        foreach ($this->unlessRouteNames as $name) {
            if ($request->routeIs($name)) {
                return $next($request);
            }
        }

        $pid = session('streaming_profile_id');
        if ($pid === null || $pid === '') {
            return redirect()->route('app.profiles.index');
        }

        $belongs = CustomerProfile::query()
            ->where('id', $pid)
            ->where('user_id', $user->id)
            ->exists();

        if (! $belongs) {
            session()->forget('streaming_profile_id');

            return redirect()->route('app.profiles.index');
        }

        return $next($request);
    }
}
