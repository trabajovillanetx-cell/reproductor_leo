<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\ThemeAssetService;
use App\Support\SiteTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ThemeAssetsController extends Controller
{
    public function edit(): View
    {
        $this->authorize('viewAny', \App\Models\User::class);

        return view('admin.theme-assets.edit', [
            'loginBackgroundUrl' => SiteTheme::loginBackgroundUrl(),
            'loginLogoUrl' => SiteTheme::loginLogoUrl(),
            'appBackgroundUrl' => SiteTheme::appBackgroundUrl(),
            'profilesPickerUrl' => SiteTheme::profilesPickerBackgroundUrl(),
            'faviconUrl' => SiteTheme::faviconUrl(),
            'profilesExternalUrl' => trim((string) SiteSetting::get('profiles_picker_background_url', '')),
        ]);
    }

    public function update(Request $request, ThemeAssetService $assets): RedirectResponse
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $imageRules = ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'];
        $logoRules = ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif,svg', 'max:3072'];
        $faviconRules = ['nullable', 'file', 'mimes:ico,png,gif,jpeg,jpg,webp,svg', 'max:1024'];

        $request->validate([
            'login_background' => $imageRules,
            'login_logo' => $logoRules,
            'app_background' => $imageRules,
            'profiles_picker' => $imageRules,
            'favicon' => $faviconRules,
            'remove_login_background' => ['nullable', 'boolean'],
            'remove_login_logo' => ['nullable', 'boolean'],
            'remove_app_background' => ['nullable', 'boolean'],
            'remove_profiles_picker' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('remove_login_background')) {
            $assets->clearManaged(SiteTheme::KEY_LOGIN_BG);
        } elseif ($request->hasFile('login_background')) {
            $assets->replaceUploaded($request->file('login_background'), SiteTheme::KEY_LOGIN_BG, 'login-background');
        }

        if ($request->boolean('remove_login_logo')) {
            $assets->clearManaged(SiteTheme::KEY_LOGIN_LOGO);
        } elseif ($request->hasFile('login_logo')) {
            $assets->replaceUploaded($request->file('login_logo'), SiteTheme::KEY_LOGIN_LOGO, 'login-logo');
        }

        if ($request->boolean('remove_app_background')) {
            $assets->clearManaged(SiteTheme::KEY_APP_BG);
        } elseif ($request->hasFile('app_background')) {
            $assets->replaceUploaded($request->file('app_background'), SiteTheme::KEY_APP_BG, 'app-background');
        }

        if ($request->boolean('remove_profiles_picker')) {
            $assets->clearManaged(SiteTheme::KEY_PROFILES_PICKER);
        } elseif ($request->hasFile('profiles_picker')) {
            $assets->replaceUploaded($request->file('profiles_picker'), SiteTheme::KEY_PROFILES_PICKER, 'profiles-picker');
        }

        if ($request->boolean('remove_favicon')) {
            $assets->clearManaged(SiteTheme::KEY_FAVICON);
        } elseif ($request->hasFile('favicon')) {
            $assets->replaceUploaded($request->file('favicon'), SiteTheme::KEY_FAVICON, 'favicon');
        }

        $rawUrl = trim((string) $request->input('profiles_picker_background_url', ''));
        if ($rawUrl === '') {
            SiteSetting::put('profiles_picker_background_url', null);
        } else {
            $request->validate(['profiles_picker_background_url' => ['required', 'url', 'max:2048']]);
            SiteSetting::put('profiles_picker_background_url', $rawUrl);
        }

        return redirect()->route('admin.theme-assets.edit')->with('success', 'Imágenes del sitio actualizadas. Los archivos anteriores bajo /storage/theme fueron reemplazados o eliminados.');
    }
}
