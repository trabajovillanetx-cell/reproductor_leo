<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Carátulas subidas por el admin viven bajo storage público en imports/content-posters.
 */
final class ManagedContentPosterFiles
{
    public const UPLOAD_SUBDIR = 'imports/content-posters';

    public static function deleteIfManaged(?string $posterUrl): void
    {
        $path = self::diskPath($posterUrl);
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }

    public static function diskPath(?string $posterUrl): ?string
    {
        if ($posterUrl === null) {
            return null;
        }
        $posterUrl = trim($posterUrl);
        if ($posterUrl === '') {
            return null;
        }
        $parsed = parse_url($posterUrl, PHP_URL_PATH);
        if (! is_string($parsed) || $parsed === '') {
            return null;
        }
        $relative = ltrim($parsed, '/');
        if (! str_starts_with($relative, 'storage/')) {
            return null;
        }
        $diskPath = substr($relative, strlen('storage/'));
        $prefix = self::UPLOAD_SUBDIR.'/';
        if (! str_starts_with($diskPath, $prefix)) {
            return null;
        }

        return $diskPath;
    }
}
