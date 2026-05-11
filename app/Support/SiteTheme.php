<?php

namespace App\Support;

use App\Models\SiteSetting;
use App\Services\ThemeAssetService;
use Illuminate\Support\Facades\App;

final class SiteTheme
{
    public const KEY_LOGIN_BG = 'theme_login_background_path';

    public const KEY_LOGIN_LOGO = 'theme_login_logo_path';

    public const KEY_APP_BG = 'theme_app_background_path';

    public const KEY_PROFILES_PICKER = 'theme_profiles_picker_path';

    public const KEY_FAVICON = 'theme_favicon_path';

    /** Fondo /login: subida admin o URL .env */
    public static function loginBackgroundUrl(): string
    {
        $svc = App::make(ThemeAssetService::class);
        $path = SiteSetting::get(self::KEY_LOGIN_BG, '');
        $fromDisk = $path !== '' ? $svc->publicUrl($path) : null;
        if ($fromDisk !== null && $fromDisk !== '') {
            return $fromDisk;
        }

        return trim((string) config('streaming.login_background_url', ''));
    }

    /** Logo imagen encima del formulario en /login (opcional). */
    public static function loginLogoUrl(): ?string
    {
        $svc = App::make(ThemeAssetService::class);
        $path = SiteSetting::get(self::KEY_LOGIN_LOGO, '');

        return $path !== '' ? $svc->publicUrl($path) : null;
    }

    /** Fondo catálogo (streaming shell). */
    public static function appBackgroundUrl(): string
    {
        $svc = App::make(ThemeAssetService::class);
        $path = SiteSetting::get(self::KEY_APP_BG, '');
        $fromDisk = $path !== '' ? $svc->publicUrl($path) : null;
        if ($fromDisk !== null && $fromDisk !== '') {
            return $fromDisk;
        }

        return trim((string) config('streaming.app_background_url', ''));
    }

    /** Favicon global (URL pública o null). */
    public static function faviconUrl(): ?string
    {
        $svc = App::make(ThemeAssetService::class);
        $path = SiteSetting::get(self::KEY_FAVICON, '');

        return $path !== '' ? $svc->publicUrl($path) : null;
    }

    /**
     * Fondo elegir perfil: subida → URL legacy en site_settings → .env → default.
     */
    public static function profilesPickerBackgroundUrl(): string
    {
        $svc = App::make(ThemeAssetService::class);
        $uploaded = SiteSetting::get(self::KEY_PROFILES_PICKER, '');
        if ($uploaded !== '') {
            $u = $svc->publicUrl($uploaded);
            if ($u !== null && $u !== '') {
                return $u;
            }
        }

        $db = trim((string) SiteSetting::get('profiles_picker_background_url', ''));
        if ($db !== '') {
            return $db;
        }

        $env = trim((string) config('streaming.profiles_picker_background_url', ''));
        if ($env !== '') {
            return $env;
        }

        return trim((string) config('streaming.profiles_picker_default_background_url', ''));
    }
}
