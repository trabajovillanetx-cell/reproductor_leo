<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class ChannelStreamDiagnosticsService
{
    /**
     * @return array<string, mixed>
     */
    public function diagnose(string $url): array
    {
        $url = trim($url);
        $out = [
            'url' => $url,
            'reachable' => false,
            'http_status' => null,
            'latency_ms' => null,
            'manifest_kind' => null,
            'video_codecs' => [],
            'audio_codecs' => [],
            'ac3_warning' => false,
            'dynamic_path_hint' => false,
            'snippet' => null,
            'error' => null,
        ];

        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            $out['error'] = 'URL vacía o esquema no permitido (solo http/https).';

            return $out;
        }

        $t0 = microtime(true);
        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'User-Agent' => (string) config('streaming.hls_upstream_user_agent'),
                    'Accept' => '*/*',
                ])
                ->withOptions(['verify' => (bool) config('streaming.hls_upstream_verify_ssl', true)])
                ->get($url);
        } catch (\Throwable $e) {
            $out['error'] = 'Error de conexión: '.$e->getMessage();

            return $out;
        }

        $out['latency_ms'] = (int) round((microtime(true) - $t0) * 1000);
        $out['http_status'] = $response->status();
        $out['reachable'] = $response->successful();

        if (! $response->successful()) {
            $out['error'] = 'HTTP '.$response->status();

            return $out;
        }

        $body = $response->body();
        $out['snippet'] = Str::limit($body, 800);

        if (stripos($body, '#EXTM3U') === 0 || str_contains($body, '#EXTM3U')) {
            $out['manifest_kind'] = str_contains($body, '#EXT-X-STREAM-INF') ? 'master' : 'media';
            $this->extractCodecs($body, $out);
        } else {
            $out['manifest_kind'] = 'no_m3u8';
        }

        if (preg_match('#/\d{6,}\.m3u8#i', $url) === 1) {
            $out['dynamic_path_hint'] = true;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $out
     */
    private function extractCodecs(string $body, array &$out): void
    {
        if (preg_match_all('#CODECS="([^"]+)"#i', $body, $m)) {
            foreach ($m[1] as $codecStr) {
                foreach (explode(',', $codecStr) as $part) {
                    $part = trim($part);
                    if ($part === '') {
                        continue;
                    }
                    if (str_starts_with(strtolower($part), 'mp4a') || str_starts_with(strtolower($part), 'opus')) {
                        $out['audio_codecs'][] = $part;
                    } elseif (str_contains(strtolower($part), 'avc') || str_contains(strtolower($part), 'hvc') || str_contains(strtolower($part), 'hev')) {
                        $out['video_codecs'][] = $part;
                    }
                }
            }
        }

        $blob = strtolower($body);
        foreach (['ac-3', 'ec-3', 'dts', 'mp4a.40.5'] as $ac) {
            if (str_contains($blob, $ac)) {
                $out['ac3_warning'] = true;
                break;
            }
        }
    }
}
