@php
    use App\Support\StreamingLabel;
    use Illuminate\Support\Str;

    $sectionTitle = match ($section) {
        'peliculas' => 'Películas',
        'series' => 'Series',
        'estrenos' => 'Estrenos',
        'tv' => 'TV en vivo',
        default => 'Catálogo',
    };
    // Sin ?q en enlaces internos: la búsqueda aplica solo a esta vista; después se limpia en el cliente.
    $catalogQuery = ['section' => $section];
    $libNorm = trim(str_replace('\\', '/', $lib ?? ''), '/');
    $currentFolderLabel = $libNorm !== '' ? StreamingLabel::decode(Str::afterLast($libNorm, '/')) : null;
    $parentLibHref = null;
    if ($libNorm !== '') {
        $seg = explode('/', $libNorm);
        if (count($seg) > 1) {
            array_pop($seg);
            $parentLibHref = route('app.home', $catalogQuery + ['lib' => implode('/', $seg)]);
        } else {
            $parentLibHref = route('app.home', $catalogQuery);
        }
    }
    $pageTitle = $currentFolderLabel !== null
        ? $currentFolderLabel.' — '.$sectionTitle
        : $sectionTitle;

    $catalogIntro = match ($section) {
        'peliculas' => 'Solo películas a la carta. Elegí una carpeta para ver los títulos, o buscá arriba.',
        'series' => 'Series por episodios. Entrá por carpeta o buscá un título arriba.',
        'estrenos' => 'Lo más reciente que sumaste al catálogo.',
        'tv' => 'Canales y eventos en vivo. Elegí una carpeta o buscá arriba.',
        default => 'Explorá por sección o por carpetas. Todo listo para reproducir.',
    };

    $folderBrowseTitle = $libNorm === '' ? 'Carpetas principales' : 'Títulos en esta carpeta';
    $folderBrowseSubtitle = $libNorm === ''
        ? 'Elegí una carpeta del catálogo para entrar.'
        : 'Cada carátula es un título dentro de esta ubicación. Tocá una para abrirla o usá la lista de abajo para dar play.';
@endphp

<x-streaming-shell :title="$pageTitle">
    {{-- Sin padding lateral en main: así el hero y la barra de búsqueda llegan al borde derecho del panel (#buscar y catálogo siguen indentados debajo). --}}
    <main
        class="w-full min-w-0 max-w-none pb-24 pt-3 sm:pb-20 lg:pt-4"
        x-data="{
            searchOpen: false,
            syncFromHash() {
                this.searchOpen = window.location.hash === '#buscar-catalogo';
                if (this.searchOpen) {
                    this.$nextTick(() => this.$refs.searchInput?.focus());
                }
            },
            stripExecutedSearchFromUrl() {
                const u = new URL(window.location.href);
                const raw = u.searchParams.get('q');
                if (raw === null || String(raw).trim() === '') {
                    return;
                }
                u.searchParams.delete('q');
                const qs = u.searchParams.toString();
                history.replaceState(null, '', u.pathname + (qs ? '?' + qs : '') + u.hash);
                this.$nextTick(() => {
                    const el = this.$refs.searchInput;
                    if (el) el.value = '';
                });
            },
            closeSearch() {
                this.searchOpen = false;
                const u = new URL(window.location.href);
                u.hash = '';
                const hadQ = u.searchParams.has('q') && String(u.searchParams.get('q')).trim() !== '';
                if (hadQ) {
                    u.searchParams.delete('q');
                    const qs = u.searchParams.toString();
                    window.location.replace(u.pathname + (qs ? '?' + qs : ''));
                    return;
                }
                const qs = u.searchParams.toString();
                history.replaceState(null, '', u.pathname + (qs ? '?' + qs : ''));
                const el = this.$refs.searchInput;
                if (el) el.value = '';
            },
        }"
        x-init="syncFromHash(); stripExecutedSearchFromUrl(); window.addEventListener('hashchange', () => syncFromHash())"
    >
        @if ($showHero ?? false)
            @include('app.partials.home-hero', [
                'slides' => $heroSlides ?? [],
                'heroPreviewUrlTemplate' => $heroPreviewUrlTemplate ?? '',
            ])
        @endif

        {{-- Recién agregados: solo en inicio --}}
        @if (($section ?? 'todas') === 'todas' && ($lib ?? '') === '' && ($search ?? '') === '' && ($latestContents ?? collect())->isNotEmpty())
            <section class="mx-auto mb-10 max-w-6xl px-4 sm:px-8 lg:px-0" aria-labelledby="latest-heading">
                <div class="mb-4 sm:mb-5">
                    <h2 id="latest-heading" class="text-sm font-bold uppercase tracking-[0.18em] text-white/45">Recién agregados</h2>
                    <p class="mt-1 text-xs text-white/40">Los últimos títulos sumados al catálogo.</p>
                </div>
                <div class="streaming-catalog-grid isolate">
                    @foreach ($latestContents as $item)
                        @php
                            $isSeries = $item->type->value === 'series';
                            $folderParts = explode('/', $item->library_folder);
                            $folderTitle = end($folderParts);
                            $displayTitle = $isSeries ? $folderTitle : $item->title;
                            $section = $isSeries ? 'series' : 'peliculas';
                            $itemUrl = $isSeries
                                ? route('app.home', ['section' => $section, 'lib' => $item->library_folder])
                                : route('app.playback.prepare', $item);
                        @endphp
                        <article class="min-w-0">
                            <a href="{{ $itemUrl }}" class="group flex min-h-0 min-w-0 w-full flex-1 flex-col" aria-label="{{ $displayTitle }}">
                                <div class="streaming-cyber-poster streaming-poster-tile relative z-0 w-full bg-slate-900 shadow-md shadow-black/30 ring-1 ring-white/10 transition duration-300 ease-out group-hover:z-20 group-hover:-translate-y-1 group-hover:ring-fuchsia-400/45 rounded-xl">
                                    @if ($item->poster_url)
                                        <img src="{{ $item->poster_url }}" alt="" role="presentation" class="transition duration-300 ease-out group-hover:brightness-110" loading="lazy" decoding="async">
                                    @else
                                        <div class="streaming-poster-placeholder gap-2 bg-gradient-to-br from-slate-800 via-slate-900 to-slate-950 px-2">
                                            <span class="pointer-events-none text-center text-[10px] font-semibold uppercase tracking-widest text-white/35">Sin carátula</span>
                                        </div>
                                    @endif
                                </div>
                                <p class="mt-2.5 line-clamp-2 text-center text-[13px] font-semibold leading-snug text-white/90 group-hover:text-white">{{ $displayTitle }}</p>
                            </a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Búsqueda solo visible con #buscar-catalogo (enlace desde el ícono del menú lateral / inferior). Sin barra fija duplicada. --}}
        <div
            id="buscar-catalogo"
            x-show="searchOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-y-2 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            role="region"
            aria-label="Buscar en el catálogo"
            class="sticky top-0 z-30 mb-6 w-full border-b border-white/10 bg-[#030712]/88 px-4 py-3 backdrop-blur-md sm:px-8 lg:px-12"
        >
            <form method="GET" action="{{ route('app.home') }}" id="catalog-search-form" class="mx-auto flex max-w-2xl flex-col gap-2 sm:flex-row sm:items-stretch sm:gap-3">
                <input type="hidden" name="section" value="{{ $section }}">
                @if (($lib ?? '') !== '')
                    <input type="hidden" name="lib" value="{{ $lib }}">
                @endif
                <input
                    type="search"
                    name="q"
                    id="catalog-search-q"
                    x-ref="searchInput"
                    value=""
                    placeholder="Buscar títulos…"
                    autocomplete="off"
                    class="min-h-[48px] flex-1 rounded-2xl border border-white/15 bg-white/10 px-4 text-[15px] text-white placeholder:text-white/45 outline-none focus:border-violet-400/60 focus:bg-white/[0.14]"
                >
                <div class="flex gap-2">
                    <button type="submit" class="min-h-[48px] flex-1 rounded-2xl bg-violet-600 px-6 text-sm font-bold uppercase tracking-wide text-white shadow-lg hover:bg-violet-500 sm:flex-none">Buscar</button>
                    <button type="button" class="rounded-2xl border border-white/20 px-4 text-sm font-semibold text-white/90 hover:bg-white/10" x-on:click="closeSearch()">Cerrar</button>
                </div>
            </form>
        </div>

        <div class="px-4 sm:px-8 lg:px-12">
        {{-- Cabecera: dónde estás (sin duplicar barra móvil aparte) --}}
        @if ($libNorm !== '' && $parentLibHref !== null)
            <header class="mx-auto mb-6 flex max-w-6xl items-start gap-3 sm:mb-8 sm:items-center">
                <a
                    href="{{ $parentLibHref }}"
                    class="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/[0.08] text-lg font-bold leading-none text-white shadow-sm transition hover:border-violet-400/40 hover:bg-white/[0.12]"
                    aria-label="Volver a la carpeta anterior"
                >&lsaquo;</a>
                <div class="min-w-0 flex-1 border-b border-white/10 pb-5">
                    <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-cyan-400/85">{{ $sectionTitle }}</p>
                    <h1 class="mt-1.5 text-balance text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $currentFolderLabel }}</h1>
                    <p class="mt-2 max-w-xl text-sm leading-relaxed text-white/50">Navegá por subcarpetas o elegí un póster abajo para ver la película o serie.</p>
                </div>
            </header>
        @else
            <header class="mx-auto mb-6 max-w-6xl border-b border-white/10 pb-6 sm:mb-8">
                <h1 class="text-balance text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $sectionTitle }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-white/55">{{ $catalogIntro }}</p>
            </header>
        @endif

        @if (! empty($libraryBreadcrumb))
            <nav class="mx-auto mb-6 flex max-w-6xl flex-wrap items-center gap-x-1 gap-y-1 rounded-xl border border-white/[0.07] bg-black/25 px-3 py-2.5 text-sm text-white/80 backdrop-blur-sm sm:px-4" aria-label="Ruta en el catálogo">
                <a href="{{ route('app.home', $catalogQuery) }}" class="rounded-md px-2 py-1 font-medium text-violet-300 transition hover:bg-white/10 hover:text-white">Inicio</a>
                @foreach ($libraryBreadcrumb as $i => $crumb)
                    <span class="px-0.5 text-white/30" aria-hidden="true">/</span>
                    @if ($i < count($libraryBreadcrumb) - 1)
                        <a href="{{ route('app.home', $catalogQuery + ['lib' => $crumb['lib']]) }}" class="rounded-md px-2 py-1 font-medium text-violet-200/95 transition hover:bg-white/10 hover:text-white">{{ $crumb['label'] }}</a>
                    @else
                        <span class="rounded-md px-2 py-1 font-semibold text-white">{{ $crumb['label'] }}</span>
                    @endif
                @endforeach
            </nav>
        @endif

        @if (! empty($folderBrowseRows) && ($section !== 'todas' || ($lib ?? '') !== ''))
            <section class="mx-auto mb-10 max-w-6xl px-1 sm:px-0" aria-labelledby="folder-nav-heading">
                <div class="mb-4 sm:mb-5">
                    <h2 id="folder-nav-heading" class="text-sm font-bold uppercase tracking-[0.18em] text-white/45">{{ $folderBrowseTitle }}</h2>
                    <p class="mt-1 max-w-2xl text-xs leading-relaxed text-white/45">{{ $folderBrowseSubtitle }}</p>
                </div>
                {{-- Misma rejilla que “Reproducir desde aquí” / Estrenos en todos los niveles (sin rejilla densa de mini-carátulas). --}}
                <div class="streaming-catalog-grid isolate">
                    @foreach ($folderBrowseRows as $fold)
                        <article class="min-w-0">
                            <a
                                href="{{ route('app.home', $catalogQuery + ['lib' => $fold['lib']]) }}"
                                class="group flex min-h-0 min-w-0 w-full flex-1 flex-col"
                                aria-label="Abrir: {{ $fold['label'] }}"
                            >
                                <div class="streaming-cyber-poster streaming-poster-tile relative z-0 w-full bg-slate-900 shadow-md shadow-black/30 ring-1 ring-white/10 transition duration-300 ease-out motion-reduce:transition-none motion-reduce:duration-0 motion-reduce:group-hover:translate-y-0 group-hover:z-20 group-hover:-translate-y-1 group-hover:ring-fuchsia-400/45 group-hover:shadow-lg group-hover:shadow-cyan-950/30 rounded-xl">
                                    @if (! empty($fold['preview_poster_url']))
                                        <img src="{{ $fold['preview_poster_url'] }}" alt="" role="presentation" class="transition duration-300 ease-out motion-reduce:transition-none group-hover:brightness-110 motion-reduce:group-hover:brightness-100" loading="lazy" decoding="async">
                                    @else
                                        <div class="streaming-poster-placeholder gap-2 bg-gradient-to-br from-slate-800 via-slate-900 to-slate-950 px-2">
                                            <span class="pointer-events-none text-center text-[10px] font-semibold uppercase tracking-widest text-white/35">Sin carátula</span>
                                        </div>
                                    @endif
                                </div>
                                <p class="mt-2.5 line-clamp-2 text-center text-[13px] font-semibold leading-snug text-white/90 group-hover:text-white">{{ $fold['label'] }}</p>
                            </a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Películas / Series / TV en raíz: solo carpetas; el catálogo aparece al elegir carpeta o al buscar. --}}
        @unless ($suppressRootCatalog ?? false)
            <section class="mx-auto max-w-6xl" aria-labelledby="catalog-grid-heading">
                <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
                    <h2 id="catalog-grid-heading" class="text-sm font-bold uppercase tracking-[0.18em] text-white/45">
                        @if (! empty($folderBrowseRows) && ($section !== 'todas' || ($lib ?? '') !== ''))
                            Reproducir desde aquí
                        @else
                            Catálogo
                        @endif
                    </h2>
                    @if (!$contents->isEmpty())
                        <p class="text-xs text-white/40">{{ $contents->total() }} resultado(s)</p>
                    @endif
                </div>
                <div class="streaming-catalog-grid isolate">
                    @forelse ($contents as $item)
                        <article class="min-w-0">
                            <a href="{{ route('app.playback.prepare', $item) }}" class="group flex min-h-0 min-w-0 flex-1 flex-col" aria-label="Reproducir: {{ StreamingLabel::decode($item->title) }}">
                                @php
                                    $posterTypeClass = match($item->type->value) {
                                        'vod' => 'poster-type-vod',
                                        'series' => 'poster-type-series',
                                        'live' => 'poster-type-live',
                                        default => '',
                                    };
                                @endphp
                                <div class="streaming-cyber-poster streaming-poster-tile {{ $posterTypeClass }} relative z-0 w-full bg-slate-900 transition duration-300 ease-out motion-reduce:transition-none motion-reduce:duration-0 motion-reduce:group-hover:translate-y-0 group-hover:z-20 group-hover:-translate-y-1 rounded-xl">
                                    @if ($item->poster_url)
                                        <img src="{{ $item->poster_url }}" alt="" role="presentation" class="transition duration-300 ease-out motion-reduce:transition-none group-hover:brightness-110 motion-reduce:group-hover:brightness-100" loading="lazy" decoding="async">
                                    @else
                                        <div class="streaming-poster-placeholder gap-2 bg-gradient-to-br from-slate-800 via-slate-900 to-slate-950 px-2">
                                            <span class="pointer-events-none text-center text-[10px] font-semibold uppercase tracking-widest text-white/35">Sin carátula</span>
                                            <span class="line-clamp-3 text-center text-[11px] font-medium leading-snug text-white/50">{{ StreamingLabel::decode($item->title) }}</span>
                                        </div>
                                    @endif
                                </div>
                                <p class="mt-2.5 line-clamp-2 text-center text-[13px] font-semibold leading-snug text-white/90 group-hover:text-white">{{ StreamingLabel::decode($item->title) }}</p>
                            </a>
                        </article>
                    @empty
                        <div class="streaming-cyber-empty col-span-full mx-auto w-full max-w-lg rounded-2xl border border-white/10 px-6 py-12 text-center backdrop-blur-sm sm:px-10 sm:py-14">
                            @if (! empty($folderBrowseRows) && ($section !== 'todas' || ($lib ?? '') !== ''))
                                <p class="text-lg font-semibold text-white">Todavía no hay películas ni episodios en esta carpeta.</p>
                                <p class="mt-3 text-sm leading-relaxed text-white/55">Arriba tenés <strong class="text-white/80">títulos</strong> para abrir por carpeta. Si ya deberían verse vídeos aquí, revisá en el admin que el contenido tenga la ruta de biblioteca correcta.</p>
                            @else
                                <p class="text-lg font-semibold text-white">No hay nada para mostrar con estos filtros.</p>
                                <p class="mt-3 text-sm leading-relaxed text-white/55">Probá otra sección en el menú o desde la <strong class="text-white/85">lupa (#buscar)</strong>. Si importaste listas o vídeos y no ves nada: en el admin revisá que cada <strong class="text-white/85">categoría esté activa</strong>, el <strong class="text-white/85">contenido esté activo</strong> y, en <strong class="text-white/85">Estrenos</strong>, que haya títulos cargados en los últimos 60 días.</p>
                            @endif
                        </div>
                    @endforelse
                </div>
            </section>

            @if (!$contents->isEmpty())
                <div class="mt-10 flex justify-center">{{ $contents->links('pagination.streaming-catalog') }}</div>
            @endif
        @endunless
        </div>
    </main>
</x-streaming-shell>
