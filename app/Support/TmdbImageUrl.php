<?php

namespace App\Support;

/**
 * Ajustes de URL de imágenes TMDB para vistas que necesitan más resolución (hero, etc.).
 */
final class TmdbImageUrl
{
    /**
     * Sube tamaño típico w300/w342/w500 → w1280 para fondos anchos sin usar "original" (pesado).
     */
    public static function upsizePosterForHero(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return $url;
        }

        $url = trim($url);
        if (! preg_match('#^https://image\.tmdb\.org/t/p/(w\d+|original)(/.+)$#i', $url, $m)) {
            return $url;
        }

        $size = strtolower($m[1]);
        $path = $m[2];

        if ($size === 'original') {
            return $url;
        }

        $w = (int) substr($size, 1);
        if ($w >= 1280) {
            return $url;
        }

        return 'https://image.tmdb.org/t/p/w1280'.$path;
    }
}
