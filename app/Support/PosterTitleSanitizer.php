<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Limpia títulos de archivo / importación para buscar en TMDB u otros metadatos.
 */
final class PosterTitleSanitizer
{
    /**
     * Limpieza extra para nombres de canal en listas IPTV (#EXTINF:-1,010 - ESPN, 53 SPACE HD 10728 V, etc.).
     */
    public static function forLiveChannelSearch(string $title): string
    {
        $t = rawurldecode(trim($title));
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // "010 - ESPN", "58-HBO FAMILY HD"
        $t = preg_replace('/^\d{1,4}\s*-\s*/u', '', $t) ?? $t;
        // "24 ESPN COLOMBIA" (numeración EPG antes del nombre)
        $t = preg_replace('/^\d{1,3}\s+(?=[\p{L}"\'])/u', '', $t) ?? $t;
        // Sufijos técnicos frecuentes: "SPACE HD 10728 V"
        $t = preg_replace('/\s+[0-9]{3,6}\s+V?\s*$/iu', '', $t) ?? $t;

        return self::forSearch($t);
    }

    public static function forSearch(string $title): string
    {
        $t = rawurldecode(trim($title));
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = str_replace('+', ' ', $t);
        $t = str_replace(['_', '.'], ' ', $t);
        // Títulos pegados tipo "tiempoSidelined" → espacio antes de mayúscula (latin-1)
        $t = preg_replace('/([a-záéíóúñ])([A-ZÁÉÍÓÚÑ])/u', '$1 $2', $t) ?? $t;
        $t = preg_replace('/\.(mkv|mp4|m4v|avi|mov|wmv|webm)$/iu', '', $t) ?? $t;
        $t = preg_replace('/\s*\(\s*(19|20)\d{2}\s*\)/u', ' ', $t) ?? $t;
        // Corchetes típicos de release (aunque no listemos el tag exacto)
        $t = preg_replace('/\s*\[[^\]]*(?:1080p|720p|2160p|4k|web-?dl|bluray|hdtv|x264|hevc|hdr|nf|amzn|hmax|hbo|latino|castellano|ac3|dd5|x265)[^\]]*\]\s*/iu', ' ', $t) ?? $t;
        $t = preg_replace('/\s*\[[^\]]{1,120}\]\s*/u', ' ', $t) ?? $t;
        $t = preg_replace('/\s*\([^)]*(?:1080p|720p|2160p|web-?dl|bluray|hdtv|x264|hevc|nf|amzn|hmax|hbo|dvdrip)[^)]*\)/iu', '', $t) ?? $t;
        $t = preg_replace('/\b(?:web-?dl|bluray|hdtv|dvdrip|hbo\w*|amzn|nf)\b/iu', '', $t) ?? $t;
        $t = preg_replace('/\b(?:480|720|1080|2160|4320)p\b/iu', '', $t) ?? $t;
        // Tags muy frecuentes en Latinoamérica / cams (no suelen estar en TMDB)
        $t = preg_replace('/\b(?:HD\s*Latino|HD\s*Castellano|HD\s*Sub|HDTS|HDCAM|TELESYNC|WORKPRINT|LATINO|CASTELLANO|SUB(?:TITULAD[OA])?|ESPA[ÑN]OL|DUAL\s*AUDIO|VERSI[ÓO]N\s*EXTENDIDA|EXTENDED\s*CUT)\b/iu', '', $t) ?? $t;
        $t = preg_replace('/\b(?:TS|CAM|SCR)\b/iu', '', $t) ?? $t;
        $t = preg_replace('/\bHD\b/iu', '', $t) ?? $t;
        $t = preg_replace('/\b(?:S\d{1,2}\s*[Ee]\d{1,2}|\d{1,2}\s*[Xx]\s*\d{1,2})\b/u', '', $t) ?? $t;
        $t = preg_replace('/\s{2,}/u', ' ', $t) ?? $t;

        return Str::limit(trim($t), 140, '');
    }
}
