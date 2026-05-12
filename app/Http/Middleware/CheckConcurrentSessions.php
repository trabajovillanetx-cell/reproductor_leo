<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckConcurrentSessions
{
    /**
     * Sesiones con last_activity anterior a este umbral no cuentan como “conectadas”.
     */
    public static function activityCutoffTimestamp(): int
    {
        $minutes = max(5, (int) config('streaming.concurrent_session_activity_minutes', 30));

        return now()->subMinutes($minutes)->getTimestamp();
    }

    public static function maxConcurrentSessions(): int
    {
        return max(1, min(50, (int) config('streaming.max_concurrent_customer_sessions', 5)));
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isCustomer()) {
            return $next($request);
        }

        if (config('session.driver') !== 'database') {
            return $next($request);
        }

        $max = self::maxConcurrentSessions();
        $currentId = $request->session()->getId();
        $cutoff = self::activityCutoffTimestamp();

        $orderedIds = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('last_activity', '>=', $cutoff)
            ->orderByDesc('last_activity')
            ->orderByDesc('id')
            ->pluck('id');

        if ($orderedIds->count() <= $max) {
            return $next($request);
        }

        $keep = $orderedIds->take($max);
        if ($keep->contains($currentId)) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('screen_limit_popup', 'El máximo es '.$max.' dispositivos a la vez. Cerrá sesión en otro equipo o, si podés, en Perfil → Dispositivos conectados e intentá de nuevo.');
    }
}
