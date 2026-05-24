<?php
namespace App\Http\Controllers;

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\PlaybackToken;
use App\Services\FfmpegTranscodeService;
use App\Services\LocalMediaService;
use App\Services\StreamMimeResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function __construct(
        private StreamMimeResolver $mimeResolver,
        private LocalMediaService $localMedia,
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

        abort_if(! $record, 403, 'Token invalido o expirado.');
        abort_unless($content->is_active, 403);

        if ($record->customer_profile_id !== null) {
            $sessionPid = $request->session()->get('streaming_profile_id');
            if (is_numeric($sessionPid) && (int) $record->customer_profile_id !== (int) $sessionPid) {
                abort(403, 'Este reproductor corresponde a otro espacio.');
            }
        }

        $this->authorize('viewForPlayback', $content);

        $ffmpeg   = app(FfmpegTranscodeService::class);
        $ffmpegOk = $ffmpeg->isAvailable();
        $isLive   = $content->type === ContentType::Live;
        $mime     = $this->mimeResolver->videoMime($content);
        $isHls    = $mime === 'application/x-mpegURL';
        $src      = route('play.stream', ['content' => $content->id, 'token' => $token]);
        $transcodeSrc = route('play.transcode', ['content' => $content->id, 'token' => $token]);

        // Detectar audio incompatible en archivos locales (AC3, DTS, EAC3)
        // Resultado cacheado 30 dias por content_id para no llamar ffprobe en cada reproduccion
        $needsTranscode = false;
        if ($ffmpegOk && !$isLive && $this->localMedia->isLocalStream((string) $content->stream_url)) {
            $cacheKey = 'audio_codec_' . $content->id;
            $audioCodec = \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400 * 30, function() use ($content) {
                $path = $this->localMedia->absolutePathFromStreamUrl((string) $content->stream_url);
                if (!$path) return 'unknown';
                return trim((string) shell_exec(
                    "ffprobe -v quiet -select_streams a:0 -show_entries stream=codec_name -of default=noprint_wrappers=1:nokey=1 " .
                    escapeshellarg($path) . " 2>/dev/null"
                ));
            });
            if (in_array(strtolower($audioCodec), ['ac3', 'eac3', 'dts', 'truehd', 'mlp'])) {
                $needsTranscode = true;
            }
        }

        if ($needsTranscode) {
            $transcodeSrc = route('play.transcode', ['content' => $content->id, 'token' => $token]);
            $isHls = false;
            $mime = 'video/mp4';
        }

        if ($ffmpegOk && $isLive) {
            // Si el canal ya es HLS nativo, reproducir directo sin transcodear
            $streamUrl = (string) $content->stream_url;
            $isNativeHls = str_ends_with(strtolower(parse_url($streamUrl, PHP_URL_PATH) ?? ''), '.m3u8')
                || str_contains(strtolower($streamUrl), '.m3u8');

            if (!$isNativeHls) {
                $channelKey = 'ch_' . $content->id;
                try {
                    $hlsPath      = $ffmpeg->getHlsManifestUrl($streamUrl, $channelKey);
                    $transcodeSrc = url($hlsPath);
                    $isHls        = true;
                    $mime         = 'application/x-mpegURL';
                } catch (\Throwable $e) {
                    // fallback
                }
            }
        }

        return view('player.show', [
            'needsAudioTranscode'      => $needsTranscode,
            'content'                  => $content,
            'token'                    => $token,
            'src'                      => $src,
            'manifestHref'             => $src,
            'transcodeSrc'             => $transcodeSrc,
            'ffmpegTranscodeAvailable' => $ffmpegOk,
            'isHls'                    => $isHls,
            'sourceMime'               => $mime,
            'isLivePlayback'           => $isLive,
        ]);
    }
}
