<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ThemeAssetService
{
    /** Solo borramos rutas bajo este prefijo (evita borrar URLs o rutas ajenas). */
    private const STORAGE_PREFIX = 'theme/';

    public function publicUrl(?string $relativePath): ?string
    {
        if ($relativePath === null || $relativePath === '') {
            return null;
        }

        if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
            return $relativePath;
        }

        if (! str_starts_with($relativePath, self::STORAGE_PREFIX)) {
            return null;
        }

        return Storage::disk('public')->url($relativePath);
    }

    /**
     * Sube un archivo a public disk, guarda la ruta en site_settings y elimina el archivo anterior si era nuestro.
     */
    public function replaceUploaded(UploadedFile $file, string $settingKey, string $filenameBase): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $newPath = self::STORAGE_PREFIX.$filenameBase.'.'.$ext;

        $old = SiteSetting::get($settingKey, '');
        if ($old !== '' && $old !== $newPath && $this->isManagedPath($old)) {
            Storage::disk('public')->delete($old);
        }

        $file->storeAs(dirname($newPath), basename($newPath), 'public');

        SiteSetting::put($settingKey, $newPath);

        return $newPath;
    }

    public function clearManaged(string $settingKey): void
    {
        $old = SiteSetting::get($settingKey, '');
        if ($old !== '' && $this->isManagedPath($old)) {
            Storage::disk('public')->delete($old);
        }

        SiteSetting::put($settingKey, null);
    }

    private function isManagedPath(string $path): bool
    {
        return str_starts_with($path, self::STORAGE_PREFIX);
    }
}
