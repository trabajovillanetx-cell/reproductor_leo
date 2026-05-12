<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Content;
use App\Services\LocalMediaService;
use App\Services\StreamUrlValidator;
use App\Support\StreamingCatalogNav;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContentController extends Controller
{
    private const CONTENT_POSTER_UPLOAD_SUBDIR = 'imports/content-posters';

    public function __construct(
        private StreamUrlValidator $streamUrls
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Content::class);

        $q = Content::query()->with('category');
        $this->applyAdminContentsIndexFilters($request, $q);

        $vodFolders = $this->vodClientVisibleFolderRemovalRows();

        $filteredTotal = (clone $q)->count();

        return view('admin.contents.index', [
            'contents' => $q->orderByDesc('id')->paginate(20)->withQueryString(),
            'filteredTotal' => $filteredTotal,
            'stats' => [
                'total' => Content::query()->count(),
                'vod' => $this->countContentsMatchingCatalogType(ContentType::Vod),
                'live' => $this->countContentsMatchingCatalogType(ContentType::Live),
                'series' => $this->countContentsMatchingCatalogType(ContentType::Series),
            ],
            'vodFolders' => $vodFolders,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Content::class);

        return view('admin.contents.create', [
            'categoryOptions' => Category::orderedTreeOptions(),
            'types' => ContentType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Content::class);

        $data = $this->validatedContent($request);
        $this->streamUrls->assertValid($data['stream_url']);

        $posterFile = $request->file('poster_file');
        if ($posterFile instanceof UploadedFile) {
            $data['poster_url'] = $this->storeContentPosterFile($posterFile);
        }

        Content::query()->create($data);

        return redirect()->route('admin.contents.index')->with('success', 'Contenido creado.');
    }

    public function edit(Content $content): View
    {
        $this->authorize('update', $content);

        return view('admin.contents.edit', [
            'content' => $content,
            'categoryOptions' => Category::orderedTreeOptions(),
            'types' => ContentType::cases(),
        ]);
    }

    public function update(Request $request, Content $content): RedirectResponse
    {
        $this->authorize('update', $content);

        $oldPoster = trim((string) ($content->poster_url ?? ''));
        $data = $this->validatedContent($request);
        $this->streamUrls->assertValid($data['stream_url']);

        $posterFile = $request->file('poster_file');
        if ($posterFile instanceof UploadedFile) {
            if ($this->isManagedContentPosterUrl($oldPoster)) {
                $this->deleteManagedContentPosterFile($oldPoster);
            }
            $data['poster_url'] = $this->storeContentPosterFile($posterFile);
        } elseif ($this->isManagedContentPosterUrl($oldPoster)) {
            $newNorm = (string) ($data['poster_url'] ?? '');
            if ($oldPoster !== $newNorm) {
                $this->deleteManagedContentPosterFile($oldPoster);
            }
        }

        $content->update($data);

        return redirect()->route('admin.contents.index')->with('success', 'Contenido actualizado.');
    }

    public function destroy(Content $content): RedirectResponse
    {
        $this->authorize('delete', $content);

        $this->deleteManagedContentPosterFile(trim((string) ($content->poster_url ?? '')));
        $content->delete();

        return redirect()->route('admin.contents.index')->with('success', 'Contenido eliminado.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Content::class);

        if ($request->boolean('select_all_query')) {
            $filters = $request->validate([
                'search' => ['nullable', 'string', 'max:500'],
                'type' => ['nullable', 'string', Rule::in(['vod', 'live', 'series'])],
                'category_id' => ['nullable', 'integer', 'exists:categories,id'],
                'is_active' => ['nullable', 'in:0,1'],
            ]);

            $queryParams = array_filter([
                'search' => isset($filters['search']) ? trim((string) $filters['search']) : null,
                'type' => isset($filters['type']) ? trim((string) $filters['type']) : null,
                'category_id' => $filters['category_id'] ?? null,
                'is_active' => array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== ''
                    ? (string) $filters['is_active']
                    : null,
            ], static fn ($v) => $v !== null && $v !== '');

            $filterRequest = Request::create('/', 'GET', $queryParams);

            $q = Content::query();
            $this->applyAdminContentsIndexFilters($filterRequest, $q);

            $deleted = 0;

            while (true) {
                $batch = (clone $q)->orderBy('id')->limit(500)->get();
                if ($batch->isEmpty()) {
                    break;
                }

                DB::transaction(function () use ($batch, &$deleted): void {
                    foreach ($batch as $content) {
                        $this->authorize('delete', $content);
                        $this->deleteManagedContentPosterFile(trim((string) ($content->poster_url ?? '')));
                        $content->delete();
                        $deleted++;
                    }
                });
            }

            if ($deleted === 0) {
                return redirect()
                    ->back()
                    ->with('warning', 'No se eliminó ningún contenido (sin permiso o ningún resultado con el filtro actual).');
            }

            return redirect()->back()->with(
                'success',
                $deleted === 1 ? 'Se eliminó 1 contenido.' : 'Se eliminaron '.$deleted.' contenidos.'
            );
        }

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:contents,id'],
        ]);

        $deleted = 0;

        DB::transaction(function () use ($data, &$deleted): void {
            foreach ($data['ids'] as $id) {
                $content = Content::query()->find((int) $id);
                if ($content === null) {
                    continue;
                }
                $this->authorize('delete', $content);
                $this->deleteManagedContentPosterFile(trim((string) ($content->poster_url ?? '')));
                $content->delete();
                $deleted++;
            }
        });

        if ($deleted === 0) {
            return redirect()
                ->back()
                ->with('warning', 'No se eliminó ningún contenido (sin permiso o ítems inexistentes).');
        }

        return redirect()->back()->with(
            'success',
            $deleted === 1 ? 'Se eliminó 1 contenido.' : 'Se eliminaron '.$deleted.' contenidos.'
        );
    }

    public function bulkDestroyLibraryFolder(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Content::class);

        $data = $request->validate([
            'folders' => ['required', 'array', 'min:1'],
            'folders.*' => ['required', 'string', 'max:511'],
            'include_subfolders' => ['sometimes', 'boolean'],
            'folder_confirm' => ['required', 'string'],
            'redirect_type' => ['sometimes', 'nullable', Rule::in(['vod', 'live', 'series'])],
            'redirect_search' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        if (trim((string) $data['folder_confirm']) !== 'ELIMINAR') {
            return redirect()
                ->back()
                ->withErrors(['folder_confirm' => 'Debés escribir ELIMINAR tal cual para confirmar.'])
                ->withInput();
        }

        $validRoots = $this->filterToAllowedClientVisibleFolderLibs($data['folders']);

        $redirectSearch = isset($data['redirect_search'])
            ? trim((string) $data['redirect_search'])
            : '';
        $back = redirect()->route('admin.contents.index', array_filter([
            'type' => isset($data['redirect_type']) ? $data['redirect_type'] : null,
            'search' => $redirectSearch !== '' ? $redirectSearch : null,
        ]));

        if ($validRoots->isEmpty()) {
            return $back->with(
                'warning',
                'Ninguna carpeta marcada coincide con las carpetas visibles del catálogo (recargá la página).'
            );
        }

        $includeSubfolders = $request->boolean('include_subfolders');

        $ids = collect();

        foreach ($validRoots as $folder) {
            $q = Content::query()
                ->where('type', ContentType::Vod)
                ->where('stream_url', 'like', LocalMediaService::LOCAL_PREFIX.'%')
                ->whereNotNull('library_folder')
                ->where('library_folder', '!=', '');

            if ($includeSubfolders) {
                $likePrefix = $folder.'/';
                $q->where(function (Builder $w) use ($folder, $likePrefix): void {
                    $w->where('library_folder', $folder)
                        ->orWhere('library_folder', 'like', $likePrefix.'%');
                });
            } else {
                $q->where('library_folder', $folder);
            }

            $ids = $ids->merge($q->pluck('id'));
        }

        $ids = $ids->unique()->values();
        $deleted = 0;

        DB::transaction(function () use ($ids, &$deleted): void {
            foreach ($ids as $id) {
                $content = Content::query()->find((int) $id);
                if ($content === null) {
                    continue;
                }
                $this->authorize('delete', $content);
                $this->deleteManagedContentPosterFile(trim((string) ($content->poster_url ?? '')));
                $content->delete();
                $deleted++;
            }
        });

        if ($deleted === 0) {
            return $back->with('warning', 'No se eliminó ningún VOD.');
        }

        $folderLabel = $validRoots->count() === 1
            ? '1 carpeta'
            : $validRoots->count().' carpetas';

        $subtree = $includeSubfolders ? ', incluyendo subcarpetas de esas rutas.' : '.';

        return $back->with(
            'success',
            'Se quitaron del catálogo (panel) '.$deleted.' VOD ('.$folderLabel.')'.$subtree.' Los archivos en disco o RaiDrive no se borran.'
        );
    }

    /**
     * Mismo criterio que el catálogo cliente en Películas: VOD activos, categoría activa, stream local.
     */
    private function visibleClientPeliculasLocalBaseQuery(): Builder
    {
        return Content::query()
            ->where('type', ContentType::Vod)
            ->where('is_active', true)
            ->where('stream_url', 'like', LocalMediaService::LOCAL_PREFIX.'%')
            ->whereHas('category', fn (Builder $q) => $q->where('is_active', true));
    }

    /**
     * Carpetas tal como las muestra el reproductor en la primera pantalla de Películas (misma lógica de navegación).
     *
     * @return Collection<int, object{library_folder: string, folder_label: string, contents_count: int}>
     */
    private function vodClientVisibleFolderRemovalRows(): Collection
    {
        $navBase = $this->visibleClientPeliculasLocalBaseQuery();
        $rows = StreamingCatalogNav::promotedRootFolderLibRows(clone $navBase);

        if ($rows === []) {
            return collect();
        }

        return collect($rows)->map(function (array $row) use ($navBase): object {
            $lib = $row['lib'];
            $like = $lib.'/';
            $count = (clone $navBase)->where(function (Builder $q) use ($lib, $like): void {
                $q->where('library_folder', $lib)
                    ->orWhere('library_folder', 'like', $like.'%');
            })->count();

            return (object) [
                'library_folder' => $lib,
                'folder_label' => $row['label'],
                'contents_count' => $count,
            ];
        })->values();
    }

    /**
     * Solo acepta rutas `lib` que hoy aparecerían en la lista del admin (coinciden con el catálogo visible).
     *
     * @param  array<int, string>  $folders
     * @return Collection<int, string>
     */
    private function filterToAllowedClientVisibleFolderLibs(array $folders): Collection
    {
        $allowed = $this->vodClientVisibleFolderRemovalRows()
            ->pluck('library_folder')
            ->map(static fn (string $p): string => trim(str_replace('\\', '/', $p), '/'))
            ->all();

        if ($allowed === []) {
            return collect();
        }

        $allowedFlip = array_flip($allowed);

        $out = collect();
        foreach ($folders as $raw) {
            $folder = trim(str_replace('\\', '/', (string) $raw), '/');
            if ($folder === '' || str_contains($folder, '%') || str_contains($folder, '_')) {
                continue;
            }
            if (isset($allowedFlip[$folder])) {
                $out->push($folder);
            }
        }

        return $out->unique()->sort()->values();
    }

    /**
     * Mismos filtros que el listado del índice (búsqueda, tipo, categoría, activo).
     */
    private function applyAdminContentsIndexFilters(Request $request, Builder $q): void
    {
        if ($request->filled('search')) {
            $raw = trim((string) $request->string('search'));
            if ($raw !== '') {
                $like = '%'.$raw.'%';
                $q->where(function (Builder $sub) use ($like): void {
                    $sub->where('title', 'like', $like)
                        ->orWhere('stream_url', 'like', $like)
                        ->orWhere('poster_url', 'like', $like)
                        ->orWhere('library_folder', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('category', function (Builder $cq) use ($like): void {
                            $cq->where('name', 'like', $like);
                        });
                });
            }
        }

        $typeFilter = $this->catalogTypeFromRequest($request);
        if ($typeFilter !== null) {
            $this->applyAdminCatalogTypeFilter($q, $typeFilter);
        }

        if ($request->filled('category_id')) {
            $q->where('category_id', (int) $request->input('category_id'));
        }

        if ($request->has('is_active') && $request->string('is_active')->toString() !== '') {
            $q->where('is_active', $request->boolean('is_active'));
        }
    }

    /**
     * Filtro admin por tipo: solo la columna {@see Content::$type} (Películas / Series / TV en vivo no se mezclan).
     */
    private function applyAdminCatalogTypeFilter(Builder $q, ContentType $enum): void
    {
        $q->where('type', $enum);
    }

    private function countContentsMatchingCatalogType(ContentType $enum): int
    {
        return Content::query()->where('type', $enum)->count();
    }

    private function catalogTypeFromRequest(Request $request): ?ContentType
    {
        $raw = $request->query('type');
        if (is_array($raw)) {
            $raw = $raw[0] ?? null;
        }
        if ($raw instanceof \Stringable) {
            $raw = (string) $raw;
        }
        if (! is_string($raw)) {
            return null;
        }
        $t = strtolower(trim($raw));

        return ContentType::tryFrom($t);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedContent(Request $request): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::enum(ContentType::class)],
            'stream_url' => ['required', 'string', 'max:5000'],
            'poster_url' => ['nullable', 'string', 'max:2048'],
            'poster_file' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:12288'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        unset($data['poster_file']);

        $data['is_active'] = $request->has('is_active');

        $pu = isset($data['poster_url']) ? trim((string) $data['poster_url']) : '';
        $data['poster_url'] = $pu === '' ? null : $pu;

        return $data;
    }

    private function storeContentPosterFile(UploadedFile $file): string
    {
        $ext = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg'));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }

        $storedName = Str::uuid()->toString().'.'.$ext;
        $storedPath = $file->storeAs(self::CONTENT_POSTER_UPLOAD_SUBDIR, $storedName, 'public');

        return Storage::disk('public')->url($storedPath);
    }

    private function isManagedContentPosterUrl(string $posterUrl): bool
    {
        $diskPath = $this->managedContentPosterDiskPath($posterUrl);

        return $diskPath !== null;
    }

    private function deleteManagedContentPosterFile(string $posterUrl): void
    {
        $diskPath = $this->managedContentPosterDiskPath($posterUrl);
        if ($diskPath !== null) {
            Storage::disk('public')->delete($diskPath);
        }
    }

    private function managedContentPosterDiskPath(string $posterUrl): ?string
    {
        if ($posterUrl === '') {
            return null;
        }
        $parsed = parse_url($posterUrl, PHP_URL_PATH);
        if (! is_string($parsed) || $parsed === '') {
            return null;
        }
        $relative = ltrim($parsed, '/');
        if (! str_starts_with($relative, 'storage/')) {
            return null;
        }
        $diskPath = substr($relative, strlen('storage/'));
        $prefix = self::CONTENT_POSTER_UPLOAD_SUBDIR.'/';
        if (! str_starts_with($diskPath, $prefix)) {
            return null;
        }

        return $diskPath;
    }
}
