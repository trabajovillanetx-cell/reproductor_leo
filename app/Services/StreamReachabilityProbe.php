<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Verifica rápidamente si una URL http(s) responde antes de registrar contenido desde M3U.
 *
 * Para listas IPTV típicas (master/media .m3u8) usa GET segmentado para validar que el cuerpo
 * sea lista HLS (#EXTM3U / #EXTINF / etiquetas EXT-X-*), así se descartan páginas HTML o errores camuflados con 200.
 * Otras URLs: HEAD y, si hace falta, GET parcial como antes.
 */
class StreamReachabilityProbe
{
    private array $memo = [];

    private const RANGE_BYTES_FIRST = 'bytes=0-16383';

    public function resetMemo(): void
    {
        $this->memo = [];
    }

    /**
     * @param  array<int, string>  $distinctUrls
     * @return array<string, bool>
     */
    public function evaluateManyDistinct(array $distinctUrls): array
    {
        $out = [];
        $distinctUrls = array_values(array_unique(array_filter(array_map(
            static fn ($u): string => trim((string) $u),
            $distinctUrls
        ))));
        foreach ($distinctUrls as $url) {
            if ($url === '') {
                continue;
            }
            if (isset($this->memo[$url])) {
                $out[$url] = $this->memo[$url];
            }
        }

        $poolSize = max(1, (int) config('m3u.probe_pool_size', 32));
        $still = array_values(array_filter($distinctUrls, fn (string $u): bool => ! array_key_exists($u, $out)));

        foreach (array_chunk($still, $poolSize) as $chunk) {
            $aliasToUrl = [];
            foreach ($chunk as $url) {
                $aliasToUrl[self::alias($url)] = $url;
            }

            /** @var array<string, Response|\Throwable|string> */
            $responses = Http::pool(function (Pool $pool) use ($aliasToUrl): void {
                foreach ($aliasToUrl as $alias => $url) {
                    if ($this->looksLikeHttpPlaylistUrl($url)) {
                        $this->enqueueRangeGetForPool($pool, $alias, $url);

                        continue;
                    }
                    $this->enqueueHeadForPool($pool, $alias, $url);
                }
            });

            foreach ($aliasToUrl as $alias => $url) {
                $response = $responses[$alias] ?? null;
                if ($this->looksLikeHttpPlaylistUrl($url)) {
                    $ok = $this->evaluateHttpPlaylistProbeResponse($response, $url);
                } else {
                    $ok = $this->finalizeNonPlaylistUrlProbe($url, $response);
                }

                $this->memo[$url] = $ok;
                $out[$url] = $ok;
            }
        }

        return $out;
    }

    private static function alias(string $url): string
    {
        return hash('sha256', $url);
    }

    /**
     * Incluye patrones típicos IPTV (/play/<id>/index.m3u8, etc.). No confunde .m3u con .m3u8 por extensión de ruta.
     */
    public function looksLikeHttpPlaylistUrl(string $url): bool
    {
        $lower = strtolower($url);
        $pathLower = strtolower((string) parse_url($url, PHP_URL_PATH));

        if (preg_match('#/play/[a-z0-9_-]+/(index\.m)?m3u8#i', $lower) === 1) {
            return true;
        }

        if ($pathLower !== '') {
            if (str_ends_with($pathLower, '.m3u8')) {
                return true;
            }
            if (str_ends_with($pathLower, '.m3u')) {
                return true;
            }
        }

        return str_contains($lower, '.m3u8');
    }

    private function enqueueHeadForPool(Pool $pool, string $alias, string $url): void
    {
        $p = $pool->as($alias)
            ->connectTimeout((float) config('m3u.probe_connect_seconds', 5))
            ->timeout((float) config('m3u.probe_timeout_seconds', 18))
            ->withHeaders($this->defaultHeaders());

        if (config('m3u.probe_allow_insecure_tls')) {
            $p = $p->withoutVerifying();
        }

        $p->head($url);
    }

    private function enqueueRangeGetForPool(Pool $pool, string $alias, string $url): void
    {
        $p = $pool->as($alias)
            ->connectTimeout((float) config('m3u.probe_connect_seconds', 5))
            ->timeout((float) config('m3u.probe_timeout_seconds', 18))
            ->withHeaders(array_merge($this->defaultHeaders(), ['Range' => self::RANGE_BYTES_FIRST]));

        if (config('m3u.probe_allow_insecure_tls')) {
            $p = $p->withoutVerifying();
        }

        $p->get($url);
    }

    /**
     * @return array<string, string>
     */
    private function defaultHeaders(): array
    {
        $headers = [
            // UA de navegador real para no ser bloqueados por servidores IPTV
            'User-Agent' => (string) config('m3u.probe_user_agent'),
            'Accept' => '*/*',
            'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
        ];

        // Si hay Referer configurado para el proxy HLS, también lo usamos en el probe
        $referer = trim((string) config('streaming.hls_upstream_referer', ''));
        if ($referer !== '') {
            $headers['Referer'] = $referer;
        }

        return $headers;
    }

    private function evaluateHttpPlaylistProbeResponse(mixed $response, string $url): bool
    {
        if ($response instanceof Response && $response->status() === 416) {
            return $this->evaluatePlaylistViaFullShortGet($url);
        }

        if ($response instanceof \Throwable) {
            return false;
        }

        if (! $response instanceof Response) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        return $this->httpPlaylistSnippetIsValid((string) $response->body());
    }

    /** Algunos servidores rechazan Range en listas minúsculas y responden 416. */
    private function evaluatePlaylistViaFullShortGet(string $url): bool
    {
        try {
            $pending = Http::connectTimeout((float) config('m3u.probe_connect_seconds', 5))
                ->timeout((float) config('m3u.probe_timeout_seconds', 18))
                ->withHeaders($this->defaultHeaders());

            if (config('m3u.probe_allow_insecure_tls')) {
                $pending = $pending->withoutVerifying();
            }

            $r = $pending->get($url);
            if (! $r->successful()) {
                return false;
            }

            return $this->httpPlaylistSnippetIsValid((string) $r->body());
        } catch (\Throwable) {
            return false;
        }
    }

    private function finalizeNonPlaylistUrlProbe(string $url, mixed $response): bool
    {
        $fromHead = $this->reachableFromProbeResponse($response, false);

        if ($fromHead !== null) {
            return $fromHead;
        }

        try {
            $r = $this->rangeGetRequest($url);
        } catch (\Throwable) {
            return false;
        }

        return $this->reachableFromProbeResponse($r, true) ?? false;
    }

    /** @phpstan-return bool|null null ⇒ probá GET */
    private function reachableFromProbeResponse(mixed $response, bool $hasBodySnippet): ?bool
    {
        if ($response instanceof \Throwable) {
            return null;
        }

        if (! $response instanceof Response) {
            return null;
        }

        if ($response->successful()) {
            if ($hasBodySnippet) {
                return $this->snippetLooksHealthyBinaryOrText($response->body(), $response);
            }

            $ct = strtolower((string) $response->header('Content-Type'));
            if (str_contains($ct, 'text/html')) {
                return null;
            }

            return true;
        }

        $status = $response->status();

        if (in_array($status, [405, 501], true)) {
            return null;
        }

        if (in_array($status, [404, 410], true)) {
            return false;
        }

        if ($status >= 400 && $status < 500) {
            return null;
        }

        return null;
    }

    private function snippetLooksHealthyBinaryOrText(string $body, Response $response): bool
    {
        if (trim($body) === '') {
            return false;
        }

        $preview = strtolower(substr($body, 0, 768));

        if (str_contains($preview, '<html')) {
            if (
                str_contains($preview, 'forbidden')
                || str_contains($preview, 'access denied')
                || str_contains($preview, 'unauthorized')
                || str_contains($preview, 'blocked')
                || str_contains($preview, 'not found')
                || str_contains($preview, 'login')
            ) {
                return false;
            }
        }

        $ct = strtolower((string) $response->header('Content-Type'));

        return ! str_contains($ct, 'text/html') || str_starts_with(trim($body), '#EXT');
    }

    public function httpPlaylistSnippetIsValid(string $body): bool
    {
        if (trim($body) === '') {
            return false;
        }

        $head = substr($body, 0, 6144);

        // Validación estándar HLS
        if (preg_match('/^\s*#EXTM3U/im', $head) === 1) {
            return true;
        }

        if (preg_match('/#EXTINF|#EXT-X-(STREAM-INF|MEDIA|TARGETDURATION)/i', $head) === 1) {
            return true;
        }

        // Algunos servidores IPTV responden con fragmento TS binario directamente (200 con video/mp2t)
        // Los primeros bytes de un TS son siempre 0x47 (sync byte)
        if (strlen($body) > 0 && ord($body[0]) === 0x47) {
            return true;
        }

        return false;
    }

    /** @throws \Throwable */
    private function rangeGetRequest(string $url): Response
    {
        $pending = Http::connectTimeout((float) config('m3u.probe_connect_seconds', 5))
            ->timeout((float) config('m3u.probe_timeout_seconds', 18))
            ->withHeaders(array_merge($this->defaultHeaders(), ['Range' => self::RANGE_BYTES_FIRST]));

        if (config('m3u.probe_allow_insecure_tls')) {
            $pending = $pending->withoutVerifying();
        }

        return $pending->get($url);
    }
}
