<?php

namespace App\Support;

/**
 * Etiquetas legibles para rutas / títulos en el catálogo cliente (URL-encoded, etc.).
 */
final class StreamingLabel
{
    public static function decode(string $value): string
    {
        $s = rawurldecode(trim($value));
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace('+', ' ', $s));
    }

    /**
     * Comparación de rutas de biblioteca (Windows / distintas mayúsculas en URL vs disco).
     */
    public static function normalizeLibraryPath(string $path): string
    {
        return mb_strtolower(trim(str_replace('\\', '/', $path), '/'), 'UTF-8');
    }
}
