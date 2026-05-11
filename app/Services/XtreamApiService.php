<?php

namespace App\Services;

use App\Models\XtreamSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class XtreamApiService
{
    public function testConnection(string $host, string $username, string $password): bool
    {
        $data = $this->rawRequest($host, $username, $password, []);

        return is_array($data) && (
            (int) data_get($data, 'user_info.auth') === 1
            || data_get($data, 'user_info.auth') === true
        );
    }

    public function getLiveCategories(XtreamSource $source): array
    {
        return $this->unwrapList($this->request($source, ['action' => 'get_live_categories']));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getLiveStreams(XtreamSource $source, ?string $categoryId = null): array
    {
        $params = ['action' => 'get_live_streams'];
        if ($categoryId !== null && $categoryId !== '') {
            $params['category_id'] = $categoryId;
        }

        return $this->unwrapList($this->request($source, $params));
    }

    public function getVodCategories(XtreamSource $source): array
    {
        return $this->unwrapList($this->request($source, ['action' => 'get_vod_categories']));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getVodStreams(XtreamSource $source, ?string $categoryId = null): array
    {
        $params = ['action' => 'get_vod_streams'];
        if ($categoryId !== null && $categoryId !== '') {
            $params['category_id'] = $categoryId;
        }

        return $this->unwrapList($this->request($source, $params));
    }

    public function buildLiveUrl(XtreamSource $source, string $streamId): string
    {
        $base = rtrim($source->host, '/');

        return $base.'/live/'.$source->username.'/'.$source->password.'/'.rawurlencode($streamId).'.m3u8';
    }

    public function buildVodUrl(XtreamSource $source, string $streamId, string $extension = 'mp4'): string
    {
        $base = rtrim($source->host, '/');
        $ext = ltrim(strtolower($extension), '.');

        return $base.'/movie/'.$source->username.'/'.$source->password.'/'.rawurlencode($streamId).'.'.$ext;
    }

    public function buildSeriesEpisodeUrl(XtreamSource $source, string $episodeId, string $extension = 'mp4'): string
    {
        $base = rtrim($source->host, '/');
        $ext = ltrim(strtolower($extension), '.');

        return $base.'/series/'.$source->username.'/'.$source->password.'/'.rawurlencode($episodeId).'.'.$ext;
    }

    private function request(XtreamSource $source, array $query): mixed
    {
        return $this->rawRequest($source->host, $source->username, $source->password, $query);
    }

    private function rawRequest(string $host, string $username, string $password, array $query): mixed
    {
        $url = rtrim($host, '/').'/player_api.php';
        $query = array_merge([
            'username' => $username,
            'password' => $password,
        ], $query);

        try {
            $response = Http::timeout(20)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($url, $query);

            if (! $response->successful()) {
                Log::warning('Xtream API HTTP error', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::warning('Xtream API exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unwrapList(mixed $payload): array
    {
        if ($payload === null) {
            return [];
        }

        if (is_array($payload) && array_is_list($payload)) {
            return $payload;
        }

        if (! is_array($payload)) {
            return [];
        }

        foreach (['streams', 'data', 'categories', 'movies', 'episodes'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $inner = $payload[$key];

                return array_is_list($inner) ? $inner : array_values($inner);
            }
        }

        return array_values($payload);
    }
}
