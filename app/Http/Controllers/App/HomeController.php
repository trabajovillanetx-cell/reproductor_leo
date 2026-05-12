<?php

namespace App\Http\Controllers\App;

use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Category;
use App\Models\Content;
use App\Models\LibraryFolderPoster;
use App\Services\StreamMimeResolver;
use App\Support\StreamingCatalogNav;
use App\Support\StreamingLabel;
use App\Support\TmdbImageUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Catálogo tipo streaming: filtros desde el menú (Películas, Series, Estrenos, TV).
 */
class HomeController extends Controller
{
    /** @var list<string> */
    private const ALLOWED_SECTIONS = ['todas', 'peliculas', 'series', 'estrenos', 'tv'];

    public function __invoke(Request $request): View
    {
        $search = $request->string('q')->trim();
        $section = strtolower((string) $request->query('section', 'todas'));
        if ($section === '' || ! in_array($section, self::ALLOWED_SECTIONS, true)) {
            $section = 'todas';
        }

        $lib = trim(str_replace('\\', '/', $request->string('lib')->trim()), '/');

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $contentsQuery = $this->catalogBaseQuery((string) $search, $section);

        if ($lib !== '') {
            $contentsQuery->where(function (Builder $q) use ($lib): void {
                $q->where('library_folder', $lib)
                    ->orWhere('library_folder', 'like', $lib.'/%');
            });
        }

        $navBase = $this->catalogBaseQuery((string) $search, $section);
        $folderNav = StreamingCatalogNav::libraryFolderNav($navBase, $lib);
        $folderNav = $this->mergeFolderNavPreviewPosters($folderNav, $navBase);

        $folderBrowseRows = $folderNav;
        if ($section === 'todas' && $lib === '' && (string) $search === '' && count($folderNav) === 1) {
            $folderBrowseRows = [];
        }

        $promoteSinglePrefix = $lib === ''
            && (string) $search === ''
            && in_array($section, ['peliculas', 'series', 'tv'], true);

        if ($promoteSinglePrefix) {
            $folderBrowseRows = $this->mergeFolderNavPreviewPosters(
                StreamingCatalogNav::promotedRootFolderLibRows(clone $navBase),
                $navBase
            );
        }

        /*
         * Misma carpeta de biblioteca + mismo título: varios archivos (calidades, duplicados de importación)
         * generan varias filas en `contents`. En la rejilla plana (sin subcarpetas que mostrar) dejamos una
         * tarjeta por película (se conserva el id más reciente).
         */
        if (
            $section === 'peliculas'
            && (string) $search === ''
            && $lib !== ''
            && $folderBrowseRows === []
        ) {
            $this->applyPeliculasFlatGridDedupe($contentsQuery);
        }

        $hasFlatCatalogAtRoot = (clone $navBase)
            ->where(function (Builder $q): void {
                $q->whereNull('library_folder')->orWhere('library_folder', '');
            })
            ->exists();

        /*
         * En Películas / Series / TV, si solo hay RaiDrive (todo con carpeta), mostramos primero carpetas y ocultamos
         * la rejilla plana hasta que el usuario entre a una carpeta o busque. Si también hay contenido sin carpeta
         * —típico de listas M3U (library_folder vacío)— la rejilla debe mostrarse; si no, los canales “sueltos”
         * desaparecen aunque el menú sugiera contenido por carpetas RaiDrive.
         */
        $suppressRootCatalog = in_array($section, ['peliculas', 'series', 'tv'], true)
            && $lib === ''
            && (string) $search === ''
            && count($folderBrowseRows) > 0
            && ! $hasFlatCatalogAtRoot;

        $libraryBreadcrumb = $this->libraryBreadcrumb($lib);

        $recentForProfile = collect();
        $pid = $request->session()->get('streaming_profile_id');
        if (is_numeric($pid)) {
            $seen = [];
            $orderedIds = [];
            $logs = AccessLog::query()
                ->where('user_id', $request->user()->id)
                ->where('customer_profile_id', (int) $pid)
                ->where('action', 'playback_request')
                ->whereNotNull('content_id')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(80)
                ->get(['content_id']);

            foreach ($logs as $log) {
                $cid = $log->content_id;
                if ($cid !== null && ! isset($seen[$cid])) {
                    $seen[$cid] = true;
                    $orderedIds[] = $cid;
                    if (count($orderedIds) >= 8) {
                        break;
                    }
                }
            }

            if ($orderedIds !== []) {
                $recentForProfile = Content::query()
                    ->whereIn('id', $orderedIds)
                    ->where('is_active', true)
                    ->whereHas('category', fn ($q) => $q->where('is_active', true))
                    ->with('category')
                    ->get()
                    ->sortBy(fn (Content $c) => array_search($c->id, $orderedIds, true))
                    ->values();
            }
        }

        $heroSlides = $this->heroSlidesPayload();

        if ($suppressRootCatalog) {
            $page = max(1, (int) $request->input('page', 1));
            $contents = new LengthAwarePaginator([], 0, 24, $page, [
                'path' => $request->url(),
                'pageName' => 'page',
            ]);
            $contents->withQueryString();
        } else {
            $contents = $contentsQuery->paginate(24)->withQueryString();
        }

        return view('app.home', [
            'categories' => $categories,
            'contents' => $contents,
            'search' => (string) $search,
            'section' => $section,
            'lib' => $lib,
            'heroPreviewUrlTemplate' => $heroSlides !== [] ? $this->heroPreviewUrlTemplateString() : '',
            'folderNav' => $folderNav,
            'folderBrowseRows' => $folderBrowseRows,
            'suppressRootCatalog' => $suppressRootCatalog,
            'libraryBreadcrumb' => $libraryBreadcrumb,
            'recentForProfile' => $recentForProfile,
            'heroSlides' => $heroSlides,
            'showHero' => $heroSlides !== [] && $section === 'todas' && $search->isEmpty() && $lib === '',
        ]);
    }

    private function catalogBaseQuery(string $search, string $section): Builder
    {
        $contentsQuery = Content::query()
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->with('category')
            ->orderByDesc('id');

        if ($search !== '') {
            $contentsQuery->where('title', 'like', '%'.$search.'%');
        }

        StreamingCatalogNav::applyCatalogSectionFilter($contentsQuery, $section);

        return $contentsQuery;
    }

    /**
     * Una sola fila de catálogo por (carpeta biblioteca + título) al listar vídeos en una ruta sin subcarpetas.
     */
    private function applyPeliculasFlatGridDedupe(Builder $contentsQuery): void
    {
        $table = $contentsQuery->getModel()->getTable();
        $sub = clone $contentsQuery;
        $sub->reorder();
        $sub->setEagerLoads([]);
        $sub->selectRaw('MAX('.$table.'.id) as id')
            ->groupBy($table.'.library_folder', DB::raw('LOWER(TRIM('.$table.'.title))'));

        $contentsQuery->whereIn($table.'.id', $sub);
    }

    /**
     * @return list<array{label: string, lib: string}>
     */
    private function libraryBreadcrumb(string $lib): array
    {
        if ($lib === '') {
            return [];
        }

        $parts = explode('/', $lib);
        $crumbs = [];
        $acc = '';
        foreach ($parts as $part) {
            $acc = $acc === '' ? $part : $acc.'/'.$part;
            $crumbs[] = ['label' => StreamingLabel::decode($part), 'lib' => $acc];
        }

        return $crumbs;
    }

    /**
     * @param  list<array{label: string, lib: string}>  $rows
     * @return list<array{label: string, lib: string, preview_poster_url: ?string}>
     */
    private function mergeFolderNavPreviewPosters(array $rows, Builder $base): array
    {
        if ($rows === []) {
            return [];
        }

        /** @var array<string, string> */
        $manualPosters = LibraryFolderPoster::query()->pluck('poster_url', 'folder_path')->all();

        $candidates = (clone $base)
            ->setEagerLoads([])
            ->whereNotNull('library_folder')
            ->whereNotNull('poster_url')
            ->where('poster_url', '!=', '')
            ->orderByDesc('id')
            ->limit(25_000)
            ->get(['library_folder', 'poster_url']);

        return array_map(function (array $row) use ($candidates, $manualPosters): array {
            $want = StreamingLabel::normalizeLibraryPath($row['lib']);
            if (isset($manualPosters[$want]) && trim((string) $manualPosters[$want]) !== '') {
                $row['preview_poster_url'] = trim((string) $manualPosters[$want]);

                return $row;
            }
            $poster = null;
            foreach ($candidates as $c) {
                $lf = trim(str_replace('\\', '/', (string) $c->library_folder), '/');
                $have = StreamingLabel::normalizeLibraryPath($lf);
                if ($have === $want || str_starts_with($have, $want.'/')) {
                    $poster = $c->poster_url;
                    break;
                }
            }
            $row['preview_poster_url'] = $poster;

            return $row;
        }, $rows);
    }

    /**
     * URL con id placeholder no colisionante para reemplazar en el cliente (__CONTENT_ID__).
     */
    private function heroPreviewUrlTemplateString(): string
    {
        $needle = 988_880_001;
        $built = route('app.hero_preview.token', ['content' => $needle]);

        return str_replace((string) $needle, '__CONTENT_ID__', $built);
    }

    /**
     * Títulos destacados en el banner: orden distinto cada día (zona horaria de la app), a partir del catálogo activo.
     *
     * @return list<array{title: string, description: string, poster: ?string, playUrl: string, typeLabel: string, contentId: int, preview: bool}>
     */
    private function heroSlidesPayload(): array
    {
        $dayKey = now()->timezone(config('app.timezone'))->toDateString();

        $ids = Content::query()
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return [];
        }

        usort($ids, function (int $a, int $b) use ($dayKey): int {
            $ha = hexdec(substr(hash('sha256', $a.'|'.$dayKey), 0, 12));
            $hb = hexdec(substr(hash('sha256', $b.'|'.$dayKey), 0, 12));

            return $ha <=> $hb;
        });

        $pick = array_slice($ids, 0, 8);

        $contents = Content::query()
            ->whereIn('id', $pick)
            ->with('category')
            ->get()
            ->sortBy(fn (Content $c) => array_search($c->id, $pick, true))
            ->values();

        $mimeResolver = app(StreamMimeResolver::class);

        return $contents->map(function (Content $c) use ($mimeResolver): array {
            $desc = Str::limit(strip_tags((string) ($c->description ?? '')), 260);

            return [
                'title' => $c->title,
                'description' => $desc,
                'poster' => TmdbImageUrl::upsizePosterForHero($c->poster_url),
                'playUrl' => route('app.playback.prepare', $c),
                'contentId' => $c->id,
                'preview' => $mimeResolver->supportsHeroPreview($c),
                'typeLabel' => match ($c->type) {
                    ContentType::Vod => 'Película',
                    ContentType::Series => 'Serie',
                    ContentType::Live => 'TV en vivo',
                },
            ];
        })->all();
    }
}
