<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Models\Content;

/**
 * Tipo MIME aproximado del origen para <video> / Video.js (misma heurística que el reproductor).
 */
final class StreamMimeResolver
{
    public function __construct(
        private LocalMediaService $localMedia,
    ) {}

    public function videoMime(Content $content): string
    {
        $ref = $content->stream_url;

        if ($this->localMedia->isLocalStream($ref)) {
            $path = $this->localMedia->absolutePathFromStreamUrl($ref) ?? '';

            return $this->mimeFromPath($path);
        }

        // Los canales en vivo de paneles IPTV usan URLs tipo:
        //   /live/user/pass/12345  (sin extensión)
        //   get.php?username=x&password=y&type=m3u_plus
        // Sin extensión .m3u8 el resolver los trata como mp4 y nunca pasan por el proxy.
        // Si el Content es tipo Live y la URL no termina en un formato de vídeo conocido,
        // lo forzamos a HLS para que el proxy lo maneje.
        if ($content->type === ContentType::Live) {
            $mime = $this->mimeFromRemoteReference((string) $ref);
            if ($mime === 'video/mp4') {
                // video/mp4 es el fallback por defecto; para Live sin extensión clara => HLS
                return 'application/x-mpegURL';
            }

            return $mime;
        }

        return $this->mimeFromRemoteReference((string) $ref);
    }

    /**
     * Si el navegador puede mostrar una vista corta sin Video.js completo (MP4/WebM/HLS con hls.js).
     */
    public function supportsHeroPreview(Content $content): bool
    {
        if (! $content->is_active || $content->type === ContentType::Live) {
            return false;
        }

        return match ($this->videoMime($content)) {
            'application/x-mpegURL',
            'video/mp4',
            'video/webm',
            'video/quicktime',
            'video/x-matroska',
            'video/x-msvideo',
            'video/mpeg',
            'video/mp2t',
            'video/ogg' => true,
            default => false,
        };
    }

    public function mimeFromPath(string $ref): string
    {
        if (preg_match('#^https?://#i', $ref) === 1) {
            return $this->mimeFromRemoteReference($ref);
        }

        $ext = strtolower(pathinfo($ref, PATHINFO_EXTENSION));

        return match ($ext) {
            'm3u8' => 'application/x-mpegURL',
            'webm' => 'video/webm',
            'mp4', 'm4v' => 'video/mp4',
            'mov' => 'video/quicktime',
            'mkv' => 'video/x-matroska',
            'avi' => 'video/x-msvideo',
            'mpeg', 'mpg' => 'video/mpeg',
            'ts' => 'video/mp2t',
            'ogv', 'ogg' => 'video/ogg',
            default => 'video/mp4',
        };
    }

    /**
     * URL remota típica de panel IPTV: el .m3u8 suele estar en path o query; pathinfo(URL entera) a veces devuelve extensión vacía
     * y Video.js/VHS terminaba tratando manifest como mp4 (“carga infinita”).
     */
    private function mimeFromRemoteReference(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $query = (string) (parse_url($url, PHP_URL_QUERY) ?? '');
        $combined = strtolower($path.($query !== '' ? '?'.$query : ''));

        $looksHls = str_contains($combined, '.m3u8')
            || preg_match('#/play/[a-z0-9_-]+/(index\.m)?m3u8#', $combined) === 1
            // Patrones típicos de paneles IPTV Xtream Codes: /live/user/pass/channelid
            || preg_match('#/(live|stream|streaming|channel|tv)/[^/]+/[^/]+/\d+$#i', $path) === 1
            // get.php con type=m3u (Xtream Codes API)
            || (str_contains($combined, 'get.php') && str_contains($combined, 'type='));

        if ($looksHls) {
            return 'application/x-mpegURL';
        }

        $leaf = $path !== '' ? basename($path) : basename(trim((string) parse_url($url, PHP_URL_PATH), '/'));
        $ext = strtolower(pathinfo($leaf, PATHINFO_EXTENSION));

        return match ($ext) {
            'm3u8' => 'application/x-mpegURL',
            'webm' => 'video/webm',
            'mp4', 'm4v' => 'video/mp4',
            'mov' => 'video/quicktime',
            'mkv' => 'video/x-matroska',
            'avi' => 'video/x-msvideo',
            'mpeg', 'mpg' => 'video/mpeg',
            'ts' => 'video/mp2t',
            'ogv', 'ogg' => 'video/ogg',
            default => 'video/mp4',
        };
    }
}
