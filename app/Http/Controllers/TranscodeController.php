<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesPlaybackToken;
use App\Models\Content;
use App\Services\FfmpegTranscodeService;
use App\Services\LocalMediaService;
use App\Services\RemoteHlsBrowserProxy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TranscodeController extends Controller
{
    use ValidatesPlaybackToken;

    public function __construct(
        private LocalMediaService $localMedia,
        private FfmpegTranscodeService $ffmpeg,
        private RemoteHlsBrowserProxy $hlsProxy,
    ) {}

    public function __invoke(Request $request, Content $content, string $token): RedirectResponse|StreamedResponse
    {
        $this->validatePlaybackToken($request, $content, $token);

        if ($this->localMedia->isLocalStream($content->stream_url)) {
            abort(400, 'La transcodificación solo aplica a streams remotos.');
        }

        if (! $this->ffmpeg->isAvailable()) {
            if ($this->hlsProxy->appliesTo($content)) {
                return redirect()->route('play.stream', ['content' => $content, 'token' => $token]);
            }

            return redirect()->away($content->stream_url);
        }

        $upstream = (string) $content->stream_url;

        return $this->ffmpeg->transcodeStreamResponse($upstream);
    }
}
