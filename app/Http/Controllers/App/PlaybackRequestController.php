<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Content;
use App\Services\PlaybackTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlaybackRequestController extends Controller
{
    public function __construct(
        private PlaybackTokenService $tokens
    ) {}

    public function __invoke(Request $request, Content $content): RedirectResponse
    {
        $this->authorize('viewForPlayback', $content);

        $user = $request->user();
        $profileId = $request->session()->get('streaming_profile_id');
        $profileId = is_numeric($profileId) ? (int) $profileId : null;

        // Los canales en vivo necesitan token largo: los segmentos TS se siguen pidiendo horas.
        $ttlOverride = $content->type === \App\Enums\ContentType::Live
            ? (int) config('streaming.playback_token_ttl_live_minutes', 480)
            : null;
        $token = $this->tokens->create($user, $content, $profileId, $ttlOverride);

        AccessLog::query()->create([
            'user_id' => $user->id,
            'customer_profile_id' => $profileId,
            'content_id' => $content->id,
            'action' => 'playback_request',
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('player.show', [
            'content' => $content->id,
            'token' => $token->token,
        ]);
    }
}
