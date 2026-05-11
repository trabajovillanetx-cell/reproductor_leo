<?php



namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesPlaybackToken;
use App\Models\Content;
use App\Services\LocalMediaService;
use App\Services\RemoteHlsBrowserProxy;
use Illuminate\Http\RedirectResponse;

use Illuminate\Http\Request;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

use Symfony\Component\HttpFoundation\Response;



class PlayStreamController extends Controller
{
    use ValidatesPlaybackToken;

    public function __construct(
        private LocalMediaService $localMedia,
        private RemoteHlsBrowserProxy $hlsProxy,
    ) {}


    public function __invoke(Request $request, Content $content, string $token): RedirectResponse|Response|BinaryFileResponse

    {

        $this->validatePlaybackToken($request, $content, $token);
        if ($this->localMedia->isLocalStream($content->stream_url)) {

            $path = $this->localMedia->absolutePathFromStreamUrl($content->stream_url);

            if ($path === null || ! $this->localMedia->isAllowedReadableFile($path)) {

                abort(403, 'Archivo local no disponible.');

            }



            $mime = @mime_content_type($path) ?: 'application/octet-stream';



            return response()->file($path, [

                'Content-Type' => $mime,

                'Accept-Ranges' => 'bytes',

                'Cache-Control' => 'private, no-store',

            ], 'inline');

        }



        if ($this->hlsProxy->appliesTo($content)) {

            $packedTarget = $request->query('t');

            if (is_string($packedTarget) && trim($packedTarget) !== '') {

                return $this->hlsProxy->relayPackedTarget(trim($packedTarget), $content, $token);

            }



            return $this->hlsProxy->entryManifest($content, $token);

        }



        return redirect()->away($content->stream_url);

    }

}

