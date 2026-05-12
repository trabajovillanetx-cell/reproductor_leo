<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\PlaybackToken;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ActiveSessionsController extends Controller
{
    /** Activo si hubo heartbeat en los últimos 2 minutos */
    public const ACTIVE_THRESHOLD_SECONDS = 120;

    public function index(): View
    {
        $this->authorize('viewAny', Content::class);

        return view('admin.sessions.index', [
            'stats' => $this->getStats(),
        ]);
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Content::class);

        $threshold = now()->subSeconds(self::ACTIVE_THRESHOLD_SECONDS);

        $sessions = PlaybackToken::query()
            ->with(['user', 'customerProfile', 'content'])
            ->where('expires_at', '>', now())
            ->where('last_seen_at', '>=', $threshold)
            ->where('playback_status', '!=', 'ended')
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(function (PlaybackToken $token) {
                $ua = $this->parseUserAgent($token->user_agent ?? '');

                return [
                    'id' => $token->id,
                    'user_name' => $token->user?->name ?? 'Desconocido',
                    'user_email' => $token->user?->email ?? '',
                    'profile_name' => $token->customerProfile?->name ?? 'Sin perfil',
                    'content_title' => $token->content?->title ?? 'Desconocido',
                    'content_type' => $token->content?->type->value ?? '',
                    'content_poster' => $token->content?->poster_url ?? '',
                    'ip_address' => $token->ip_address ?? 'Desconocida',
                    'device' => $ua['device'],
                    'browser' => $ua['browser'],
                    'os' => $ua['os'],
                    'status' => $token->playback_status ?? 'playing',
                    'last_seen' => $token->last_seen_at?->diffForHumans() ?? '',
                    'started_at' => $token->created_at?->format('H:i:s') ?? '',
                ];
            });

        return response()->json([
            'sessions' => $sessions,
            'total' => $sessions->count(),
            'stats' => $this->getStats(),
            'updated_at' => now()->format('H:i:s'),
        ]);
    }

    /**
     * @return array{total_active: int, watching_live: int, watching_vod: int}
     */
    private function getStats(): array
    {
        $threshold = now()->subSeconds(self::ACTIVE_THRESHOLD_SECONDS);

        $base = PlaybackToken::query()
            ->where('expires_at', '>', now())
            ->where('last_seen_at', '>=', $threshold)
            ->where('playback_status', '!=', 'ended');

        return [
            'total_active' => (clone $base)->count(),
            'watching_live' => (clone $base)
                ->join('contents', 'playback_tokens.content_id', '=', 'contents.id')
                ->where('contents.type', 'live')
                ->count(),
            'watching_vod' => (clone $base)
                ->join('contents', 'playback_tokens.content_id', '=', 'contents.id')
                ->whereIn('contents.type', ['vod', 'series'])
                ->count(),
        ];
    }

    /**
     * @return array{device: string, browser: string, os: string}
     */
    private function parseUserAgent(string $ua): array
    {
        $device = 'Desktop';
        if (preg_match('/Mobile|Android|iPhone|iPad/i', $ua)) {
            $device = preg_match('/iPad/i', $ua) ? 'Tablet' : 'Móvil';
        } elseif (preg_match('/Smart.?TV|WebOS|Tizen|VIDAA/i', $ua)) {
            $device = 'Smart TV';
        }

        $browser = 'Desconocido';
        if (preg_match('/Chrome\/(\d+)/i', $ua, $m)) {
            $browser = 'Chrome '.$m[1];
        } elseif (preg_match('/Firefox\/(\d+)/i', $ua, $m)) {
            $browser = 'Firefox '.$m[1];
        } elseif (preg_match('/Edg\/(\d+)/i', $ua, $m)) {
            $browser = 'Edge '.$m[1];
        } elseif (preg_match('/Safari\/(\d+)/i', $ua)) {
            $browser = 'Safari';
        }

        $os = 'Desconocido';
        if (preg_match('/Windows NT/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS X/i', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/Android (\d+)/i', $ua, $m)) {
            $os = 'Android '.$m[1];
        } elseif (preg_match('/iPhone OS (\d+)/i', $ua, $m)) {
            $os = 'iOS '.$m[1];
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
        }

        return compact('device', 'browser', 'os');
    }
}
