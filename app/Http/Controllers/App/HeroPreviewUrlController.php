<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Services\PlaybackTokenService;
use App\Services\StreamMimeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Devuelve URL temporal de stream para vignette del carrusel inicio (mismo esquema /play/{id}/{token}).
 */
final class HeroPreviewUrlController extends Controller
{
    public function __invoke(
        Request $request,
        Content $content,
        PlaybackTokenService $tokens,
        StreamMimeResolver $mime,
    ): JsonResponse {
        $this->authorize('viewForPlayback', $content);

        if (! $mime->supportsHeroPreview($content)) {
            return response()->json(['ok' => false, 'reason' => 'unsupported'], 422);
        }

        $user = $request->user();
        $profileRaw = $request->session()->get('streaming_profile_id');
        $profileId = is_numeric($profileRaw) ? (int) $profileRaw : null;

        $ttl = max(2, min(15, (int) config('streaming.hero_preview_token_ttl_minutes', 4)));
        $token = $tokens->create($user, $content, $profileId, $ttl);

        $streamMime = $mime->videoMime($content);
        $isHls = $streamMime === 'application/x-mpegURL';

        return response()->json([
            'ok' => true,
            'stream_url' => route('play.stream', ['content' => $content->id, 'token' => $token->token]),
            'is_hls' => $isHls,
            'mime' => $streamMime,
            'clip_seconds' => max(20, min(120, (int) config('streaming.hero_preview_clip_seconds', 40))),
        ]);
    }
}
