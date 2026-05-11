<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CullDeadStreamsJob;
use App\Models\Category;
use App\Models\Content;
use App\Services\LocalMediaService;
use App\Services\M3uImportService;
use App\Services\RemoteUnreachableStreamsCuller;
use App\Services\StreamUrlValidator;
use App\Support\ManagedContentPosterFiles;
use App\Support\PhpUploadLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class M3uImportController extends Controller
{
    /** Listas de canales / texto */
    private const PLAYLIST_EXTENSIONS = ['m3u', 'm3u8', 'txt'];

    /** Tope de lista M3U subida por HTTP (texto); el cuello de botella real sigue siendo post_max_size de PHP. */
    private const MAX_PLAYLIST_UPLOAD_BYTES = 50 * 1024 * 1024;

    /** Tope de vídeo por esta pantalla (películas enormes: usar RaiDrive o subir por otra vía). */
    private const MAX_VIDEO_UPLOAD_BYTES = 400 * 1024 * 1024;

    public function __construct(
        private M3uImportService $importer,
        private LocalMediaService $localMedia,
        private StreamUrlValidator $streamUrls,
        private RemoteUnreachableStreamsCuller $streamsCuller,
    ) {}

    public function create(): View
    {
        $this->authorize('create', \App\Models\Content::class);

        return view('admin.m3u.import', [
            'categoryOptions' => Category::orderedTreeOptions(onlyActive: true),
            'videoExtensions' => LocalMediaService::videoExtensions(),
            'probeStreamsDefault' => (bool) config('m3u.probe_streams_default', true),
            'phpUploadMax' => ini_get('upload_max_filesize'),
            'phpPostMax' => ini_get('post_max_size'),
            'phpEffectiveHuman' => PhpUploadLimits::humanBytes(PhpUploadLimits::effectiveMaxBytes()),
            'phpEffectiveLow' => PhpUploadLimits::effectiveMaxBytes() < 40 * 1024 * 1024,
        ]);
    }

    public function manage(): View
    {
        $this->authorize('create', Content::class);

        $counts = [];
        foreach (['vod', 'live', 'series'] as $t) {
            $counts[$t] = Content::query()
                ->whereRemoteStreamUrl()
                ->where('type', $t)
                ->count();
        }

        return view('admin.m3u.manage', [
            'remoteTotal' => array_sum($counts),
            'remoteByType' => $counts,
            'recentRemote' => Content::query()
                ->with('category')
                ->whereRemoteStreamUrl()
                ->orderByDesc('id')
                ->limit(25)
                ->get(['id', 'title', 'type', 'stream_url', 'category_id']),
            'categoryOptions' => Category::orderedTreeOptions(onlyActive: false),
        ]);
    }

    public function purgeRemote(Request $request): RedirectResponse
    {
        $this->authorize('create', Content::class);

        $data = $request->validate([
            'scope' => ['required', Rule::in(['all', 'category'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id', 'required_if:scope,category'],
            'purge_confirm' => ['required', 'string', 'max:32'],
        ]);

        $typed = mb_strtoupper(trim($data['purge_confirm']));
        if ($typed !== 'BORRAR') {
            return back()->withErrors(['purge_confirm' => 'Debés escribir exactamente BORRAR (mayúsculas) para confirmar.']);
        }

        $q = Content::query()->whereRemoteStreamUrl();

        if ($data['scope'] === 'category') {
            $category = Category::query()->findOrFail((int) $data['category_id']);
            $q->whereIn('category_id', $category->descendantIdsIncludingSelf());
        }

        $deleted = 0;

        DB::transaction(function () use (&$deleted, $q): void {
            $deleted = $q->delete();
        });

        $msg = $deleted === 1
            ? 'Se eliminó 1 elemento con URL remota.'
            : "Se eliminaron {$deleted} elementos con URL remota.";

        return redirect()->route('admin.m3u.manage')->with('success', $msg);
    }

    /**
     * Quita del catálogo solo las filas http(s) que ya no respondan (misma lógica que la comprobación al importar).
     */
    public function cullDeadRemotes(Request $request): RedirectResponse
    {
        $this->authorize('create', Content::class);

        $data = $request->validate([
            'cull_scope' => ['required', Rule::in(['all', 'category'])],
            'cull_category_id' => ['nullable', 'integer', 'exists:categories,id', 'required_if:cull_scope,category'],
            'cull_confirm' => ['required', 'string', 'max:20'],
        ]);

        if (mb_strtoupper(trim((string) $data['cull_confirm'])) !== 'PODAR') {
            return back()->withErrors(['cull_confirm' => 'Debés escribir exactamente PODAR (mayúsculas) para confirmar el barrido.']);
        }

        set_time_limit(0);

        $categoryId = $data['cull_scope'] === 'category' ? (int) $data['cull_category_id'] : null;

        $report = $this->streamsCuller->cull($categoryId);

        $msg = match (true) {
            $report['removed'] === 0 => 'Barrido terminado: no había ítems con URL remota que fallaran la comprobación (o ya estaba limpio).',
            $report['removed'] === 1 => 'Barrido terminado: se eliminó 1 ítem cuya URL ya no respondía según el servidor.',
            default => 'Barrido terminado: se eliminaron '.$report['removed'].' ítems cuyas URLs no respondieron (canal caído / bloqueado / error).',
        };

        return redirect()->route('admin.m3u.manage')->with('success', $msg);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', \App\Models\Content::class);

        $data = $request->validate([
            'm3u' => ['nullable', 'string', 'max:5000000'],
            'm3u_file' => ['nullable', 'file'],
            'category_id' => ['required', 'exists:categories,id'],
            'split_by_group' => ['sometimes', 'boolean'],
            'probe_streams' => ['sometimes', Rule::in(['0', '1'])],
        ]);

        $probeStreams = $request->boolean('probe_streams', (bool) config('m3u.probe_streams_default', true));

        $category = Category::query()->findOrFail((int) $data['category_id']);

        if ($request->hasFile('m3u_file')) {
            /** @var UploadedFile $file */
            $file = $request->file('m3u_file');
            $ext = strtolower($file->getClientOriginalExtension());

            $playlist = self::PLAYLIST_EXTENSIONS;
            $videos = LocalMediaService::videoExtensions();
            $allowed = array_merge($playlist, $videos);

            if (! in_array($ext, $allowed, true)) {
                return back()
                    ->withErrors(['m3u_file' => 'Extensión no permitida. Usa listas .m3u / .m3u8 / .txt o vídeo: '.implode(', ', $videos).'.'])
                    ->withInput();
            }

            $size = (int) $file->getSize();
            $phpMax = PhpUploadLimits::effectiveMaxBytes();

            if (in_array($ext, $playlist, true)) {
                $maxPlaylist = min(self::MAX_PLAYLIST_UPLOAD_BYTES, $phpMax);
                if ($size > $maxPlaylist) {
                    return back()
                        ->withErrors(['m3u_file' => 'La lista supera '.PhpUploadLimits::humanBytes($maxPlaylist).' (límite de lista o de PHP). Divide la lista o sube post_max_size / upload_max_filesize.'])
                        ->withInput();
                }
            } elseif (in_array($ext, $videos, true)) {
                $maxVideo = min(self::MAX_VIDEO_UPLOAD_BYTES, $phpMax);
                if ($size > $maxVideo) {
                    return back()
                        ->withErrors([
                            'm3u_file' => 'Este vídeo pesa '.PhpUploadLimits::humanBytes($size).'. Por esta pantalla solo se admiten subidas de hasta '.PhpUploadLimits::humanBytes($maxVideo).' (y nunca más que permita PHP). Para películas de varios GB: copia el archivo a la carpeta de RaiDrive (o al disco del servidor) y usa la opción Biblioteca local (RaiDrive) para registrarlo sin pasar el archivo por el navegador; o bien aloja el .mp4 en un servidor/CDN y pon la URL https en una lista M3U.',
                        ])
                        ->withInput();
                }

                return $this->importSingleVideoFile($file, $category);
            }

            $path = $file->getRealPath();
            if ($path === false) {
                return back()
                    ->withErrors(['m3u_file' => 'No se pudo acceder al archivo temporal subido.'])
                    ->withInput();
            }

            if ($probeStreams) {
                set_time_limit(0);
            }

            $result = $this->importer->importFromPath(
                $path,
                $category,
                $request->boolean('split_by_group'),
                $probeStreams
            );

            $message = $this->m3uImportSummaryMessage($result);

            return redirect()
                ->route('admin.contents.index')
                ->with($result['created'] > 0 ? 'success' : 'warning', $message.' Puedes revisar el desglose en el Dashboard.');
        }

        $m3uText = trim((string) ($data['m3u'] ?? ''));
        $m3uText = $m3uText === '' ? '' : trim($this->normalizeEncoding($m3uText));

        if ($m3uText === '') {
            return back()
                ->withErrors(['m3u' => 'Pega una lista M3U, sube una lista (.m3u / .m3u8 / .txt) o un archivo de vídeo compatible.'])
                ->withInput();
        }

        if ($probeStreams) {
            set_time_limit(0);
        }

        $result = $this->importer->import(
            $m3uText,
            $category,
            $request->boolean('split_by_group'),
            $probeStreams
        );

        $message = $this->m3uImportSummaryMessage($result);

        return redirect()
            ->route('admin.contents.index')
            ->with($result['created'] > 0 ? 'success' : 'warning', $message.' Puedes revisar el desglose en el Dashboard.');
    }

    /** @param  array{created:int, skipped:int, rejected_unreachable?:int, errors:list<string>}  $result */
    private function m3uImportSummaryMessage(array $result): string
    {
        $message = 'Importación finalizada: '.$result['created'].' creados, '.$result['skipped'].' omitidos.';

        $reject = (int) ($result['rejected_unreachable'] ?? 0);
        if ($reject === 1) {
            $message .= ' 1 entrada descartada porque la URL no respondió (posible caído o bloqueado desde el servidor).';
        } elseif ($reject > 1) {
            $message .= ' '.$reject.' entradas descartadas porque las URLs no respondieron (posibles caídas o bloqueadas desde el servidor).';
        }

        if ($result['errors'] !== []) {
            $message .= ' Errores: '.count($result['errors']).'.';
        }

        return $message;
    }

    private function importSingleVideoFile(UploadedFile $file, Category $category): RedirectResponse
    {
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $baseName = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', (string) $baseName);
        $baseName = trim((string) $baseName, '._- ') ?: 'video';
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === '' || ! in_array($ext, LocalMediaService::videoExtensions(), true)) {
            $ext = 'mp4';
        }
        $unique = Str::uuid()->toString().'_'.$baseName.'.'.$ext;

        $stored = $file->storeAs(LocalMediaService::UPLOADS_PUBLIC_SUBDIR, $unique, 'public');
        $absolute = storage_path('app/public/'.$stored);
        $real = realpath($absolute);

        if ($real === false || ! is_file($real)) {
            return back()
                ->withErrors(['m3u_file' => 'No se pudo guardar el vídeo en el servidor.'])
                ->withInput();
        }

        $streamUrl = $this->localMedia->streamUrlForPath($real);

        try {
            $this->streamUrls->assertValid($streamUrl);
        } catch (\Illuminate\Validation\ValidationException $e) {
            @unlink($real);

            return back()->withErrors($e->errors())->withInput();
        }

        $title = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        if ($title === '') {
            $title = 'Vídeo importado';
        }

        try {
            Content::query()->create([
                'category_id' => $category->id,
                'title' => $title,
                'description' => null,
                'type' => $category->type->value,
                'stream_url' => $streamUrl,
                'poster_url' => null,
                'duration' => null,
                'is_active' => true,
            ]);
        } catch (\Throwable $e) {
            @unlink($real);

            return back()
                ->withErrors(['m3u_file' => 'No se pudo registrar el contenido: '.$e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('admin.contents.index')
            ->with('success', 'Vídeo guardado en storage/app/public/'.LocalMediaService::UPLOADS_PUBLIC_SUBDIR.' y registrado. Si no reproduce: php artisan storage:link');
    }

    private function normalizeEncoding(string $raw): string
    {
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $detected = mb_detect_encoding($raw, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($detected !== false && $detected !== 'UTF-8') {
                $raw = mb_convert_encoding($raw, 'UTF-8', $detected);
            }
        }

        return $raw;
    }

    /**
     * Lanza el barrido de canales muertos en background (queue/sync) sin bloquear el navegador.
     */
    public function cullDeadRemotesAsync(Request $request): JsonResponse
    {
        $this->authorize('create', Content::class);

        $this->releaseStaleCullLockIfNeeded();

        // Evitar lanzar dos barridos simultáneos
        $current = Cache::get(CullDeadStreamsJob::STATUS_CACHE_KEY);
        if (is_array($current) && ($current['running'] ?? false)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ya hay un barrido en curso. Espera a que termine.',
                'status'  => $current,
            ], 409);
        }

        $data = $request->validate([
            'cull_scope'       => ['required', Rule::in(['all', 'category', 'type'])],
            'cull_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'cull_type'        => ['nullable', Rule::in(['vod', 'live', 'series'])],
            'dry_run'          => ['sometimes', 'boolean'],
        ]);

        if ($data['cull_scope'] === 'category' && empty($data['cull_category_id'])) {
            return response()->json([
                'ok'      => false,
                'message' => 'Elegí una categoría o cambiá el ámbito a «todos» o «por tipo».',
            ], 422);
        }

        if ($data['cull_scope'] === 'type' && empty($data['cull_type'])) {
            return response()->json([
                'ok'      => false,
                'message' => 'Elegí un tipo (VOD, TV en vivo o series).',
            ], 422);
        }

        $categoryId = ($data['cull_scope'] === 'category' && ! empty($data['cull_category_id']))
            ? (int) $data['cull_category_id']
            : null;

        $contentType = ($data['cull_scope'] === 'type' && ! empty($data['cull_type']))
            ? (string) $data['cull_type']
            : null;

        $dryRun = $request->boolean('dry_run');

        CullDeadStreamsJob::dispatch($categoryId, false, $dryRun, $contentType);

        return response()->json([
            'ok'      => true,
            'message' => 'Barrido iniciado en segundo plano. Puedes consultar el estado sin recargar la página.',
        ]);
    }

    /**
     * Devuelve el estado actual del barrido (polling desde el navegador).
     */
    public function cullStatus(Request $request): JsonResponse
    {
        $this->authorize('create', Content::class);

        $this->releaseStaleCullLockIfNeeded();

        $status = Cache::get(CullDeadStreamsJob::STATUS_CACHE_KEY);

        if ($status === null) {
            return response()->json(['ok' => true, 'status' => null, 'message' => 'Sin barrido reciente.']);
        }

        return response()->json(['ok' => true, 'status' => $status]);
    }

    /**
     * Escaneo síncrono de URLs remotas: devuelve JSON con totales, muestra de OK y lista de caídos (sin cola de trabajos).
     */
    public function scanRemoteChannelsSync(Request $request): JsonResponse
    {
        $this->authorize('create', Content::class);

        $data = $request->validate([
            'scan_scope' => ['required', Rule::in(['all', 'type'])],
            'scan_type'  => ['nullable', Rule::in(['vod', 'live', 'series'])],
        ]);

        if ($data['scan_scope'] === 'type' && empty($data['scan_type'])) {
            return response()->json([
                'ok'      => false,
                'message' => 'Elegí un tipo (VOD, TV en vivo o series).',
            ], 422);
        }

        $categoryId = null;
        $type = ($data['scan_scope'] === 'type' && ! empty($data['scan_type']))
            ? (string) $data['scan_type']
            : null;

        $report = $this->streamsCuller->scanCatalog($categoryId, $type);

        return response()->json(['ok' => true, 'report' => $report]);
    }

    /**
     * Elimina por IDs filas http(s) remotas (misma condición que el escaneo). Pensado para borrar lo marcado en la tabla.
     */
    public function deleteScannedUnreachableIds(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Content::class);

        $maxIds = (int) config('m3u.scan_max_dead_listed');

        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:'.$maxIds],
            'ids.*' => ['integer', 'exists:contents,id'],
        ]);

        $deleted = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, &$deleted, &$skipped): void {
            foreach ($data['ids'] as $id) {
                $content = Content::query()->find((int) $id);
                if ($content === null) {
                    continue;
                }
                if (! self::contentRowIsRemoteUrl($content)) {
                    $skipped++;

                    continue;
                }
                $this->authorize('delete', $content);
                ManagedContentPosterFiles::deleteIfManaged($content->poster_url);
                $content->delete();
                $deleted++;
            }
        });

        return response()->json([
            'ok'                   => true,
            'deleted'              => $deleted,
            'skipped_not_remote'   => $skipped,
            'message'              => $deleted === 0
                ? 'No se eliminó ningún ítem (permisos o ya no son URLs http(s)).'
                : ($deleted === 1 ? 'Se eliminó 1 ítem.' : "Se eliminaron {$deleted} ítems."),
        ]);
    }

    private static function contentRowIsRemoteUrl(Content $content): bool
    {
        $u = trim((string) ($content->stream_url ?? ''));

        return str_starts_with($u, 'http://') || str_starts_with($u, 'https://');
    }

    /**
     * Si el job murió sin limpiar caché, `running` puede quedar en true para siempre y bloquear la UI.
     */
    private function releaseStaleCullLockIfNeeded(): void
    {
        $current = Cache::get(CullDeadStreamsJob::STATUS_CACHE_KEY);
        if (! is_array($current) || ! ($current['running'] ?? false)) {
            return;
        }
        $started = $current['started_at'] ?? null;
        if (! is_string($started) || $started === '') {
            return;
        }
        try {
            if (Carbon::parse($started)->addHours(3)->isPast()) {
                Cache::forget(CullDeadStreamsJob::STATUS_CACHE_KEY);
            }
        } catch (\Throwable) {
            Cache::forget(CullDeadStreamsJob::STATUS_CACHE_KEY);
        }
    }
}
