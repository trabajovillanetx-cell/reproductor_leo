<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CheckConcurrentSessions;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $activeSessions = collect();
        if (config('session.driver') === 'database' && $request->user() !== null) {
            $cutoff = CheckConcurrentSessions::activityCutoffTimestamp();
            $activeSessions = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $request->user()->id)
                ->where('last_activity', '>=', $cutoff)
                ->orderByDesc('last_activity')
                ->get(['id', 'ip_address', 'user_agent', 'last_activity']);
        }

        return view('profile.edit', [
            'user' => $request->user(),
            'activeSessions' => $activeSessions,
            'currentSessionId' => $request->session()->getId(),
            'maxConcurrentSessions' => CheckConcurrentSessions::maxConcurrentSessions(),
        ]);
    }

    /**
     * Cierra una sesión persistida en la tabla `sessions` (otro dispositivo).
     */
    public function destroySession(Request $request, string $session): RedirectResponse
    {
        if (config('session.driver') !== 'database') {
            return Redirect::route('profile.edit')->withErrors([
                'session' => 'Las sesiones no se administran con el driver actual.',
            ]);
        }

        $user = $request->user();
        abort_if($user === null, 403);

        $table = config('session.table', 'sessions');
        $exists = DB::table($table)
            ->where('id', $session)
            ->where('user_id', $user->id)
            ->exists();

        if (! $exists) {
            return Redirect::route('profile.edit')->with('warning', 'Esa sesión no existe o no es tuya.');
        }

        if ($session === $request->session()->getId()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return Redirect::route('login')->with('status', 'Sesión cerrada.');
        }

        DB::table($table)->where('id', $session)->where('user_id', $user->id)->delete();

        return Redirect::route('profile.edit')->with('status', 'Sesión cerrada en ese dispositivo.');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
