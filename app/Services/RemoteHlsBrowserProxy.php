<?php

namespace App\Services;

use App\Models\Content;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxifica playlists y segmentos HLS remotos por el mismo origen que la app (evita CORS/mixed-content
 * cuando Video.js/VHS sigue redirects a http desde una página https o el panel IPTV no envía cabeceras CORS).
 */
final class RemoteHlsBrowserProxy
{
    public function __construct(
        private LocalMediaService $localMedia,
        private StreamMimeResolver $mimeResolver,
    ) {}

    public function proxyEnabled(): bool
    {
        return (bool) config('streaming.hls_browser_proxy_enabled', true);
    }

    public function appliesTo(Content $content): bool
    {
        if (! $this->proxyEnabled()) {
            return false;
        }

        if ($this->localMedia->isLocalStream($content->stream_url)) {
            return false;
        }

        if ($this->mimeResolver->videoMime($content) === 'application/x-mpegURL') {
            return true;
        }

        $u = strtolower((string) $content->stream_url);

        // Patrones HLS explícitos
        if (str_contains($u, '.m3u8') || preg_match('#/play/[a-z0-9_-]+/(index\.m)?m3u8#', $u) === 1) {
            return true;
        }

        // Canales en vivo sin extensión (Xtream Codes: /live/user/pass/channelid)
        // Siempre se proxifican para evitar CORS y manejar Referer correctamente
        if ($content->type === \App\Enums\ContentType::Live && preg_match('#^https?://#i', $u)) {
            return true;
        }

        return false;
    }

    public function upstreamHeaders(): array
    {
        $ua = trim((string) config('streaming.hls_upstream_user_agent'));

        return array_filter([
            'User-Agent' => $ua !== '' ? $ua : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/121.0.0.0 Safari/537.36',
            'Accept' => '*/*',
            'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
        ]);
    }

    /** Respuesta inicial: playlist en stream_url rescrita contra /play/{id}/{token}?t=… */
    public function entryManifest(Content $content, string $playbackToken): \Symfony\Component\HttpFoundation\Response
    {
        $catalogUrl = (string) $content->stream_url;
        [$body, $finalUrl] = $this->pullRemote($catalogUrl, $catalogUrl);

        $head = ltrim($body, "\xEF\xBB\xBF \t\n\r");
        if ($head === '' || (! str_starts_with($head, '#EXTM3U') && ! $this->bodyLooksLikePlaylist($body))) {
            abort(502, 'El origen no respondió con un manifiesto HLS válido (lista caída o bloqueó al proxy).');
        }

        // Si es un master playlist (contiene EXT-X-STREAM-INF), seguir la primera variante
        // automáticamente para evitar que el número dinámico expire antes de que el navegador lo pida
        if ($this->isMasterPlaylist($body)) {
            $variantUrl = $this->extractFirstVariantUrl($body, $finalUrl);
            if ($variantUrl !== null) {
                [$body, $finalUrl] = $this->pullRemote($variantUrl, $catalogUrl);
                $head = ltrim($body, "\xEF\xBB\xBF \t\n\r");
                if ($head === '' || (! str_starts_with($head, '#EXTM3U') && ! $this->bodyLooksLikePlaylist($body))) {
                    // Si la variante también falla, volver al master original
                    [$body, $finalUrl] = $this->pullRemote($catalogUrl, $catalogUrl);
                }
            }
        }

        return response(
            $this->rewritePlaylist($body, (string) $finalUrl, $content, $playbackToken),
            200,
            [
                'Content-Type' => 'application/vnd.apple.mpegURL',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    /** Detecta si un playlist es master (contiene variantes de stream). */
    private function isMasterPlaylist(string $body): bool
    {
        return str_contains($body, '#EXT-X-STREAM-INF') || str_contains($body, 'EXT-X-STREAM-INF');
    }

    /** Extrae la URL de la primera variante de un master playlist. */
    private function extractFirstVariantUrl(string $body, string $baseUrl): ?string
    {
        try {
            $base = new Uri($baseUrl);
        } catch (\Throwable) {
            return null;
        }

        $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
        $nextIsUrl = false;

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                continue;
            }
            if (str_starts_with($trim, '#EXT-X-STREAM-INF')) {
                $nextIsUrl = true;
                continue;
            }
            if ($nextIsUrl && ! str_starts_with($trim, '#')) {
                try {
                    /** @phpstan-ignore argument.invalidType */
                    return (string) UriResolver::resolve($base, new Uri($trim));
                } catch (\Throwable) {
                    return null;
                }
            }
            if (str_starts_with($trim, '#')) {
                $nextIsUrl = false;
            }
        }

        return null;
    }

    /** Request subrogada (variante, llave AES, TS, AAC…). */
    public function relayPackedTarget(string $packed, Content $content, string $playbackToken): \Symfony\Component\HttpFoundation\Response
    {
        $decoded = $this->unpackUrl($packed);
        if ($decoded === null || $decoded === '') {
            abort(400, 'Destino inválido.');
        }

        if (! preg_match('#^https?://#i', $decoded)) {
            abort(400, 'Esquema no permitido.');
        }

        $this->assertRelayTargetAllowed($content, $decoded);

        return $this->relayAbsoluteUrl($decoded, $content, $playbackToken);
    }

    private function relayAbsoluteUrl(string $absoluteUrl, Content $content, string $playbackToken): \Symfony\Component\HttpFoundation\Response
    {
        $catalogStreamUrl = (string) $content->stream_url;
        $effectiveLower   = strtolower($absoluteUrl);

        // Segmentos binarios TS: descargar con cURL directo (más rápido, tolera cURL error 18)
        $isBinarySegment = preg_match('~\.(ts|aac|m4s|fmp4)(\?|$)~i', $effectiveLower) === 1
            || preg_match('~/play/[a-z0-9_-]+/\d+[_a-f0-9]*\.ts~i', $effectiveLower) === 1;

        if ($isBinarySegment) {
            return $this->fetchBinarySegment($absoluteUrl, $catalogStreamUrl);
        }

        // Manifiestos .m3u8: descargar completo y reescribir URLs
        [$binaryOrText, , $effective, $mime] = $this->pullRemoteWithMeta($absoluteUrl, $catalogStreamUrl);

        $lowerCt      = strtolower($mime);
        $effectiveLow = strtolower($effective);

        $looksPlaylist = (str_contains($lowerCt, 'mpeg') && str_contains($lowerCt, 'application'))
            || preg_match('~\.m3u8(\?|$)~i', $effectiveLow) === 1
            || (str_contains($effective, '/') && preg_match('~/play/[a-z0-9_-]+/(index\.m)?m3u8~i', $effectiveLow) === 1);

        if ($looksPlaylist || $this->bodyLooksLikePlaylist($binaryOrText)) {
            // Para canales en vivo con números dinámicos (ej: /play/a06d/47676934.m3u8),
            // el número expira en segundos. Siempre ir al master a buscar el número fresco.
            $isVariantWithDynamicNumber = preg_match('~/play/[a-z0-9_-]+/\d{6,}\.m3u8~i', strtolower($absoluteUrl)) === 1;

            if ($isVariantWithDynamicNumber || $this->bodyLooksLikeHtmlError($binaryOrText)) {
                // Ir al master playlist a buscar el número actual
                [$masterBody, $masterEffective] = $this->pullRemote($catalogStreamUrl, $catalogStreamUrl);
                if ($this->isMasterPlaylist($masterBody)) {
                    $variantUrl = $this->extractFirstVariantUrl($masterBody, $masterEffective);
                    if ($variantUrl !== null) {
                        [$freshBody, $freshEffective] = $this->pullRemote($variantUrl, $catalogStreamUrl);
                        if ($this->bodyLooksLikePlaylist($freshBody)) {
                            $binaryOrText = $freshBody;
                            $effective    = $freshEffective;
                        }
                    }
                } elseif ($this->bodyLooksLikePlaylist($masterBody)) {
                    // El master ya ES el media playlist (algunos servidores)
                    $binaryOrText = $masterBody;
                    $effective    = $masterEffective;
                }
            }

            return response(
                $this->rewritePlaylist($binaryOrText, $effective, $content, $playbackToken),
                200,
                [
                    'Content-Type' => 'application/vnd.apple.mpegURL',
                    'Cache-Control' => 'private, no-store',
                ]
            );
        }

        return response($binaryOrText, 200, array_filter([
            'Content-Type' => $mime !== '' ? $mime : 'video/mp2t',
            'Cache-Control' => 'private, no-store',
        ]));
    }

    /**
     * Segmentos TS/AAC/M4S vía cURL (cuerpo completo antes de responder).
     *
     * Importante: una {@see StreamedResponse} que no escribe bytes pero devuelve 200 dejaba a VHS “cargando”
     * para siempre. Aquí solo respondemos 200 si hay cuerpo (o cURL 18 con datos parciales en vivo).
     */
    private function fetchBinarySegment(string $url, string $catalogStreamUrl): \Symfony\Component\HttpFoundation\Response
    {
        $headers        = $this->buildUpstreamHeaders($url, $catalogStreamUrl);
        $verifySsl      = (bool) config('streaming.hls_upstream_verify_ssl', true);
        $connectTimeout = min(45, max(5, (int) config('streaming.hls_upstream_connect_timeout', 20)));
        $timeout        = min(240, max(15, (int) config('streaming.hls_upstream_timeout', 120)));

        $curlHeaders = [];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = "{$k}: {$v}";
        }

        $ch = curl_init($url);
        if ($ch === false) {
            abort(502, 'No se pudo inicializar cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_BUFFERSIZE     => 131072,
        ]);

        $body     = curl_exec($ch);
        $errno    = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            abort(502, 'Segmento no disponible (HTTP '.$httpCode.').');
        }

        $body = $body === false ? '' : (string) $body;

        // cURL 18 = cierre anticipado en vivo; si hubo bytes, sirven para que VHS no se quede colgado.
        if ($body === '' && ($errno !== 0 && $errno !== 18)) {
            Log::warning('HLS segmento vacío o error cURL', ['errno' => $errno, 'url' => $url]);
            abort(502, 'Segmento no disponible (cURL '.$errno.').');
        }

        if ($body === '' && $errno === 18) {
            abort(502, 'Segmento incompleto (canal en vivo). Reintentá.');
        }

        return response($body, 200, [
            'Content-Type'   => 'video/mp2t',
            'Cache-Control'  => 'private, no-store',
            'Content-Length' => strlen($body),
        ]);
    }

    /** @return array{0:string,1:string} body, effectiveUrl */
    private function pullRemote(string $url, string $catalogStreamUrl): array
    {
        [$body, $_status, $effective, $_mime] = $this->pullRemoteWithMeta($url, $catalogStreamUrl);

        return [$body, $effective];
    }

    /** @return array{0:string,1:int,2:string,3:string} */
    private function pullRemoteWithMeta(string $url, string $catalogStreamUrl): array
    {
        $headers = $this->buildUpstreamHeaders($url, $catalogStreamUrl);

        $pending = Http::connectTimeout(min(45, max(10, (int) config('streaming.hls_upstream_connect_timeout', 20))))
            ->timeout(min(240, max(45, (int) config('streaming.hls_upstream_timeout', 120))))
            ->withHeaders($headers);

        if (! (bool) config('streaming.hls_upstream_verify_ssl', true)) {
            $pending = $pending->withoutVerifying();
        }

        try {
            $res = $pending->get($url);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // cURL error 18: segmento TS cortado antes de terminar (live stream).
            // El servidor envió datos parciales pero cerró la conexión anticipadamente.
            // En vez de abortar con 500, intentamos usar los bytes que llegaron via transferStatistics.
            // Si no hay nada, abortamos con 502.
            $message = $e->getMessage();
            if (str_contains($message, 'cURL error 18') || str_contains($message, 'end of response')) {
                // Segmento parcial — lo ignoramos y devolvemos vacío para que el player reintente
                abort(502, 'Segmento de stream incompleto (canal en vivo). El reproductor reintentará.');
            }
            abort(502, 'Error de conexión al servidor upstream: '.$e->getMessage());
        }

        if ($res->serverError()) {
            abort(502, 'Servidor upstream no disponible.');
        }

        if (! $res->successful()) {
            abort(403, 'Contenido no accesible para proxy.');
        }

        /** @phpstan-ignore nullCoalesce.variable */
        $effective = (string) ($res->effectiveUri()?->__toString() ?? $url);
        $ct = strtolower((string) $res->header('Content-Type'));
        /** @phpstan-ignore argument.invalidType */
        $body = $res->body();

        return [$body, $res->status(), $effective, $ct !== '' ? $ct : 'application/octet-stream'];
    }

    /**
     * Muchos proveedores comparan Referer/Origin con el panel; sin esto el manifiesto llega vacío o HTML 403 y VHS queda colgado.
     *
     * @return array<string, string>
     */
    private function buildUpstreamHeaders(string $requestUrl, string $catalogStreamUrl): array
    {
        $headers = $this->upstreamHeaders();

        $custom = trim((string) config('streaming.hls_upstream_referer', ''));
        if ($custom !== '') {
            $headers['Referer'] = $custom;
            $origin = $this->originFromUrlWithPort($custom);
            if ($origin !== '') {
                $headers['Origin'] = $origin;
            }

            return $headers;
        }

        $policy = (string) config('streaming.hls_upstream_referer_policy', 'catalog');
        if ($policy === 'none' || $policy === '') {
            return $headers;
        }

        $baseForReferer = $policy === 'request' ? $requestUrl : $catalogStreamUrl;
        $referer = $this->refererDirectoryUrl($baseForReferer);
        if ($referer === '') {
            return $headers;
        }

        $headers['Referer'] = $referer;
        $origin = $this->originFromUrlWithPort($referer);
        if ($origin !== '') {
            $headers['Origin'] = $origin;
        }

        return $headers;
    }

    /** Referer típico navegador: origen + directorio del recurso (termina en /). */
    private function refererDirectoryUrl(string $url): string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.((int) $parts['port']) : '';

        $path = isset($parts['path']) ? str_replace('\\', '/', (string) $parts['path']) : '/';
        if ($path === '' || $path[0] !== '/') {
            $path = '/'.$path;
        }

        $dir = dirname($path);
        if ($dir === '/' || $dir === '.' || $dir === '\\') {
            return $scheme.'://'.$host.$port.'/';
        }

        return $scheme.'://'.$host.$port.rtrim($dir, '/').'/';
    }

    /** Origin para cabecera HTTP (sin barra final). */
    private function originFromUrlWithPort(string $url): string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);

        return isset($parts['port'])
            ? $scheme.'://'.$host.':'.$parts['port']
            : $scheme.'://'.$host;
    }

    private function rewritePlaylist(string $body, string $playlistEffectiveUrl, Content $content, string $playbackToken): string
    {
        if ($playbackToken === '') {
            abort(500, 'Falta token de reproducción para reescribir playlist.');
        }

        try {
            $base = new Uri($playlistEffectiveUrl);
        } catch (\Throwable) {
            abort(500, 'URL de playlist inválida.');
        }

        $body = preg_replace_callback('/URI="([^"]+)"/', function (array $m) use ($base, $content, $playbackToken): string {
            $inner = trim($m[1]);
            if ($inner === '') {
                return $m[0];
            }

            /** @phpstan-ignore argument.invalidType */
            $resolved = (string) UriResolver::resolve($base, new Uri($inner));

            return 'URI="'.$this->proxiedUrl($resolved, $content, $playbackToken).'"';
        }, $body) ?? $body;

        $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
        $out = [];

        $appOrigin = self::canonicalOrigin((string) config('app.url'));
        $isOurOrigin = fn (string $maybeUrl): bool => $appOrigin !== null
            && self::canonicalOrigin($maybeUrl) === $appOrigin;

        foreach ($lines as $line) {
            $trim = trim((string) $line);
            if ($trim === '' || str_starts_with($trim, '#')) {
                $out[] = $line;

                continue;
            }

            if (preg_match('#^https?://#i', $trim) === 1 && $isOurOrigin($trim)) {
                $out[] = $line;

                continue;
            }

            $resolved = (string) UriResolver::resolve($base, new Uri($trim));

            // Evita líneas repetidas donde ya coincide con contenido saneado igual
            if (! preg_match('#^https?://#i', $resolved)) {
                $out[] = $line;

                continue;
            }

            $out[] = $this->proxiedUrl($resolved, $content, $playbackToken);
        }

        return implode("\n", $out);
    }

    private function bodyLooksLikePlaylist(string $body): bool
    {
        $head = ltrim(mb_substr($body, 0, 768));

        return str_starts_with($head, '#EXTM3U')
            || (bool) preg_match('~#\s*EXT(?:INF|M3U|X-|MEDIA|STREAM-INF)~', $body);
    }

    private function bodyLooksLikeHtmlError(string $body): bool
    {
        $head = strtolower(ltrim(mb_substr($body, 0, 512)));
        return str_contains($head, '<html') || str_contains($head, 'not found') || str_contains($head, '404');
    }

    private function proxiedUrl(string $resolvedAbsoluteUrl, Content $content, string $playbackToken): string
    {
        return route('play.stream', ['content' => $content->id, 'token' => $playbackToken]).'?t='.$this->packUrl($resolvedAbsoluteUrl);
    }

    public function packUrl(string $absolute): string
    {
        return rtrim(strtr(base64_encode($absolute), '+/', '-_'), '=');
    }

    /**
     * @api used by Blade tests / tooling
     */
    public function unpackUrl(string $packed): ?string
    {
        $packed = trim($packed);
        if ($packed === '') {
            return null;
        }

        $b64 = strtr($packed, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        $raw = base64_decode($b64, true);

        return $raw === false ? null : $raw;
    }

    private function assertRelayTargetAllowed(Content $content, string $targetUrl): void
    {
        if ($this->proxySameCatalogOriginOnly()) {
            $this->assertStrictSameCatalogOrigin((string) $content->stream_url, $targetUrl);

            return;
        }

        $this->assertNotSsrfPrivateTarget($targetUrl);
    }

    private function proxySameCatalogOriginOnly(): bool
    {
        return (bool) config('streaming.hls_proxy_same_origin_only', false);
    }

    /** Mismo host+scheme normalizado que el stream del ítem en catálogo (modo cerrado IPTV monocanal). */
    private function assertStrictSameCatalogOrigin(string $catalogUrl, string $targetUrl): void
    {
        $o1 = self::canonicalOrigin($catalogUrl);
        $o2 = self::canonicalOrigin($targetUrl);

        if ($o1 === null || $o2 === null || $o1 !== $o2) {
            abort(403, 'Dominio externo bloqueado para proxy.');
        }
    }

    /**
     * Modo habitual listas CDN: permite cualquier HTTPS/HTTP público; bloquea rangos RFC1918 / loopback.
     *
     * @see RemoteHlsBrowserProxy::relayPackedTarget Requiere token de reproducción válido antes de llegar aquí.
     */
    private function assertNotSsrfPrivateTarget(string $targetUrl): void
    {
        $parsed = parse_url(trim($targetUrl));

        if (! is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            abort(400, 'Destino proxy inválido.');
        }

        $scheme = strtolower((string) $parsed['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            abort(400, 'Esquema no permitido para proxy.');
        }

        $host = strtolower((string) $parsed['host']);
        $hostBare = preg_match('#^\[(.+)]$#', $host, $m) === 1 ? strtolower((string) $m[1]) : $host;

        if ($hostBare === '' || in_array($hostBare, ['localhost', '::1', '127.0.0.1', '0.0.0.0'], true)) {
            abort(403, 'Destino proxy no permitido.');
        }

        if (str_ends_with($hostBare, '.localhost') || str_ends_with($hostBare, '.local')) {
            abort(403, 'Destino proxy no permitido.');
        }

        if ($hostBare === 'metadata' || preg_match('#^metadata\.google\.internal$#', $hostBare) === 1) {
            abort(403, 'Destino proxy no permitido.');
        }

        if (filter_var($hostBare, FILTER_VALIDATE_IP)) {
            if (! filter_var($hostBare, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                abort(403, 'Red privada o reservada no permitida.');
            }

            return;
        }

        $ips = @gethostbynamel($hostBare);
        if (is_array($ips) && $ips !== []) {
            foreach ($ips as $ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    abort(403, 'DNS del destino apunta a red privada/reservada.');
                }
            }
        }
    }

    private static function canonicalOrigin(string $url): ?string
    {
        $p = parse_url(trim($url));
        if (! is_array($p) || empty($p['scheme']) || empty($p['host'])) {
            return null;
        }

        $scheme = strtolower((string) $p['scheme']);
        $host = strtolower((string) $p['host']);
        $port = $p['port'] ?? null;

        if ($port !== null && is_numeric($port)) {
            return $scheme.'://'.$host.':'.((int) $port);
        }

        $fallback = strtolower($scheme) === 'https' ? 443 : 80;

        return $scheme.'://'.$host.':'.$fallback;
    }

}
