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

        $audioCodec = \Illuminate\Support\Facades\Cache::get('audio_codec_' . $content->id, '');
        $needsAudioTranscode = in_array(strtolower($audioCodec), ['ac3', 'eac3', 'dts', 'truehd', 'mlp']);

        if ($this->localMedia->isLocalStream($content->stream_url) && !$needsAudioTranscode) {
            abort(400, 'La transcodificacion solo aplica a streams remotos.');
        }

        if (! $this->ffmpeg->isAvailable()) {
            if ($this->hlsProxy->appliesTo($content)) {
                return redirect()->route('play.stream', ['content' => $content, 'token' => $token]);
            }
            return redirect()->away($content->stream_url);
        }

        // VOD local con audio incompatible: pipe MP4 directo
        if ($needsAudioTranscode && $this->localMedia->isLocalStream($content->stream_url)) {
            $path = $this->localMedia->absolutePathFromStreamUrl((string) $content->stream_url);
            if ($path) {
                return $this->ffmpeg->transcodeLocalFileResponse($path);
            }
        }

        // Live: HLS
        $channelKey = 'ch_' . $content->id;
        $hlsUrl = $this->ffmpeg->getHlsManifestUrl((string) $content->stream_url, $channelKey);
        return redirect()->away($hlsUrl);
    }
}
