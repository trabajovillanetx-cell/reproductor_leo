<?php

namespace App\Http\Controllers;

use App\Enums\ContentType;
use App\Enums\UserStatus;
use App\Models\Content;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class IptvController extends Controller
{
    private function authenticate(string $username, string $password): ?User
    {
        $user = User::where(function($q) use ($username) {
                $q->where('username', $username)
                  ->orWhere('email', $username);
            })->where('role', 'customer')->first();

        if (!$user) return null;
        if ($user->status !== UserStatus::Active) return null;
        if ($user->provider_password !== $password) return null;

        return $user;
    }

    private function getContents(User $user)
    {
        return Content::query()
            ->where('is_active', true)
            ->whereHas('category', fn($q) => $q->where('is_active', true))
            ->with('category')
            ->orderBy('type')
            ->orderBy('title')
            ->get();
    }

    public function playlist(Request $request, string $username, string $password): Response
    {
        $user = $this->authenticate($username, $password);

        if (!$user) {
            return response('Unauthorized', 401);
        }

        $contents = $this->getContents($user);
        $baseUrl = config('app.url');

        $m3u = "#EXTM3U\n";

        foreach ($contents as $content) {
            $typeLabel = match($content->type) {
                ContentType::Live => 'live',
                ContentType::Vod => 'movie',
                ContentType::Series => 'series',
            };

            $logo = $content->poster_url ?? '';
            $group = $content->category->name ?? 'General';
            $streamUrl = $this->resolveStreamUrl($content, $baseUrl);

            $m3u .= "#EXTINF:-1";
            $m3u .= " tvg-id=\"{$content->id}\"";
            $m3u .= " tvg-name=\"{$content->title}\"";
            $m3u .= " tvg-logo=\"{$logo}\"";
            $m3u .= " group-title=\"{$group}\"";
            $m3u .= ",{$content->title}\n";
            $m3u .= "{$streamUrl}\n";
        }

        return response($m3u, 200, [
            'Content-Type' => 'application/x-mpegURL',
            'Content-Disposition' => 'attachment; filename="playlist.m3u"',
        ]);
    }

    public function playerApi(Request $request, string $username, string $password): \Symfony\Component\HttpFoundation\Response
    {
        $user = $this->authenticate($username, $password);
        $action = $request->query('action', '');
        $baseUrl = config('app.url');

        if (!$user) {
            return response()->json([
                'user_info' => ['auth' => 0],
            ]);
        }

        $userInfo = [
            'auth' => 1,
            'status' => 'Active',
            'username' => $username,
            'password' => $password,
            'message' => 'Welcome',
            'exp_date' => $user->expires_at ? (string) strtotime($user->expires_at) : null,
            'is_trial' => '0',
            'active_cons' => '0',
            'created_at' => (string) strtotime($user->created_at),
            'max_connections' => '1',
            'allowed_output_formats' => ['m3u8', 'ts', 'rtmp'],
        ];

        $serverInfo = [
            'url' => parse_url($baseUrl, PHP_URL_HOST),
            'port' => '80',
            'https_port' => '443',
            'server_protocol' => 'https',
            'rtmp_port' => '1935',
            'timezone' => config('app.timezone'),
            'timestamp_now' => time(),
            'time_now' => now()->format('Y-m-d H:i:s'),
        ];

        // Sin action: devolver info del usuario (login check de IPTV Smarters)
        if ($action === '') {
            return response()->json([
                'user_info' => $userInfo,
                'server_info' => $serverInfo,
            ]);
        }

        switch ($action) {
            case 'get_live_categories':
                $cats = \App\Models\Category::where('is_active', true)
                    ->where('type', 'live')
                    ->get(['id', 'name']);
                return response()->json($cats->map(fn($c) => [
                    'category_id' => (string) $c->id,
                    'category_name' => $c->name,
                    'parent_id' => 0,
                ]));

            case 'get_vod_categories':
                $cats = \App\Models\Category::where('is_active', true)
                    ->where('type', 'vod')
                    ->get(['id', 'name']);
                return response()->json($cats->map(fn($c) => [
                    'category_id' => (string) $c->id,
                    'category_name' => $c->name,
                    'parent_id' => 0,
                ]));

            case 'get_live_streams':
                $contents = Content::where('is_active', true)
                    ->where('type', 'live')
                    ->whereHas('category', fn($q) => $q->where('is_active', true))
                    ->with('category')
                    ->get();
                return response()->json($contents->map(fn($c) => [
                    'num' => $c->id,
                    'name' => $c->title,
                    'stream_type' => 'live',
                    'stream_id' => $c->id,
                    'stream_icon' => $c->poster_url ?? '',
                    'epg_channel_id' => '',
                    'added' => strtotime($c->created_at),
                    'category_id' => (string) ($c->category_id ?? 0),
                    'custom_sid' => '',
                    'tv_archive' => 0,
                    'direct_source' => $c->stream_url ?? '',
                    'tv_archive_duration' => 0,
                ]));

            case 'get_vod_streams':
                $contents = Content::where('is_active', true)
                    ->where('type', 'vod')
                    ->whereHas('category', fn($q) => $q->where('is_active', true))
                    ->with('category')
                    ->get();
                return response()->json($contents->map(fn($c) => [
                    'num' => $c->id,
                    'name' => $c->title,
                    'stream_type' => 'movie',
                    'stream_id' => $c->id,
                    'stream_icon' => $c->poster_url ?? '',
                    'added' => strtotime($c->created_at),
                    'category_id' => (string) ($c->category_id ?? 0),
                    'container_extension' => 'mp4',
                    'direct_source' => '',
                ]));


            case 'get_series_categories':
                $cats = \App\Models\Category::where('is_active', true)
                    ->where('type', 'series')
                    ->get(['id', 'name']);
                return response()->json($cats->map(fn($c) => [
                    'category_id' => (string) $c->id,
                    'category_name' => $c->name,
                    'parent_id' => 0,
                ]));

            case 'get_series':
                $seriesData = \Illuminate\Support\Facades\Cache::remember('iptv_series_list', 1800, function () {
                    $episodes = \App\Models\Content::where('is_active', true)
                    ->where('type', 'series')
                    ->whereHas('category', fn($q) => $q->where('is_active', true))
                    ->with('category')
                    ->get();
                $seriesMap = [];
                foreach ($episodes as $ep) {
                    $path = str_replace('local:', '', $ep->stream_url ?? '');
                    $parts = explode('/', $path);
                    // El archivo es el último elemento, la carpeta padre es el penúltimo
                    $totalParts = count($parts);
                    $parentFolder = $totalParts >= 2 ? trim($parts[$totalParts - 2]) : $ep->title;
                    // Si la carpeta padre es Temporada/Season, subir un nivel más
                    if (preg_match('/^(Temporada|Season|temporada|season)\s*\d+/i', $parentFolder)) {
                        $serieName = $totalParts >= 3 ? trim($parts[$totalParts - 3]) : $ep->title;
                    } else {
                        $serieName = $parentFolder;
                    }
                    // Limpiar año entre paréntesis del nombre: "Attack on Titan (2013)" -> "Attack on Titan"
                    $serieName = trim(preg_replace('/\s*\(\d{4}\)\s*$/', '', $serieName));
                    $key = $ep->category_id . '_' . $serieName;
                    if (!isset($seriesMap[$key])) {
                        $seriesMap[$key] = [
                            'series_id' => crc32($key),
                            'name' => $serieName,
                            'cover' => $ep->poster_url ?? '',
                            'plot' => '',
                            'cast' => '',
                            'director' => '',
                            'genre' => $ep->category->name ?? '',
                            'release_date' => '',
                            'last_modified' => (string) strtotime($ep->updated_at),
                            'rating' => '0',
                            'rating_5based' => '0',
                            'backdrop_path' => [],
                            'youtube_trailer' => '',
                            'episode_run_time' => '45',
                            'category_id' => (int) $ep->category_id,
                        ];
                    }
                    if (empty($seriesMap[$key]['cover']) && $ep->poster_url) {
                        $seriesMap[$key]['cover'] = $ep->poster_url;
                    }
                }
                return array_values($seriesMap);
                });
                return response()->json($seriesData, 200, [], JSON_UNESCAPED_UNICODE);

            case 'get_series_info':
                $seriesIdParam = (int) $request->query('series_id', 0);
                $episodes = \App\Models\Content::where('is_active', true)->where('type', 'series')->get();
                $matching = [];
                foreach ($episodes as $ep) {
                    $path = str_replace('local:', '', $ep->stream_url ?? '');
                    $parts = explode('/', $path);
                    // El archivo es el último elemento, la carpeta padre es el penúltimo
                    $totalParts = count($parts);
                    $parentFolder = $totalParts >= 2 ? trim($parts[$totalParts - 2]) : $ep->title;
                    // Si la carpeta padre es Temporada/Season, subir un nivel más
                    if (preg_match('/^(Temporada|Season|temporada|season)\s*\d+/i', $parentFolder)) {
                        $serieName = $totalParts >= 3 ? trim($parts[$totalParts - 3]) : $ep->title;
                    } else {
                        $serieName = $parentFolder;
                    }
                    // Limpiar año entre paréntesis del nombre: "Attack on Titan (2013)" -> "Attack on Titan"
                    $serieName = trim(preg_replace('/\s*\(\d{4}\)\s*$/', '', $serieName));
                    $key = $ep->category_id . '_' . $serieName;
                    if (crc32($key) === $seriesIdParam) { $matching[] = $ep; }
                }
                if (empty($matching)) {
                    return response()->json(['seasons' => [], 'episodes' => [], 'info' => []]);
                }
                $seasons = [];
                $episodesOut = [];
                foreach ($matching as $ep) {
                    $path = str_replace('local:', '', $ep->stream_url ?? '');
                    $parts = explode('/', $path);
                    $seasonNum = 1;
                    $episodeNum = 1;
                    foreach ($parts as $p) {
                        if (preg_match('/Season\s*(\d+)/i', $p, $m)) { $seasonNum = (int) $m[1]; }
                    }
                    $title = $ep->title;
                    if (preg_match('/[sS]\d+[eE](\d+)/', $title, $m)) { $episodeNum = (int) $m[1]; }
                    elseif (preg_match('/[Ee]pi\s*(\d+)/i', $title, $m)) { $episodeNum = (int) $m[1]; }
                    elseif (preg_match('/(\d+)/', $title, $m)) { $episodeNum = (int) $m[1]; }
                    $seasons[$seasonNum] = [
                        'season_number' => $seasonNum,
                        'episode_count' => ($seasons[$seasonNum]['episode_count'] ?? 0) + 1,
                        'name' => 'Temporada ' . $seasonNum,
                        'cover' => $ep->poster_url ?? '',
                        'overview' => '', 'air_date' => '',
                    ];
                    $episodesOut[$seasonNum][] = [
                        'id' => (string) $ep->id,
                        'episode_num' => $episodeNum,
                        'title' => $ep->title,
                        'container_extension' => 'mp4',
                        'info' => [
                            'season' => $seasonNum, 'episode' => $episodeNum,
                            'air_date' => '', 'plot' => '',
                            'duration_secs' => 0, 'duration' => '00:00:00',
                            'video' => [], 'audio' => [], 'bitrate' => 0, 'rating' => '0',
                        ],
                        'custom_sid' => '',
                        'added' => (string) strtotime($ep->created_at),
                        'season' => $seasonNum,
                        'direct_source' => '',
                    ];
                }
                $first = $matching[0];
                return response()->json([
                    'seasons' => array_values($seasons),
                    'info' => [
                        'name' => $first->title,
                        'cover' => $first->poster_url ?? '',
                        'plot' => '', 'cast' => '', 'director' => '', 'genre' => '',
                        'release_date' => '',
                        'last_modified' => (string) strtotime($first->updated_at),
                        'rating' => '0', 'rating_5based' => '0',
                        'backdrop_path' => [], 'youtube_trailer' => '',
                        'episode_run_time' => '45',
                        'category_id' => (string) $first->category_id,
                    ],
                    'episodes' => $episodesOut,
                ]);
            default:
                return response()->json([
                    'user_info' => $userInfo,
                    'server_info' => $serverInfo,
                ]);
        }
    }


    public function streamVod(Request $request, string $username, string $password, string $file): \Symfony\Component\HttpFoundation\Response
    {
        $user = $this->authenticate($username, $password);
        if (!$user) abort(401);
        $streamId = (int) explode('.', $file)[0];
        $content = Content::where('id', $streamId)->where('type', 'vod')->where('is_active', true)->firstOrFail();

        if (str_starts_with($content->stream_url, 'local:')) {
            $filePath = substr($content->stream_url, 6);
            if (!file_exists($filePath)) abort(404);

            $fileSize  = filesize($filePath);
            $mimeType  = 'video/mp4';
            $start     = 0;
            $end       = $fileSize - 1;
            $rangeHeader = $request->header('Range');

            if ($rangeHeader) {
                preg_match('/bytes=(\d*)-(\d*)/', $rangeHeader, $matches);
                $start = $matches[1] !== '' ? (int)$matches[1] : 0;
                $end   = $matches[2] !== '' ? (int)$matches[2] : $fileSize - 1;
                $end   = min($end, $fileSize - 1);
            }

            $length = $end - $start + 1;
            $status = $rangeHeader ? 206 : 200;

            $headers = [
                'Content-Type'   => $mimeType,
                'Content-Length' => $length,
                'Accept-Ranges'  => 'bytes',
                'Cache-Control'  => 'private, no-store',
            ];

            if ($rangeHeader) {
                $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
            }

            return response()->stream(function () use ($filePath, $start, $length) {
                $fp = fopen($filePath, 'rb');
                fseek($fp, $start);
                $remaining = $length;
                $chunkSize = 1024 * 256; // 256KB chunks
                while ($remaining > 0 && !feof($fp)) {
                    $read  = min($chunkSize, $remaining);
                    $chunk = fread($fp, $read);
                    if ($chunk === false) break;
                    echo $chunk;
                    flush();
                    $remaining -= strlen($chunk);
                }
                fclose($fp);
            }, $status, $headers);
        }

        return redirect()->away($content->stream_url);
    }

    public function streamLive(Request $request, string $username, string $password, string $file): \Symfony\Component\HttpFoundation\Response
    {
        $user = $this->authenticate($username, $password);
        if (!$user) abort(401);
        $streamId = (int) explode(".", $file)[0];
        $content = Content::where("id", $streamId)->where("type", "live")->where("is_active", true)->firstOrFail();
        return redirect()->away($content->stream_url);
    }

    public function streamSeries(Request $request, string $username, string $password, string $file): \Symfony\Component\HttpFoundation\Response
    {
        $user = $this->authenticate($username, $password);
        if (!$user) abort(401);
        $streamId = (int) explode('.', $file)[0];
        $content = Content::where('id', $streamId)->where('type', 'series')->where('is_active', true)->firstOrFail();

        if (str_starts_with($content->stream_url, 'local:')) {
            $path = substr($content->stream_url, 6);
            if (!file_exists($path)) abort(404);
            $mime = @mime_content_type($path) ?: 'video/mp4';
            return response()->file($path, [
                'Content-Type'  => $mime,
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, no-store',
            ], 'inline');
        }

        return redirect()->away($content->stream_url);
    }

    private function resolveStreamUrl(Content $content, string $baseUrl): string
    {
        if ($content->type === ContentType::Live && $content->stream_url && !str_starts_with($content->stream_url, 'local:')) {
            return $content->stream_url;
        }
        return $baseUrl . '/iptv/stream/' . $content->id;
    }
}
