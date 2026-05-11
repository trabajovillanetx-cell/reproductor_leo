<?php

namespace App\Http\Controllers;

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\PlaybackToken;
use App\Services\FfmpegTranscodeService;
use App\Services\StreamMimeResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function __construct(
        private StreamMimeResolver $mimeResolver,
    ) {}

    public function show(Request $request, Content $content, string $token): View
    {
        $user = $request->user();

        $record = PlaybackToken::query()
            ->where('token', $token)
            ->where('content_id', $content->id)
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->first();

        abort_if(! $record, 403, 'Token inválido o expirado.');
        abort_unless($content->is_active, 403);

        if ($record->customer_profile_id !== null) {
            $sessionPid = $request->session()->get('streaming_profile_id');
            if (is_numeric($sessionPid) && (int) $record->customer_profile_id !== (int) $sessionPid) {
                abort(403, 'Este reproductor corresponde a otro espacio.');
            }
        }

        $this->authorize('viewForPlayback', $content);

        $src = route('play.stream', ['content' => $content->id, 'token' => $token]);

        $mime = $this->mimeResolver->videoMime($content);
        $isHls = $mime === 'application/x-mpegURL';

        $ffmpeg = app(FfmpegTranscodeService::class);

        return view('player.show', [
            'content' => $content,
            'token' => $token,
            'src' => $src,
            'manifestHref' => route('play.stream', ['content' => $content->id, 'token' => $token]),
            'transcodeSrc' => route('play.transcode', ['content' => $content->id, 'token' => $token]),
            'ffmpegTranscodeAvailable' => $ffmpeg->isAvailable(),
            'isHls' => $isHls,
            'sourceMime' => $mime,
            'isLivePlayback' => $content->type === ContentType::Live,
        ]);
    }

}
