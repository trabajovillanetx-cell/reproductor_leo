<?php

namespace App\Support;

final class PhpUploadLimits
{
    /**
     * Convierte valores tipo "128M", "8G" de php.ini a bytes.
     */
    public static function iniShorthandToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtoupper(substr($value, -1));
        $numeric = (int) $value;

        return match ($unit) {
            'G' => $numeric * 1024 * 1024 * 1024,
            'M' => $numeric * 1024 * 1024,
            'K' => $numeric * 1024,
            default => (int) $value,
        };
    }

    public static function uploadMaxBytes(): int
    {
        return self::iniShorthandToBytes((string) ini_get('upload_max_filesize'));
    }

    public static function postMaxBytes(): int
    {
        return self::iniShorthandToBytes((string) ini_get('post_max_size'));
    }

    /**
     * Tamaño máximo práctico de un archivo en multipart (upload y post deben albergar el cuerpo).
     */
    public static function effectiveMaxBytes(): int
    {
        return min(self::uploadMaxBytes(), self::postMaxBytes());
    }

    public static function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        $n = $bytes / (1024 ** $i);

        return round($n, $i === 0 ? 0 : 1).' '.$units[$i];
    }

    /**
     * Límite en kilobytes para reglas de validación Laravel `max:` en archivos.
     */
    public static function effectiveMaxKilobytesForValidation(int $appCapKb = 409600): int
    {
        $effectiveBytes = self::effectiveMaxBytes();
        if ($effectiveBytes <= 0) {
            return $appCapKb;
        }

        $effectiveKb = (int) floor($effectiveBytes / 1024);

        return max(512, min($appCapKb, $effectiveKb));
    }
}
