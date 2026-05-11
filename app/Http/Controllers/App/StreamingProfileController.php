<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class StreamingProfileController extends Controller
{
    public function index(Request $request): View
    {
        $profiles = $request->user()
            ->streamingProfiles()
            ->orderBy('sort_order')
            ->get();

        return view('app.profiles.index', [
            'profiles' => $profiles,
        ]);
    }

    public function select(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'profile_id' => ['required', 'integer'],
            'pin' => ['required', 'string', 'digits:4'],
        ]);

        $profile = CustomerProfile::query()
            ->where('id', (int) $data['profile_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if ($profile === null) {
            return back()->withErrors(['pin' => 'Perfil inválido.'])->withInput();
        }

        if (! Hash::check($data['pin'], $profile->pin_hash)) {
            return back()->withErrors(['pin' => 'PIN incorrecto.'])->withInput();
        }

        session(['streaming_profile_id' => $profile->id]);

        return redirect()->intended(route('app.home'));
    }

    public function switch(Request $request): RedirectResponse
    {
        session()->forget('streaming_profile_id');

        return redirect()->route('app.profiles.index')->with('status', 'BIENVENIDO');
    }
}
