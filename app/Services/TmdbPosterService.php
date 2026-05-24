<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Models\Content;
use App\Support\PosterTitleSanitizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TmdbPosterService
{
    public function isConfigured(): bool
    {
        $key = config('services.tmdb.key');

        return is_string($key) && trim($key) !== '';
    }

    /**
     * Comprueba que TMDB acepte la clave antes de lanzar cientos de búsquedas (si no, todas devuelven null).
     *
     * @return array{ok: bool, status: int, message: string}
     */
    public function verifyApiKey(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'status' => 0, 'message' => 'TMDB_API_KEY vacía o no definida.'];
        }

        try {
            $response = $this->tmdbHttp()
                ->acceptJson()
                ->get('https://api.themoviedb.org/3/configuration', [
                    'api_key' => config('services.tmdb.key'),
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return ['ok' => false, 'status' => 0, 'message' => 'Sin conexión a TMDB (timeout/red). Subí TMDB_CONNECT_TIMEOUT / TMDB_HTTP_TIMEOUT en .env o reintentá. '.$e->getMessage()];
        }

        $status = $response->status();
        if ($response->successful()) {
            return ['ok' => true, 'status' => $status, 'message' => ''];
        }

        $json = $response->json();
        $detail = '';
        if (is_array($json)) {
            if (isset($json['status_message']) && is_string($json['status_message'])) {
                $detail = $json['status_message'];
            } elseif (isset($json['errors'])) {
                $err = $json['errors'];
                $detail = is_array($err) ? implode(' ', array_map('strval', $err)) : (string) $err;
            }
        }
        if ($detail === '') {
            $detail = Str::limit(trim($response->body()), 200, '…');
        }

        return [
            'ok' => false,
            'status' => $status,
            'message' => Str::limit(trim($detail), 240, '…'),
        ];
    }

    /**
     * Busca en TMDB y devuelve URL absoluta del póster, o null.
     */
    public function posterUrlForTitle(string $title, ContentType $type, ?int $year = null): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $query = match ($type) {
            ContentType::Live => PosterTitleSanitizer::forLiveChannelSearch($title),
            default => PosterTitleSanitizer::forSearch($title),
        };
        if ($query === '') {
            return null;
        }

        $primary = match ($type) {
            ContentType::Series, ContentType::Live => 'search/tv',
            default => 'search/movie',
        };
        $alternate = match ($type) {
            ContentType::Series, ContentType::Live => 'search/movie',
            default => 'search/tv',
        };

        $configuredLang = trim((string) config('services.tmdb.language', 'es-ES'));
        if ($configuredLang === '') {
            $configuredLang = 'es-ES';
        }
        $languages = [$configuredLang];
        if ($configuredLang !== 'en-US') {
            $languages[] = 'en-US';
        }

        $queries = [$query];
        foreach ($this->extraTitleSearchQueries($query) as $alt) {
            if ($alt !== '' && $alt !== $query) {
                $queries[] = $alt;
            }
        }
        $queries = array_values(array_unique($queries));

        foreach ($queries as $searchQuery) {
            foreach ([$primary, $alternate] as $endpoint) {
                foreach ($languages as $language) {
                    if ($year !== null) {
                        $url = $this->searchBestPosterUrl($endpoint, $searchQuery, $language, $year);
                        if ($url !== null) { return $url; }
                    }
                    $url = $this->searchBestPosterUrl($endpoint, $searchQuery, $language);
                    if ($url !== null) { return $url; }
                }
            }
        }

        return null;
    }

    /**
     * Variantes cortas para títulos bilingües (ej. "Pesadilla maligna Malignant" → "Malignant").
     *
     * @return list<string>
     */
    private function extraTitleSearchQueries(string $cleanQuery): array
    {
        $words = preg_split('/\s+/u', trim($cleanQuery), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === []) {
            return [];
        }

        $out = [];
        $last = $words[count($words) - 1];
        if (preg_match('/^[A-Za-z][A-Za-z0-9]{2,24}$/', $last) && count($words) >= 2) {
            $out[] = $last;
        }

        if (count($words) >= 4) {
            $out[] = implode(' ', array_slice($words, 0, 3));
        }

        return $out;
    }

    /**
     * Entre los resultados con póster, elige el de mayor popularidad (mejor que el índice 0 a ciegas).
     */
    private function searchBestPosterUrl(string $endpoint, string $query, string $language, ?int $year = null): ?string
    {
        try {
            $response = $this->tmdbHttp()
                ->acceptJson()
                ->get('https://api.themoviedb.org/3/'.$endpoint, [
                    'api_key' => config('services.tmdb.key'),
                    'query' => $query,
                    'language' => $language,
                    'include_adult' => 'false',
                    ...($year !== null ? (str_contains($endpoint, 'tv') ? ['first_air_date_year' => $year] : ['primary_release_year' => $year]) : []),
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('tmdb.connection_error', [
                'endpoint' => $endpoint,
                'query' => Str::limit($query, 80),
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            $level = in_array($response->status(), [401, 403], true) ? 'warning' : 'debug';
            Log::log($level, 'tmdb.search_failed', [
                'status' => $response->status(),
                'query' => $query,
                'endpoint' => $endpoint,
                'language' => $language,
            ]);

            return null;
        }

        $results = $response->json('results');
        if (! is_array($results) || $results === []) {
            return null;
        }

        $bestPath = null;
        $bestPopularity = -1.0;

        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }
            $posterPath = $row['poster_path'] ?? null;
            if (! is_string($posterPath) || $posterPath === '') {
                continue;
            }
            $popularity = $row['popularity'] ?? null;
            $score = is_numeric($popularity) ? (float) $popularity : 0.0;
            if ($score >= $bestPopularity) {
                $bestPopularity = $score;
                $bestPath = $posterPath;
            }
        }

        if ($bestPath === null) {
            return null;
        }

        $base = rtrim((string) config('services.tmdb.image_base', 'https://image.tmdb.org/t/p/w500'), '/');

        return $base.$bestPath;
    }

    /**
     * Cliente HTTP hacia api.themoviedb.org con timeouts y reintentos (VPS con red lenta / SSL lento).
     */
    private function tmdbHttp(): \Illuminate\Http\Client\PendingRequest
    {
        $connect = max(5, (int) config('services.tmdb.connect_timeout', 20));
        $timeout = max(10, (int) config('services.tmdb.timeout', 30));
        $retries = max(0, min(8, (int) config('services.tmdb.retries', 3)));
        $delayMs = max(0, min(10_000, (int) config('services.tmdb.retry_delay_ms', 800)));

        $pending = Http::connectTimeout($connect)
            ->timeout($timeout);

        if ($retries > 0) {
            $pending = $pending->retry($retries, $delayMs, null, false);
        }

        return $pending;
    }

    /**
     * Si el contenido no tiene póster, intenta TMDB y persiste la URL.
     */
    public function enrichContentPoster(Content $content): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $current = trim((string) ($content->poster_url ?? ''));
        if ($current !== '') {
            return false;
        }

        $year = \App\Support\PosterTitleSanitizer::extractYear((string) $content->title);
        $url = $this->posterUrlForTitle((string) $content->title, $content->type, $year);
        if ($url === null) {
            return false;
        }

        $content->forceFill(['poster_url' => $url])->save();

        return true;
    }
}
