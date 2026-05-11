<x-panel-layout title="Contenido del catálogo">

    @php
        $rawSearch = request()->query('search');
        if (is_array($rawSearch)) {
            $rawSearch = $rawSearch[0] ?? null;
        }
        $searchQ = is_string($rawSearch) && trim($rawSearch) !== '' ? trim($rawSearch) : null;

        $rawType = request()->query('type');
        if (is_array($rawType)) {
            $rawType = $rawType[0] ?? null;
        }
        if ($rawType instanceof \Stringable) {
            $rawType = (string) $rawType;
        }
        $catalogType = is_string($rawType) ? strtolower(trim($rawType)) : null;
        if (! in_array($catalogType, ['vod', 'live', 'series'], true)) {
            $catalogType = null;
        }

        $vodHref = route('admin.contents.index', array_filter(['type' => 'vod', 'search' => $searchQ]));
        $seriesHref = route('admin.contents.index', array_filter(['type' => 'series', 'search' => $searchQ]));
        $liveHref = route('admin.contents.index', array_filter(['type' => 'live', 'search' => $searchQ]));
        $allHref = route('admin.contents.index', array_filter(['search' => $searchQ]));
    @endphp

    <section class="mb-6 rounded-2xl border border-cyan-500/25 bg-[#060a14]/55 p-5 shadow-lg backdrop-blur-md sm:p-6" aria-labelledby="admin-contents-search-heading">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 id="admin-contents-search-heading" class="text-lg font-bold tracking-tight text-white">Buscar</h2>
                <p class="mt-1 max-w-2xl text-sm leading-relaxed text-cyan-100/75">Título, URL del stream, carátula, carpeta biblioteca, descripción o categoría. La búsqueda respeta el tipo elegido abajo (Películas, Series o TV en vivo).</p>
            </div>
            <p class="shrink-0 rounded-xl border border-white/15 bg-white/5 px-4 py-3 text-center text-xs text-white/80">
                <span class="block text-2xl font-bold tabular-nums text-white">{{ number_format($stats['total']) }}</span>
                ítems en total
            </p>
        </div>
        <form method="GET" class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end" action="{{ route('admin.contents.index') }}">
            @if ($catalogType !== null)
                <input type="hidden" name="type" value="{{ $catalogType }}">
            @endif
            <div class="min-w-0 flex-1 sm:max-w-2xl">
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-cyan-200/90" for="admin-contents-search">Texto a buscar</label>
                <input
                    id="admin-contents-search"
                    type="search"
                    name="search"
                    value="{{ $searchQ }}"
                    placeholder="Ej. nombre del canal, parte de la URL, tmdb…"
                    autocomplete="off"
                    class="w-full rounded-xl border-2 border-white/25 bg-white/95 px-4 py-3 text-base text-slate-900 shadow-inner placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
                >
            </div>
            <button type="submit" class="admin-btn-primary shrink-0">Buscar</button>
            @if ($searchQ !== null)
                <a href="{{ route('admin.contents.index', array_filter(['type' => $catalogType])) }}" class="admin-btn-secondary shrink-0 border-white/30 !text-white hover:bg-white/10">Limpiar búsqueda</a>
            @endif
        </form>
    </section>

    <section class="mb-6" aria-labelledby="content-type-hubs-heading">
        <h2 id="content-type-hubs-heading" class="text-sm font-bold uppercase tracking-[0.2em] text-cyan-300/90">Películas · Series · TV en vivo</h2>
        <p class="mt-1 text-sm text-white/55">Entrá a cada sección para listar solo ese tipo y cambiar carátulas en <strong class="text-white/80">Editar</strong>.</p>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <a
                href="{{ $vodHref }}"
                @class([
                    'group flex min-h-[9.5rem] flex-col rounded-2xl border-2 p-5 text-left shadow-lg transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400',
                    'border-indigo-400 bg-gradient-to-br from-indigo-950/90 to-slate-950/95 ring-2 ring-indigo-400/35' => $catalogType === 'vod',
                    'border-white/15 bg-white/[0.06] hover:border-indigo-400/45 hover:bg-white/[0.09]' => $catalogType !== 'vod',
                ])
            >
                <span class="text-xs font-bold uppercase tracking-widest text-indigo-200/90">Películas</span>
                <span class="mt-2 text-xl font-bold text-white sm:text-2xl">VOD</span>
                <span class="mt-1 text-sm text-white/55">{{ number_format($stats['vod']) }} ítems</span>
                <span class="mt-auto pt-4 text-sm font-semibold text-indigo-200 group-hover:text-white">Entrar a Películas →</span>
            </a>
            <a
                href="{{ $seriesHref }}"
                @class([
                    'group flex min-h-[9.5rem] flex-col rounded-2xl border-2 p-5 text-left shadow-lg transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400',
                    'border-violet-400 bg-gradient-to-br from-violet-950/90 to-slate-950/95 ring-2 ring-violet-400/35' => $catalogType === 'series',
                    'border-white/15 bg-white/[0.06] hover:border-violet-400/45 hover:bg-white/[0.09]' => $catalogType !== 'series',
                ])
            >
                <span class="text-xs font-bold uppercase tracking-widest text-violet-200/90">Series</span>
                <span class="mt-2 text-xl font-bold text-white sm:text-2xl">Catálogo series</span>
                <span class="mt-1 text-sm text-white/55">{{ number_format($stats['series']) }} ítems</span>
                <span class="mt-auto pt-4 text-sm font-semibold text-violet-200 group-hover:text-white">Entrar a Series →</span>
            </a>
            <a
                href="{{ $liveHref }}"
                @class([
                    'group flex min-h-[9.5rem] flex-col rounded-2xl border-2 p-5 text-left shadow-lg transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400',
                    'border-emerald-400 bg-gradient-to-br from-emerald-950/90 to-slate-950/95 ring-2 ring-emerald-400/35' => $catalogType === 'live',
                    'border-white/15 bg-white/[0.06] hover:border-emerald-400/45 hover:bg-white/[0.09]' => $catalogType !== 'live',
                ])
            >
                <span class="text-xs font-bold uppercase tracking-widest text-emerald-200/90">TV en vivo</span>
                <span class="mt-2 text-xl font-bold text-white sm:text-2xl">Canales IPTV</span>
                <span class="mt-1 text-sm text-white/55">{{ number_format($stats['live']) }} ítems</span>
                <span class="mt-auto pt-4 text-sm font-semibold text-emerald-200 group-hover:text-white">Entrar a TV en vivo →</span>
            </a>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            @if ($catalogType !== null)
                <a href="{{ $allHref }}" class="font-medium text-sky-200 underline decoration-sky-300/50 underline-offset-2 hover:text-white">Ver todo mezclado (quitar filtro de tipo)</a>
                <span class="hidden text-white/35 sm:inline" aria-hidden="true">|</span>
                <span class="text-white/60">Listado filtrado: <strong class="text-white">{{ $catalogType }}</strong></span>
            @else
                <span class="text-white/50">Sin filtro de tipo: ves <strong class="text-white/80">Películas, series y canales</strong> juntos. Elegí una tarjeta arriba para acotar.</span>
            @endif
        </div>
    </section>

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.contents.create') }}" class="admin-btn-primary">Nuevo contenido</a>

        <a href="{{ route('admin.m3u.import') }}" class="admin-btn-ghost-light">Importar M3U / vídeo</a>
        <a href="{{ route('admin.m3u.manage') }}" class="admin-btn-ghost-light">Gestión listas M3U (borrar todo lo remoto)</a>

        <form
            id="bulk-delete-contents"
            method="POST"
            action="{{ route('admin.contents.bulk_destroy') }}"
            class="inline-flex flex-wrap items-center gap-2"
            onsubmit="return confirm('¿Eliminar DEFINITIVAMENTE los contenidos marcados? No se puede deshacer.');"
        >
            @csrf
            <button type="submit" class="admin-btn-secondary border border-red-500/50 bg-red-950/30 text-red-100 hover:bg-red-900/45 dark:text-red-200">
                Eliminar seleccionados
            </button>
        </form>

        <form method="POST" action="{{ route('admin.contents.enrich-posters') }}" class="inline" onsubmit="return confirm('Se consultará TMDB para hasta 30 ítems (VOD, series o canales en vivo) sin carátula; los nombres muy genéricos pueden no coincidir. ¿Continuar?');">
            @csrf
            <input type="hidden" name="limit" value="30">
            <button type="submit" class="admin-btn-ghost-light border border-violet-400/40 !text-violet-100 hover:!bg-violet-500/20">Buscar carátulas (TMDB)</button>
        </form>
    </div>

    <p class="mb-6 text-xs leading-relaxed text-amber-100/90">Marcá la casilla y usá <strong class="text-white">Eliminar seleccionados</strong> para borrar filas de <strong class="text-white">esta página</strong> (paginación). Para vaciar todas las URLs remotas: <a href="{{ route('admin.m3u.manage') }}" class="font-semibold underline decoration-amber-400/60 hover:text-white">Gestión listas M3U</a>.</p>

    <div class="mb-6 w-full rounded-xl border border-red-500/45 bg-red-950/35 px-4 py-4 text-red-50 shadow-inner ring-1 ring-red-400/20">

                <p class="text-sm font-semibold text-red-100">Quitar del panel — carpetas de tu biblioteca local (RaiDrive)</p>

                <p class="mt-1 text-xs text-red-100/85">Las filas son <strong class="text-white">las mismas carpetas</strong> que ves en el reproductor en <strong class="text-white">Películas → Carpetas principales</strong>: solo VOD <strong class="text-white">activos</strong> con categoría activa, importados con <code class="rounded bg-black/25 px-1">local:…</code> (RaiDrive). No se listan servicios que solo existan por películas desactivadas u ocultas. Al confirmar se quitan del catálogo las entradas en base de datos bajo esa ruta (incluye inactivos debajo); <strong class="text-white">no se borran archivos</strong> en disco ni RaiDrive.</p>

                @if ($vodFolders->isEmpty())

                    <p class="mt-3 text-sm text-red-100/80">No hay carpetas visibles en Películas (VOD activos + categoría activa + <code class="rounded bg-black/25 px-1">local:…</code>). Importá desde <a href="{{ route('admin.library.raidrive') }}" class="font-semibold text-white underline decoration-red-300/70 hover:text-amber-100">Biblioteca local</a> o activá el contenido/categoría en <a href="{{ route('admin.contents.index', ['type' => 'vod']) }}" class="font-semibold text-white underline">Contenido VOD</a>.</p>

                @else

                <form id="bulk-delete-vod-folders" method="POST" action="{{ route('admin.contents.bulk_destroy_library_folder') }}" class="mt-4 space-y-3">

                    @csrf

                    @if ($catalogType !== null)
                        <input type="hidden" name="redirect_type" value="{{ $catalogType }}">
                    @endif

                    @if ($searchQ !== null)
                        <input type="hidden" name="redirect_search" value="{{ $searchQ }}">
                    @endif

                    <input type="hidden" name="include_subfolders" value="1">

                    <div class="flex flex-wrap items-center gap-2">
                        <label for="folder-delete-filter" class="sr-only">Filtrar carpetas</label>
                        <input id="folder-delete-filter" type="search" autocomplete="off" placeholder="Filtrar por texto en la ruta…" class="admin-input-on-royal min-w-[12rem] max-w-md flex-1 text-sm text-slate-900 placeholder:text-slate-400">
                        <button type="button" id="folder-del-select-visible" class="admin-btn-ghost-light !px-3 !py-2 text-xs">Marcar visibles</button>
                        <button type="button" id="folder-del-clear" class="admin-btn-ghost-light !px-3 !py-2 text-xs">Desmarcar todo</button>
                    </div>

                    <div class="max-h-80 overflow-y-auto rounded-lg border border-slate-300 bg-white text-slate-950 shadow-inner ring-1 ring-black/5">
                        @foreach ($vodFolders as $vf)
                            <label class="folder-del-row group flex cursor-pointer items-start gap-2 border-b border-slate-200 bg-white px-3 py-2.5 text-left text-slate-950 last:border-b-0 hover:bg-indigo-50" data-folder-text="{{ Str::lower($vf->folder_label.' '.$vf->library_folder) }}">
                                <input type="checkbox" name="folders[]" value="{{ $vf->library_folder }}" class="folder-del-check mt-0.5 h-4 w-4 shrink-0 rounded border-slate-500 text-red-600 focus:ring-red-500">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-bold leading-snug text-slate-900 group-hover:text-indigo-950">{{ $vf->folder_label }}</span>
                                    <span class="mt-0.5 block break-all font-mono text-[11px] font-medium leading-snug text-slate-600 group-hover:text-indigo-900/90">{{ $vf->library_folder }}</span>
                                </span>
                                <span class="shrink-0 rounded-md bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-900">{{ $vf->contents_count }} VOD</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-end gap-3">
                        <div class="flex-1 sm:max-w-[14rem]">
                            <label for="folder-confirm-text" class="block text-xs font-medium text-red-100/90">Escribí <span class="font-mono text-white">ELIMINAR</span></label>
                            <input id="folder-confirm-text" type="text" name="folder_confirm" value="{{ old('folder_confirm') }}" placeholder="ELIMINAR" autocomplete="off" class="admin-input-on-royal mt-1 w-full font-mono text-sm text-slate-900 placeholder:text-slate-400">
                        </div>

                        <button type="submit" class="admin-btn-secondary border border-red-400/60 bg-red-900/50 text-red-50 hover:bg-red-800/55">Quitar del catálogo (no borra archivos)</button>
                    </div>

                    @error('folders')<p class="text-xs text-amber-200">{{ $message }}</p>@enderror
                    @error('folder_confirm')<p class="text-xs text-amber-200">{{ $message }}</p>@enderror

                </form>

                @endif

    </div>

    <p class="admin-panel-hint mb-6 hidden max-w-4xl" aria-hidden="true">

        Los vídeos que subes por «Importar M3U / vídeo» aparecen aquí (orden: más recientes primero). Las listas M3U con URLs http(s) se pueden ver y borrar en bloque desde <a href="{{ route('admin.m3u.manage') }}" class="font-semibold text-violet-200 underline decoration-violet-400/60 hover:text-white">Gestión listas M3U</a>. En el catálogo del <strong>cliente</strong> solo ves lo mismo iniciando sesión como cliente en <code>/app</code>.
        <span class="mt-2 block text-violet-100/90">Carátulas automáticas (TMDB): en themoviedb.org/settings/api copiá la <strong class="font-semibold text-white">API Key (v3)</strong> a <code class="rounded bg-white/10 px-1">TMDB_API_KEY</code> en <code class="rounded bg-white/10 px-1">.env</code> (no el token v4), <code class="rounded bg-white/10 px-1">php artisan config:clear</code>, y usá «Buscar carátulas» o <code class="rounded bg-white/10 px-1">php artisan content:enrich-posters</code>. Incluye <strong class="text-white">canales en vivo</strong> sin imagen (nombre tipo lista IPTV). Si el M3U trae <code class="rounded bg-white/10 px-1">tvg-logo="https://…"</code>, esa URL se guarda sola al importar. Opcional RaiDrive: <code class="rounded bg-white/10 px-1">TMDB_AUTO_POSTER_ON_IMPORT=true</code>.</span>

    </p>



    <div class="overflow-hidden rounded-2xl border border-white/20 bg-white text-slate-900 shadow-xl ring-1 ring-white/10">

        <div class="overflow-x-auto">

            <table class="min-w-full divide-y divide-slate-200 text-sm">

                <thead class="bg-gradient-to-r from-slate-100 to-blue-50/80">

                    <tr>

                        <th class="w-10 px-3 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-600">
                            <input type="checkbox" id="select-all-contents" aria-label="Seleccionar todos en esta página" class="h-4 w-4 rounded border-slate-400 text-indigo-600 focus:ring-indigo-500">
                        </th>

                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-600">ID</th>

                        <th class="w-16 px-2 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Carátula</th>

                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Título</th>

                        <th class="max-w-[10rem] px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Carpeta</th>

                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-600">URL stream</th>

                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Categoría</th>

                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Tipo</th>

                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Activo</th>

                        <th class="px-5 py-4"></th>

                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse ($contents as $c)

                        <tr class="bg-white hover:bg-slate-50/80">

                            <td class="px-3 py-4 align-middle">
                                <input
                                    type="checkbox"
                                    name="ids[]"
                                    value="{{ $c->id }}"
                                    form="bulk-delete-contents"
                                    class="content-row-check h-4 w-4 rounded border-slate-400 text-indigo-600 focus:ring-indigo-500"
                                    aria-label="Seleccionar {{ $c->title }}"
                                >
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-600">{{ $c->id }}</td>

                            <td class="px-2 py-3 align-middle">
                                @if (! empty($c->poster_url))
                                    <a href="{{ route('admin.contents.edit', $c) }}" class="inline-block rounded-md ring-1 ring-slate-200 transition hover:ring-indigo-400" title="Editar carátula">
                                        <img src="{{ $c->poster_url }}" alt="" class="h-14 w-10 object-cover" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                                    </a>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="max-w-[14rem] px-5 py-4 font-medium text-slate-900">{{ $c->title }}</td>

                            <td class="max-w-[10rem] px-5 py-4 font-mono text-[11px] leading-snug text-slate-600 break-all" title="{{ $c->library_folder ?? '' }}">{{ $c->library_folder ? Str::limit($c->library_folder, 56) : '—' }}</td>

                            <td class="max-w-[min(28rem,50vw)] px-5 py-4 font-mono text-[11px] leading-snug text-slate-700 break-all" title="{{ $c->stream_url }}">{{ Str::limit($c->stream_url, 96) }}</td>

                            <td class="max-w-[12rem] px-5 py-4 text-slate-700">{{ $c->category?->name }}</td>

                            <td class="px-5 py-4 text-slate-700">{{ $c->type->value }}</td>

                            <td class="px-5 py-4">{{ $c->is_active ? 'Sí' : 'No' }}</td>

                            <td class="px-5 py-4 text-right">

                                <a href="{{ route('admin.contents.edit', $c) }}" class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">Editar</a>

                                <form action="{{ route('admin.contents.destroy', $c) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este contenido de forma definitiva?');">@csrf @method('DELETE')

                                    <button type="submit" class="ms-3 font-semibold text-red-600 hover:text-red-800 hover:underline">{{ $c->type->value === 'vod' ? 'Eliminar película' : 'Eliminar' }}</button>

                                </form>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="10" class="px-5 py-10 text-center text-slate-600">No hay resultados. Probá otra búsqueda, elegí otra sección (Películas / Series / TV en vivo) o importá contenido.</td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        <div class="border-t border-slate-200 bg-slate-50/80 px-5 py-3">{{ $contents->links() }}</div>

    </div>

    <script>
        document.getElementById('select-all-contents')?.addEventListener('change', function () {
            document.querySelectorAll('.content-row-check').forEach(function (cb) {
                cb.checked = this.checked;
            }, this);
        });
        document.getElementById('bulk-delete-contents')?.addEventListener('submit', function (e) {
            if (!document.querySelectorAll('.content-row-check:checked').length) {
                e.preventDefault();
                alert('Marcá al menos un contenido para eliminar.');
            }
        });

        (function () {
            var filter = document.getElementById('folder-delete-filter');
            if (!filter) return;
            filter.addEventListener('input', function () {
                var q = filter.value.trim().toLowerCase();
                document.querySelectorAll('.folder-del-row').forEach(function (row) {
                    var t = row.getAttribute('data-folder-text') || '';
                    row.classList.toggle('hidden', q !== '' && t.indexOf(q) === -1);
                });
            });
            document.getElementById('folder-del-select-visible')?.addEventListener('click', function () {
                document.querySelectorAll('.folder-del-row:not(.hidden) .folder-del-check').forEach(function (cb) {
                    cb.checked = true;
                });
            });
            document.getElementById('folder-del-clear')?.addEventListener('click', function () {
                document.querySelectorAll('.folder-del-check').forEach(function (cb) {
                    cb.checked = false;
                });
            });
            document.getElementById('bulk-delete-vod-folders')?.addEventListener('submit', function (e) {
                var form = e.target;
                if (!form.querySelectorAll('.folder-del-check:checked').length) {
                    e.preventDefault();
                    alert('Marcá al menos una carpeta del catálogo para quitar.');
                    return;
                }
                if (!window.confirm('¿Quitar del catálogo (panel y reproductor) todos los VOD bajo las carpetas marcadas? No se borran archivos en disco ni en RaiDrive.')) {
                    e.preventDefault();
                }
            });
        })();
    </script>

</x-panel-layout>

