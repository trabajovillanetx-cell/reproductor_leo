<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Content;
use App\Services\LocalMediaService;
use App\Services\RaidriveRenameSyncService;
use App\Services\TmdbPosterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LocalLibraryController extends Controller
{
    public function __construct(
        private LocalMediaService $localMedia,
        private TmdbPosterService $tmdbPosters
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('create', Content::class);

        $path = $request->string('path')->trim();

        if (! $this->localMedia->isConfigured()) {
            return view('admin.library.raidrive', [
                'configured' => false,
                'path' => '',
                'parentPath' => null,
                'browse' => ['dirs' => [], 'files' => []],
                'categoryOptions' => Category::orderedTreeOptions(onlyActive: true),
                'diskVideoStats' => null,
                'dbLocalStreamsTotal' => Content::query()->where('stream_url', 'like', 'local:%')->count(),
                'dbImportedUnderRaidriveRoot' => null,
                'raidriveRootPath' => null,
                'raidriveRoots' => [],
                'raidriveMultiRoot' => false,
                'raidriveBrowseCacheTtl' => max(0, (int) config('media.raidrive_browse_cache_ttl', 120)),
                'raidriveDiskStatsCacheTtl' => max(0, (int) config('media.raidrive_disk_stats_cache_ttl', 300)),
                'raidriveIndexDiskStats' => false,
                'canImportRecursive' => false,
                'importRecursiveMax' => (int) config('media.raidrive_import_recursive_max', 2500),
                'localLibraryDriverOption' => $this->localMedia->localLibraryDriverOption(),
                'localLibraryRootsBackend' => $this->localMedia->localLibraryRootsBackend(),
            ]);
        }

        if (! $this->localMedia->isSafeRelativePath((string) $path)) {
            abort(400, 'Ruta no válida.');
        }

        $browse = $this->localMedia->browse((string) $path);
        $parentPath = $this->parentRelativePath((string) $path);
        $canImportRecursive = $this->localMedia->resolveDirectoryRealPathFromBrowsePath((string) $path) !== null;

        $diskVideoStats = (bool) config('media.raidrive_index_disk_stats', false)
            ? $this->localMedia->countVideoFilesUnderConfiguredRoot()
            : null;

        $roots = $this->localMedia->roots();
        $dbImportedUnderRaidriveRoot = $this->countLocalRowsUnderRaidriveRootsFast($roots);

        return view('admin.library.raidrive', [
            'configured' => true,
            'path' => str_replace(DIRECTORY_SEPARATOR, '/', (string) $path),
            'parentPath' => $parentPath,
            'browse' => $browse,
            'categoryOptions' => Category::orderedTreeOptions(onlyActive: true),
            'diskVideoStats' => $diskVideoStats,
            'dbLocalStreamsTotal' => Content::query()->where('stream_url', 'like', 'local:%')->count(),
            'dbImportedUnderRaidriveRoot' => $dbImportedUnderRaidriveRoot,
            'raidriveRootPath' => implode(' · ', $roots),
            'raidriveRoots' => $roots,
            'raidriveMultiRoot' => count($roots) > 1,
            'raidriveBrowseCacheTtl' => max(0, (int) config('media.raidrive_browse_cache_ttl', 120)),
            'raidriveDiskStatsCacheTtl' => max(0, (int) config('media.raidrive_disk_stats_cache_ttl', 300)),
            'raidriveIndexDiskStats' => (bool) config('media.raidrive_index_disk_stats', false),
            'canImportRecursive' => $canImportRecursive,
            'importRecursiveMax' => max(1, min(10000, (int) config('media.raidrive_import_recursive_max', 2500))),
            'localLibraryDriverOption' => $this->localMedia->localLibraryDriverOption(),
            'localLibraryRootsBackend' => $this->localMedia->localLibraryRootsBackend(),
        ]);
    }

    public function refreshCache(Request $request): RedirectResponse
    {
        $this->authorize('create', Content::class);

        if (! $this->localMedia->isConfigured()) {
            return redirect()->route('admin.library.raidrive');
        }

        $this->localMedia->bumpRaidriveCacheEpoch();

        $back = $request->string('return_path')->trim();
        if (! $this->localMedia->isSafeRelativePath((string) $back)) {
            $back = '';
        }

        return redirect()
            ->route('admin.library.raidrive', $back === '' ? [] : ['path' => $back])
            ->with('success', 'Caché de listados y recuentos actualizada; la próxima visita vuelve a leer el disco.');
    }

    /**
     * Tras renombrar vídeos en RaiDrive en la MISMA carpeta: actualiza stream_url/título cuando hay 1 roto + 1 archivo huérfano.
     */
    public function syncRenamedFiles(Request $request, RaidriveRenameSyncService $renameSync): RedirectResponse
    {
        $this->authorize('create', Content::class);

        if (! $this->localMedia->isConfigured()) {
            return redirect()->route('admin.library.raidrive');
        }

        $stats = $renameSync->sync();
        $this->localMedia->bumpRaidriveCacheEpoch();

        $back = $request->string('return_path')->trim();
        if (! $this->localMedia->isSafeRelativePath((string) $back)) {
            $back = '';
        }

        $parts = [];
        $parts[] = 'Sincronización de renombres: '.$stats['relinked'].' título(s) re-enlazado(s).';
        if ($stats['still_broken'] > 0) {
            $parts[] = 'Sin archivo en disco ni reemplazo claro en la carpeta (revisá título borrado o ruta): '.$stats['still_broken'].'.';
        }
        if ($stats['ambiguous'] > 0) {
            $parts[] = 'Carpetas con varios vídeos o varias filas sin coincidir 1↔1 (no se modificó solo): '.$stats['ambiguous'].'.';
        }
        if ($stats['parent_unreachable'] > 0) {
            $parts[] = 'Directorio padre ilegible o inexistente: '.$stats['parent_unreachable'].'.';
        }
        $parts[] = 'Tip: si configuraste TMDB, se intentó carátula nueva para los re-enlazados.';

        return redirect()
            ->route('admin.library.raidrive', $back === '' ? [] : ['path' => $back])
            ->with('success', implode(' ', $parts));
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', Content::class);
        $this->allowLongRunningImportRequest();

        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:50'],
            'files.*' => ['required', 'string', 'max:4096'],
            'category_id' => ['required', 'exists:categories,id'],
            'return_path' => ['nullable', 'string', 'max:2000'],
        ]);

        $category = Category::query()->findOrFail((int) $data['category_id']);
        $created = 0;

        $candidates = [];
        foreach ($data['files'] as $abs) {
            $abs = str_replace('/', DIRECTORY_SEPARATOR, $abs);
            if (! $this->localMedia->isAllowedReadableFile($abs)) {
                continue;
            }
            if ($this->localMedia->shouldSkipSupplementaryVideoPath($abs)) {
                continue;
            }
            $candidates[] = $abs;
        }

        $streamUrls = array_map(fn (string $p): string => $this->localMedia->streamUrlForPath($p), $candidates);
        $existingUrls = $streamUrls === [] ? [] : array_flip(Content::query()
            ->whereIn('stream_url', array_values(array_unique($streamUrls)))
            ->pluck('stream_url')
            ->all());

        foreach ($candidates as $abs) {
            $streamUrl = $this->localMedia->streamUrlForPath($abs);
            if (isset($existingUrls[$streamUrl])) {
                continue;
            }

            $title = pathinfo($abs, PATHINFO_FILENAME);
            if ($title === '') {
                $title = 'Sin título';
            }

            $content = Content::query()->create([
                'category_id' => $category->id,
                'title' => $title,
                'description' => null,
                'type' => $category->type->value,
                'stream_url' => $streamUrl,
                'poster_url' => null,
                'library_folder' => $this->localMedia->libraryFolderForAbsoluteFile($abs),
                'duration' => null,
                'is_active' => true,
            ]);
            $this->maybeEnrichPosterFromTmdb($content);
            $existingUrls[$streamUrl] = true;
            $created++;
        }

        $back = $request->string('return_path')->trim();
        if (! $this->localMedia->isSafeRelativePath((string) $back)) {
            $back = '';
        }

        $this->localMedia->bumpRaidriveCacheEpoch();

        return redirect()
            ->route('admin.library.raidrive', $back === '' ? [] : ['path' => $back])
            ->with('success', "Se importaron {$created} elemento(s) desde tu biblioteca local.");
    }

    public function importRecursive(Request $request): RedirectResponse
    {
        $this->authorize('create', Content::class);
        $this->allowLongRunningImportRequest();

        $max = max(1, min(10000, (int) config('media.raidrive_import_recursive_max', 2500)));

        $data = $request->validate([
            'return_path' => ['required', 'string', 'max:2000'],
            'category_id' => ['required', 'exists:categories,id'],
        ]);

        $browsePath = (string) $data['return_path'];
        if (! $this->localMedia->isSafeRelativePath($browsePath)) {
            abort(400, 'Ruta no válida.');
        }

        // count(roots)>1 equivale a isMultiRoot(); usamos roots() (pública) por si APCu/opcache sirve clase vieja.
        if (count($this->localMedia->roots()) > 1 && trim(str_replace(['/', '\\'], '/', $browsePath), '/') === '') {
            return redirect()
                ->route('admin.library.raidrive')
                ->with('success', 'Elegí primero una unidad (#0, #1…) y carpeta antes de importar en recursivo.');
        }

        if ($this->localMedia->resolveDirectoryRealPathFromBrowsePath($browsePath) === null) {
            return redirect()
                ->route('admin.library.raidrive', $browsePath === '' ? [] : ['path' => $browsePath])
                ->with('success', 'No se pudo abrir esa carpeta para importación recursiva.');
        }

        $category = Category::query()->findOrFail((int) $data['category_id']);
        $items = $this->localMedia->listVideosRecursiveForBrowsePath($browsePath, $max);
        $skipped = 0;

        $ctx = $this->localMedia->browseListRootContext($browsePath);
        $streamUrls = array_map(fn (array $r): string => $this->localMedia->streamUrlForPath($r['absolute']), $items);
        $existingUrls = $this->existingLocalStreamUrlLookup($streamUrls);

        $enrichEachRowRecursive = (bool) config('media.raidrive_import_recursive_enrich_tmdb', false)
            && (bool) config('services.tmdb.auto_on_import', false)
            && $this->tmdbPosters->isConfigured();

        if ($enrichEachRowRecursive) {
            $created = 0;
            foreach ($items as $row) {
                $abs = $row['absolute'];
                $allowed = $ctx !== null
                    ? $this->localMedia->isReadableFileUnderTrustedRoot($ctx['rootReal'], $abs)
                    : $this->localMedia->isAllowedReadableFile($abs);
                if (! $allowed) {
                    $skipped++;

                    continue;
                }

                $streamUrl = $this->localMedia->streamUrlForPath($abs);
                if (isset($existingUrls[$streamUrl])) {
                    $skipped++;

                    continue;
                }

                $content = Content::query()->create([
                    'category_id' => $category->id,
                    'title' => $row['title'],
                    'description' => null,
                    'type' => $category->type->value,
                    'stream_url' => $streamUrl,
                    'poster_url' => null,
                    'library_folder' => $row['library_folder'],
                    'duration' => null,
                    'is_active' => true,
                ]);
                $this->maybeEnrichPosterFromTmdb($content, true);
                $existingUrls[$streamUrl] = true;
                $created++;
            }
        } else {
            $pending = [];
            foreach ($items as $row) {
                $abs = $row['absolute'];
                $allowed = $ctx !== null
                    ? $this->localMedia->isReadableFileUnderTrustedRoot($ctx['rootReal'], $abs)
                    : $this->localMedia->isAllowedReadableFile($abs);
                if (! $allowed) {
                    $skipped++;

                    continue;
                }

                $streamUrl = $this->localMedia->streamUrlForPath($abs);
                if (isset($existingUrls[$streamUrl])) {
                    $skipped++;

                    continue;
                }

                $pending[] = [
                    'title' => $row['title'],
                    'stream_url' => $streamUrl,
                    'library_folder' => $row['library_folder'],
                ];
                $existingUrls[$streamUrl] = true;
            }

            $created = $this->insertImportedLibraryRowsBatch($category, $pending);
        }

        $this->localMedia->bumpRaidriveCacheEpoch();

        return redirect()
            ->route('admin.library.raidrive', $browsePath === '' ? [] : ['path' => $browsePath])
            ->with('success', "Importación recursiva: {$created} nuevo(s). Omitidos (duplicado o no permitido): {$skipped}. Tope de archivos en esta pasada: {$max}.");
    }

    public function importRecursiveFolders(Request $request): RedirectResponse
    {
        $this->authorize('create', Content::class);
        $this->allowLongRunningImportRequest();

        $maxGlobal = max(1, min(10000, (int) config('media.raidrive_import_recursive_max', 2500)));

        $data = $request->validate([
            'folder_paths' => ['required', 'array', 'min:1', 'max:40'],
            'folder_paths.*' => ['required', 'string', 'max:2000'],
            'category_id' => ['required', 'exists:categories,id'],
            'redirect_path' => ['nullable', 'string', 'max:2000'],
        ]);

        $category = Category::query()->findOrFail((int) $data['category_id']);
        $paths = array_values(array_unique($data['folder_paths']));

        $skipped = 0;
        $remaining = $maxGlobal;

        $enrichEachRowRecursive = (bool) config('media.raidrive_import_recursive_enrich_tmdb', false)
            && (bool) config('services.tmdb.auto_on_import', false)
            && $this->tmdbPosters->isConfigured();

        $pending = [];
        $created = 0;

        foreach ($paths as $browsePath) {
            if ($remaining <= 0) {
                break;
            }
            if (! $this->localMedia->isSafeRelativePath($browsePath)) {
                continue;
            }
            if ($this->localMedia->resolveDirectoryRealPathFromBrowsePath($browsePath) === null) {
                continue;
            }

            $items = $this->localMedia->listVideosRecursiveForBrowsePath($browsePath, $remaining);
            if ($items === []) {
                continue;
            }

            $ctx = $this->localMedia->browseListRootContext($browsePath);
            $streamUrls = array_map(fn (array $r): string => $this->localMedia->streamUrlForPath($r['absolute']), $items);
            $existingUrls = $this->existingLocalStreamUrlLookup($streamUrls);
            foreach ($pending as $p) {
                $existingUrls[$p['stream_url']] = true;
            }

            foreach ($items as $row) {
                if ($remaining <= 0) {
                    break 2;
                }
                $abs = $row['absolute'];
                $allowed = $ctx !== null
                    ? $this->localMedia->isReadableFileUnderTrustedRoot($ctx['rootReal'], $abs)
                    : $this->localMedia->isAllowedReadableFile($abs);
                if (! $allowed) {
                    $skipped++;
                    $remaining--;

                    continue;
                }

                $streamUrl = $this->localMedia->streamUrlForPath($abs);
                if (isset($existingUrls[$streamUrl])) {
                    $skipped++;
                    $remaining--;

                    continue;
                }

                if ($enrichEachRowRecursive) {
                    $content = Content::query()->create([
                        'category_id' => $category->id,
                        'title' => $row['title'],
                        'description' => null,
                        'type' => $category->type->value,
                        'stream_url' => $streamUrl,
                        'poster_url' => null,
                        'library_folder' => $row['library_folder'],
                        'duration' => null,
                        'is_active' => true,
                    ]);
                    $this->maybeEnrichPosterFromTmdb($content, true);
                    $existingUrls[$streamUrl] = true;
                    $created++;
                    $remaining--;
                } else {
                    $pending[] = [
                        'title' => $row['title'],
                        'stream_url' => $streamUrl,
                        'library_folder' => $row['library_folder'],
                    ];
                    $existingUrls[$streamUrl] = true;
                    $remaining--;
                }
            }
        }

        if (! $enrichEachRowRecursive && $pending !== []) {
            $created = $this->insertImportedLibraryRowsBatch($category, $pending);
        }

        $this->localMedia->bumpRaidriveCacheEpoch();

        $back = $request->string('redirect_path')->trim();
        if (! $this->localMedia->isSafeRelativePath((string) $back)) {
            $back = '';
        }

        return redirect()
            ->route('admin.library.raidrive', $back === '' ? [] : ['path' => $back])
            ->with('success', "Importación por carpetas marcadas: {$created} nuevo(s). Omitidos: {$skipped}. Tope total en esta pasada: {$maxGlobal}.");
    }

    /**
     * Importaciones recursivas pueden tardar minutos (disco de red + BD). Sin esto, PHP suele cortar a los 30–120 s.
     */
    private function allowLongRunningImportRequest(): void
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
    }

    private function maybeEnrichPosterFromTmdb(Content $content, bool $duringRecursiveBulkImport = false): void
    {
        if ($duringRecursiveBulkImport && ! (bool) config('media.raidrive_import_recursive_enrich_tmdb', false)) {
            return;
        }
        if (! (bool) config('services.tmdb.auto_on_import', false)) {
            return;
        }
        if (! $this->tmdbPosters->isConfigured()) {
            return;
        }
        if ($this->tmdbPosters->enrichContentPoster($content)) {
            usleep((int) config('services.tmdb.delay_ms_between_requests', 200) * 1000);
        }
    }

    /**
     * @param  list<string>  $streamUrls
     * @return array<string, true>
     */
    private function existingLocalStreamUrlLookup(array $streamUrls): array
    {
        $urls = array_values(array_unique(array_filter($streamUrls, static fn (string $u): bool => $u !== '')));
        if ($urls === []) {
            return [];
        }

        $found = [];
        foreach (array_chunk($urls, 450) as $chunk) {
            foreach (Content::query()->whereIn('stream_url', $chunk)->pluck('stream_url') as $u) {
                $found[(string) $u] = true;
            }
        }

        return $found;
    }

    /**
     * Inserción masiva (mucho más rápido que miles de {@see Content::create()}).
     *
     * @param  list<array{title: string, stream_url: string, library_folder: ?string}>  $rows
     */
    private function insertImportedLibraryRowsBatch(Category $category, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = now();
        $catId = $category->id;
        $typeVal = $category->type->value;
        $table = (new Content)->getTable();
        $inserted = 0;

        foreach (array_chunk($rows, 200) as $chunk) {
            $payload = [];
            foreach ($chunk as $r) {
                $payload[] = [
                    'category_id' => $catId,
                    'xtream_source_id' => null,
                    'stream_id' => null,
                    'source_type' => null,
                    'title' => $r['title'],
                    'description' => null,
                    'type' => $typeVal,
                    'stream_url' => $r['stream_url'],
                    'poster_url' => null,
                    'library_folder' => $r['library_folder'] ?? null,
                    'duration' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table($table)->insert($payload);
            $inserted += count($payload);
        }

        return $inserted;
    }

    private function parentRelativePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '') {
            return null;
        }

        $parts = explode('/', $path);
        array_pop($parts);

        return $parts === [] ? '' : implode('/', $parts);
    }

    /**
     * Cuenta filas local: bajo las raíces configuradas usando prefijos SQL (sin realpath por fila).
     *
     * @param  list<string>  $roots
     */
    private function countLocalRowsUnderRaidriveRootsFast(array $roots): int
    {
        $prefixes = [];
        foreach ($roots as $rp) {
            $rr = @realpath($rp);
            if ($rr === false) {
                continue;
            }
            $base = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $rr), DIRECTORY_SEPARATOR);
            $prefixes[] = LocalMediaService::LOCAL_PREFIX.$base.DIRECTORY_SEPARATOR;
        }

        if ($prefixes === []) {
            return 0;
        }

        return (int) Content::query()
            ->where('stream_url', 'like', 'local:%')
            ->where(function ($q) use ($prefixes) {
                foreach ($prefixes as $pref) {
                    $q->orWhere('stream_url', 'like', $pref.'%');
                }
            })
            ->count();
    }
}
