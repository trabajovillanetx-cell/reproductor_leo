<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JellyfinPosterService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.jellyfin.url', ''), '/');
        $this->apiKey  = (string) config('services.jellyfin.api_key', '');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    public function posterUrlForSeries(string $seriesName): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::connectTimeout(10)
                ->timeout(15)
                ->withHeaders(['X-Emby-Token' => $this->apiKey])
                ->acceptJson()
                ->get($this->baseUrl . '/Items', [
                    'searchTerm'        => $seriesName,
                    'IncludeItemTypes'  => 'Series',
                    'Recursive'         => 'true',
                    'Fields'            => 'PrimaryImageAspectRatio',
                    'Limit'             => 5,
                ]);
        } catch (\Throwable $e) {
            Log::warning('jellyfin.connection_error', ['series' => $seriesName, 'error' => $e->getMessage()]);
            return null;
        }

        if (! $response->successful()) {
            Log::debug('jellyfin.search_failed', ['status' => $response->status(), 'series' => $seriesName]);
            return null;
        }

        $items = $response->json('Items');
        if (! is_array($items) || empty($items)) {
            return null;
        }

        foreach ($items as $item) {
            if (! is_array($item)) continue;
            $itemId     = $item['Id'] ?? null;
            $imageCount = $item['ImageTags'] ?? [];
            if ($itemId && isset($imageCount['Primary'])) {
                return $this->baseUrl . '/Items/' . $itemId . '/Images/Primary?api_key=' . $this->apiKey . '&quality=90';
            }
        }

        return null;
    }

    public function verify(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'status' => 0, 'message' => 'JELLYFIN_URL o JELLYFIN_API_KEY no configuradas en .env'];
        }

        try {
            $response = Http::connectTimeout(10)->timeout(15)->acceptJson()
                ->get($this->baseUrl . '/System/Info/Public');
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 0, 'message' => $e->getMessage()];
        }

        if ($response->successful()) {
            $name = $response->json('ServerName', 'Jellyfin');
            return ['ok' => true, 'status' => $response->status(), 'message' => "Conectado a: {$name}"];
        }

        return ['ok' => false, 'status' => $response->status(), 'message' => 'HTTP ' . $response->status()];
    }
}
