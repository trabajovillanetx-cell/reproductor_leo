<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Edición de espacios (nombre, avatar, PIN) y marca «vendido» para revendedores y vendedores.
 */
class CustomerStreamingProfileController extends Controller
{
    public function edit(Request $request, User $customer): View
    {
        $this->assertPartnerOwnsCustomer($request, $customer);
        $this->authorize('update', $customer);

        $profiles = $customer->streamingProfiles()->orderBy('sort_order')->get();

        return view('partner.customers.streaming-profiles', [
            'customer' => $customer,
            'profiles' => $profiles,
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function update(Request $request, User $customer, CustomerProfile $profile): RedirectResponse
    {
        $this->assertPartnerOwnsCustomer($request, $customer);
        abort_unless((int) $profile->user_id === (int) $customer->id, 404);
        $this->authorize('manage', $profile);

        $base = [
            '_editing_profile' => ['required', 'integer', 'in:'.$profile->id],
            'name' => ['required', 'string', 'max:100'],
            'avatar_url' => ['nullable', 'string', 'max:2048', 'url'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ];

        $pinTouched = $request->filled('pin') || $request->filled('pin_confirmation');
        if ($pinTouched) {
            $request->validate(array_merge($base, [
                'pin' => ['required', 'string', 'digits:4'],
                'pin_confirmation' => ['required', 'same:pin'],
            ]));
        } else {
            $request->validate($base);
        }

        $profile->name = trim((string) $request->input('name'));

        if ($request->hasFile('avatar')) {
            $this->deleteStoredAvatarFileIfOwned($profile->avatar_url);
            $storedPath = $request->file('avatar')->store(
                'customer-profiles/user-'.$customer->id.'/profile-'.$profile->id,
                'public'
            );
            $profile->avatar_url = Storage::disk('public')->url($storedPath);
        } else {
            $rawAvatar = $request->input('avatar_url');
            $newUrl = is_string($rawAvatar) && trim($rawAvatar) !== '' ? trim($rawAvatar) : null;
            if ($newUrl !== $profile->avatar_url) {
                $this->deleteStoredAvatarFileIfOwned($profile->avatar_url);
            }
            $profile->avatar_url = $newUrl;
        }

        if ($pinTouched) {
            $pinPlain = (string) $request->input('pin');
            foreach ($customer->streamingProfiles()->where('id', '!=', $profile->id)->get() as $other) {
                if (Hash::check($pinPlain, $other->pin_hash)) {
                    return back()
                        ->withErrors(['pin' => 'Ese PIN ya lo usa otro espacio de este cliente. Elige 4 dígitos distintos para cada uno.'])
                        ->withInput();
                }
            }
            $profile->pin_hash = Hash::make($pinPlain);
        }

        $profile->save();

        $pfx = $this->routePrefix($request);

        return redirect()
            ->route($pfx.'.customers.streaming-profiles.edit', $customer)
            ->with('success', 'Espacio «'.$profile->name.'» actualizado.');
    }

    public function toggleSold(Request $request, User $customer, CustomerProfile $profile): RedirectResponse|JsonResponse
    {
        $this->assertPartnerOwnsCustomer($request, $customer);
        abort_unless((int) $profile->user_id === (int) $customer->id, 404);
        $this->authorize('manage', $profile);

        $profile->is_sold = ! $profile->is_sold;
        $profile->save();

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'is_sold' => (bool) $profile->fresh()->is_sold,
                'profile_id' => $profile->id,
            ]);
        }

        return back()->with('success', 'Estado del espacio «'.($profile->sort_order + 1).'» actualizado.');
    }

    private function assertPartnerOwnsCustomer(Request $request, User $customer): void
    {
        abort_unless($customer->isCustomer(), 404);
        abort_unless((int) $customer->parent_id === (int) $request->user()->id, 403);
    }

    private function routePrefix(Request $request): string
    {
        $u = $request->user();
        if ($u !== null && $u->isVendor()) {
            return 'vendor';
        }

        $p = $request->route('partner_route_prefix');

        return is_string($p) && $p !== '' ? $p : 'reseller';
    }

    private function deleteStoredAvatarFileIfOwned(?string $avatarUrl): void
    {
        if ($avatarUrl === null || $avatarUrl === '') {
            return;
        }

        $path = parse_url($avatarUrl, PHP_URL_PATH);
        if (! is_string($path)) {
            return;
        }

        if (! preg_match('#/storage/(customer-profiles/.+)$#', $path, $matches)) {
            return;
        }

        Storage::disk('public')->delete($matches[1]);
    }
}
