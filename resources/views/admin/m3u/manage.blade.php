<x-panel-layout title="Listas M3U — contenido por URL">
    <div class="mb-6 flex flex-wrap gap-3">
        <a href="{{ route('admin.contents.index') }}" class="admin-btn-primary ring-2 ring-violet-400/50">Contenido — buscar canales</a>
        <a href="{{ route('admin.contents.index', ['type' => 'live']) }}" class="admin-btn-secondary">Solo en vivo (lista + buscador)</a>
        <a href="{{ route('admin.m3u.import') }}" class="admin-btn-secondary">Importar nueva lista</a>
        <a href="{{ route('admin.categories.index') }}" class="admin-btn-secondary">Gestionar categorías</a>
    </div>

    <div class="mb-6 hidden rounded-xl border border-violet-400/40 bg-violet-950/40 p-4 text-sm text-violet-50" aria-hidden="true">
        <p class="font-semibold text-white">¿Dónde está el buscador por nombre / URL?</p>
        <p class="mt-2 text-violet-100/95">
            Esta página es para <strong class="text-white">estadísticas, barrido y borrado masivo</strong> por URL remota. La tabla de abajo solo muestra una <strong class="text-white">muestra de 25 ítems</strong>, no todo el catálogo.
            Para <strong class="text-white">buscar cualquier canal</strong> (título, URL stream, categoría, carpeta…) andá a
            <a href="{{ route('admin.contents.index') }}" class="font-bold text-cyan-200 underline hover:text-white">Administración → Contenido</a>
            y usá el campo de búsqueda arriba de la tabla (también podés filtrar por tipo: VOD / En vivo / Series).
            Para <strong class="text-white">borrar toda una lista IPTV remota</strong>, bajá en esta página hasta el recuadro rojo «Eliminar contenido por URL remota» y escribí <code class="rounded bg-black/35 px-1 font-mono">BORRAR</code>.
        </p>
    </div>

    <div class="mb-6 hidden rounded-lg border border-sky-200/40 bg-sky-950/30 p-4 text-sm text-sky-50" aria-hidden="true">
        <p class="font-medium text-white">Qué muestra esta pantalla</p>
        <p class="mt-2 text-sky-100/95">
            Al importar un <strong class="text-white">.m3u</strong>, cada entrada se guarda como un ítem con <strong class="text-white">URL https o http</strong>.
            Listas tipo IPTV (<strong>#EXTINF:-1, …nombre canal…</strong> seguido de líneas tipo <code class="rounded bg-black/40 px-1 font-mono">…:8000/play/xxxx/index.m3u8</code>) están contempladas: en Importar M3U podés dejar marcado «Comprobar canales antes de registrar» para no crear líneas muertas.
            Por eso puede aparecer mucho contenido tipo <em>en vivo</em> aunque solo hayas pegado texto: la categoría elegida define si el tipo es live, vod o series.
            Los contenidos desde <strong class="text-white">RaiDrive / biblioteca local</strong> usan URLs con prefijo <code class="rounded bg-black/40 px-1 font-mono">local:</code> y <strong class="text-white">no</strong> entran en estos barridos.
        </p>
        <p class="mt-2 text-amber-100/95">
            <strong class="text-amber-200">Importante:</strong> “Borrar URL remotas” elimina todos los ítems cuya URL empiece por http(s). Si cargaste URLs a mano en «Nuevo contenido», también se eliminarán.
        </p>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-white/20 bg-white px-4 py-3 text-slate-900 shadow-lg">
            <p class="text-xs font-medium text-slate-600">Ítems con URL remota (http/https)</p>
            <p class="text-2xl font-semibold">{{ $remoteTotal }}</p>
        </div>
        @foreach ([
            'vod' => ['label' => 'VOD', 'class' => 'text-indigo-700 ring-indigo-300/40'],
            'live' => ['label' => 'En vivo', 'class' => 'text-emerald-700 ring-emerald-300/40'],
            'series' => ['label' => 'Series', 'class' => 'text-violet-700 ring-violet-300/40'],
        ] as $typeKey => $meta)
            <div class="rounded-xl border border-white/20 bg-white px-4 py-3 shadow-lg ring-1 {{ $meta['class'] }}">
                <p class="text-xs font-medium opacity-90">{{ $meta['label'] }}</p>
                <p class="text-2xl font-semibold">{{ $remoteByType[$typeKey] ?? 0 }}</p>
            </div>
        @endforeach
    </div>

    @if ($remoteTotal > 0)
        <div
            x-data="m3uChannelScanPanel()"
            data-scan-url="{{ route('admin.m3u.scan-channels-sync') }}"
            data-delete-url="{{ route('admin.m3u.delete-scanned-unreachable') }}"
            class="admin-card mb-8 max-w-6xl border-2 border-sky-400 bg-sky-50/80 text-slate-900 shadow-lg dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-50"
        >
            <h2 class="text-lg font-bold text-sky-950 dark:text-sky-100">📡 Escanear canales (resultado en esta misma página)</h2>
            <p class="mt-2 max-w-3xl text-sm text-sky-950/90 dark:text-sky-100/90">
                Este escaneo <strong class="text-sky-950 dark:text-white">no usa cola de trabajos</strong>: el servidor prueba cada URL http(s) y devuelve un informe JSON.
                Podés ver <strong class="text-sky-950 dark:text-white">cuántos responden y cuántos no</strong>, una muestra de los que sí van, y la tabla de caídos con casillas para borrarlos.
                Listas muy grandes pueden tardar varios minutos; si el navegador corta, subí <code class="rounded bg-black/10 px-1 font-mono text-xs dark:bg-white/10">max_execution_time</code> y el timeout de nginx/Apache.
            </p>

            <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                <div class="min-w-[min(100%,18rem)]">
                    <label class="admin-label">Qué escanear</label>
                    <select x-model="filterMode" class="admin-select text-slate-900 dark:text-white">
                        <option value="all">Todo catálogo remoto ({{ $remoteTotal }} filas)</option>
                        <option value="live">Solo TV en vivo ({{ $remoteByType['live'] ?? 0 }})</option>
                        <option value="vod">Solo VOD ({{ $remoteByType['vod'] ?? 0 }})</option>
                        <option value="series">Solo series ({{ $remoteByType['series'] ?? 0 }})</option>
                    </select>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border-2 border-sky-700 bg-sky-700 px-6 py-3 text-sm font-bold text-white shadow hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="scanning"
                    @click="runScan()"
                >
                    <svg x-show="scanning" class="h-5 w-5 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    <span x-text="scanning ? 'Escaneando… esperá' : '🔎 Escanear ahora'"></span>
                </button>
            </div>
            <p class="mt-2 text-xs text-sky-900/75 dark:text-sky-100/70">
                Los números entre paréntesis en el menú son los <strong class="text-sky-950 dark:text-white">ítems que ya tenés guardados</strong> en la base, no un tope del escáner. El escaneo revisa <strong class="text-sky-950 dark:text-white">todas</strong> las filas del ámbito elegido; los topes de tabla son configurables (hasta miles).
            </p>

            <div x-show="errorMsg" x-cloak class="mt-4 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/50 dark:text-red-200" x-text="errorMsg"></div>
            <div x-show="successMsg" x-cloak class="mt-4 rounded-lg border border-emerald-400 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100" x-text="successMsg"></div>

            <div x-show="report" x-cloak class="mt-6 space-y-6">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg border border-white/40 bg-white/90 px-3 py-2 text-sm text-slate-800 dark:border-white/10 dark:bg-slate-900/80 dark:text-slate-100">
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Filas revisadas</p>
                        <p class="text-2xl font-bold tabular-nums" x-text="report?.rows_scanned ?? '—'"></p>
                    </div>
                    <div class="rounded-lg border border-emerald-300/80 bg-emerald-50/90 px-3 py-2 text-sm text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-50">
                        <p class="text-xs font-medium opacity-80">Responden (OK)</p>
                        <p class="text-2xl font-bold tabular-nums text-emerald-800 dark:text-emerald-200" x-text="report?.rows_reachable ?? '—'"></p>
                    </div>
                    <div class="rounded-lg border border-amber-400/90 bg-amber-50/95 px-3 py-2 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/45 dark:text-amber-100">
                        <p class="text-xs font-medium opacity-85">No responden / caídos</p>
                        <p class="text-2xl font-bold tabular-nums text-amber-900 dark:text-amber-200" x-text="report?.rows_unreachable ?? '—'"></p>
                    </div>
                    <div class="rounded-lg border border-slate-300/80 bg-white/90 px-3 py-2 text-sm text-slate-800 dark:border-white/10 dark:bg-slate-900/80 dark:text-slate-100">
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Tiempo</p>
                        <p class="text-2xl font-bold tabular-nums"><span x-text="report?.seconds_elapsed ?? '—'"></span><span class="text-sm font-semibold"> s</span></p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 dark:text-slate-400" x-show="report?.dead_list_truncated">
                    La tabla lista como máximo {{ (int) config('m3u.scan_max_dead_listed') }} caídos por respuesta (config <code class="font-mono">M3U_SCAN_MAX_DEAD_LISTED</code>).
                    Si hay más, borrá este lote y volvé a escanear, o usá el barrido en segundo plano más abajo.
                </p>

                <div class="flex flex-wrap gap-2" x-show="(report?.dead?.length ?? 0) > 0">
                    <button type="button" class="admin-btn-secondary text-sm" @click="selectAllDeadListed()">Marcar todos en tabla</button>
                    <button type="button" class="admin-btn-secondary text-sm" @click="clearSelection()">Desmarcar</button>
                    <button
                        type="button"
                        class="rounded-xl border-2 border-red-700 bg-red-700 px-4 py-2 text-sm font-bold text-white shadow hover:bg-red-800 disabled:opacity-50"
                        :disabled="deleting || selectedCount() === 0"
                        @click="deleteSelected()"
                    >
                        <span x-text="deleting ? 'Borrando…' : ('🗑️ Eliminar marcados (' + selectedCount() + ')')"></span>
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl border border-emerald-400/50 bg-white/95 dark:border-emerald-800/50 dark:bg-slate-900/90" x-show="(report?.alive_sample?.length ?? 0) > 0">
                    <p class="border-b border-emerald-200/80 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                        Muestra de canales que <strong>sí respondieron</strong> (máx. {{ (int) config('m3u.scan_alive_sample_size') }} · <code class="font-mono">M3U_SCAN_ALIVE_SAMPLE_SIZE</code>)
                    </p>
                    <div class="max-h-48 overflow-auto">
                        <table class="min-w-full text-left text-xs text-slate-900 dark:text-slate-100">
                            <thead class="sticky top-0 bg-slate-100 dark:bg-slate-800">
                                <tr>
                                    <th class="px-2 py-2 font-semibold">ID</th>
                                    <th class="px-2 py-2 font-semibold">Título</th>
                                    <th class="px-2 py-2 font-semibold">Tipo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in (report?.alive_sample || [])" :key="'a'+row.id">
                                    <tr class="border-t border-slate-200 dark:border-slate-700">
                                        <td class="whitespace-nowrap px-2 py-1.5 font-mono" x-text="row.id"></td>
                                        <td class="max-w-[14rem] px-2 py-1.5" x-text="row.title"></td>
                                        <td class="px-2 py-1.5" x-text="row.type === 'live' ? 'En vivo' : (row.type === 'series' ? 'Series' : 'VOD')"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-amber-500/60 bg-white/95 dark:border-amber-800/60 dark:bg-slate-900/90" x-show="(report?.dead?.length ?? 0) > 0">
                    <p class="border-b border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                        Canales que <strong>no respondieron</strong> (marcá y eliminá)
                    </p>
                    <div class="max-h-[28rem] overflow-auto">
                        <table class="min-w-full text-left text-xs text-slate-900 dark:text-slate-100">
                            <thead class="sticky top-0 bg-slate-100 dark:bg-slate-800">
                                <tr>
                                    <th class="w-10 px-2 py-2 font-semibold"> </th>
                                    <th class="px-2 py-2 font-semibold">ID</th>
                                    <th class="px-2 py-2 font-semibold">Título</th>
                                    <th class="px-2 py-2 font-semibold">Tipo</th>
                                    <th class="px-2 py-2 font-semibold">URL stream</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in (report?.dead || [])" :key="'d'+row.id">
                                    <tr class="border-t border-slate-200 dark:border-slate-700">
                                        <td class="px-2 py-1.5 align-top">
                                            <input type="checkbox" class="h-4 w-4 rounded border-slate-400" :checked="isSelected(row.id)" @click.prevent="toggleSelect(row.id)">
                                        </td>
                                        <td class="whitespace-nowrap px-2 py-1.5 font-mono align-top" x-text="row.id"></td>
                                        <td class="max-w-[10rem] px-2 py-1.5 align-top" x-text="row.title"></td>
                                        <td class="whitespace-nowrap px-2 py-1.5 align-top" x-text="row.type === 'live' ? 'En vivo' : (row.type === 'series' ? 'Series' : 'VOD')"></td>
                                        <td class="max-w-[min(32rem,70vw)] break-all px-2 py-1.5 font-mono align-top text-[11px]" x-text="row.stream_url"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 dark:border-slate-600 dark:text-slate-400" x-show="report && (report?.rows_unreachable ?? 0) === 0">
                    No se detectaron URLs caídas en el ámbito elegido. Todo lo revisado respondió según la comprobación del servidor.
                </p>
            </div>
        </div>
    @endif

    <div
        x-data="m3uCullPanel()"
        data-status-url="{{ route('admin.m3u.cull-status') }}"
        data-async-url="{{ route('admin.m3u.cull-async') }}"
        class="admin-card mb-8 max-w-2xl border-2 border-emerald-300 bg-emerald-50/60 text-slate-900 shadow-lg dark:border-emerald-900 dark:bg-emerald-950/35 dark:text-white"
    >
        <h2 class="text-lg font-bold text-emerald-950 dark:text-emerald-100">🧹 Barrido automático: quitar canales caídos</h2>
        <p class="mt-2 text-sm text-emerald-950/90 dark:text-emerald-100/90">
            Podés <strong class="text-emerald-900 dark:text-white">simular</strong> para ver una muestra de canales caídos (no borra nada) o <strong class="text-emerald-900 dark:text-white">barrer de verdad</strong> y quitar del catálogo las URLs que no respondan.
            Todo corre <strong>en segundo plano</strong> — podés cerrar esta pestaña y volver después.
            <br><span class="mt-1 block text-xs text-emerald-800/85 dark:text-emerald-200/75">Las tarjetas <strong class="text-emerald-950 dark:text-white">VOD / En vivo / Series</strong> de arriba solo muestran totales: <strong class="text-emerald-950 dark:text-white">no activan el barrido</strong>. Para limpiar solo TV en vivo (o solo VOD), elegí <strong class="text-emerald-950 dark:text-white">«Solo por tipo de contenido»</strong> en el ámbito de abajo.</span>
            <br><span class="mt-1 block text-xs text-emerald-800/70 dark:text-emerald-200/60">El schedule automático solo ejecuta el barrido con borrado (configurable con <code class="font-mono">CULL_DEAD_STREAMS_SCHEDULE</code> en .env).</span>
        </p>

        @if ($remoteTotal === 0)
            <p class="mt-3 rounded-lg border border-dashed border-emerald-400 p-3 text-xs text-emerald-900 dark:border-emerald-700 dark:text-emerald-100/85">Aún no hay ítems con URL http(s) en el catálogo.</p>
        @else
            {{-- Panel de estado del último barrido --}}
            <div x-show="status !== null" class="mt-4 rounded-xl border px-4 py-3 text-sm"
                :class="status?.running ? 'border-amber-400 bg-amber-50 dark:bg-amber-950/40' : (status?.error ? 'border-red-400 bg-red-50 dark:bg-red-950/40' : 'border-emerald-400 bg-emerald-50 dark:bg-emerald-950/40')">

                <div x-show="status?.running" class="flex flex-wrap items-center gap-3">
                    <svg class="h-5 w-5 shrink-0 animate-spin text-amber-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    <span class="font-medium text-amber-800 dark:text-amber-200">
                        <span x-text="status?.dry_run ? 'Simulación en curso… (no se borra nada)' : 'Barrido en curso… escaneando canales'"></span>
                    </span>
                </div>

                <div x-show="!status?.running && status?.result">
                    <p class="font-semibold text-emerald-800 dark:text-emerald-200">
                        <span x-text="status?.result?.dry_run ? '✅ Simulación completada (sin borrar)' : '✅ Último barrido completado'"></span>
                    </p>
                    <ul class="mt-2 space-y-1 text-xs text-slate-700 dark:text-slate-300">
                        <li x-show="status?.content_type">🎯 Tipo acotado: <strong x-text="status.content_type === 'live' ? 'TV en vivo' : (status.content_type === 'series' ? 'Series' : (status.content_type === 'vod' ? 'VOD' : status.content_type))"></strong></li>
                        <li>📡 Filas revisadas: <strong x-text="status?.result?.rows_scanned ?? '—'"></strong></li>
                        <li><span x-text="status?.result?.dry_run ? '⚠️ Darían de baja (caídos / no responden):' : '🗑️ Eliminados del catálogo:'"></span> <strong x-text="status?.result?.removed ?? '—'"></strong></li>
                        <li>🔗 URLs únicas que fallan (aprox.): <strong x-text="status?.result?.distinct_unreachable_urls ?? '—'"></strong></li>
                        <li>⏱️ Terminó: <strong x-text="status?.finished_at ? new Date(status.finished_at).toLocaleString('es-CO') : '—'"></strong></li>
                        <li>🔧 Disparado por: <strong x-text="status?.triggered_by === 'schedule' ? 'Tarea automática' : 'Admin manual'"></strong></li>
                    </ul>

                    <div
                        class="mt-4 overflow-hidden rounded-lg border border-amber-400/50 bg-white/95 text-slate-900 dark:border-amber-700/50 dark:bg-slate-900/95 dark:text-slate-100"
                        x-show="status?.result?.dry_run && (status?.result?.dead_samples?.length > 0)"
                        x-cloak
                    >
                        <p class="border-b border-amber-200/80 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">Muestra de canales caídos (máx. {{ (int) config('m3u.dry_run_dead_sample_limit') }} en pantalla · <code class="font-mono">M3U_DRY_RUN_DEAD_SAMPLE_LIMIT</code>)</p>
                        <div class="max-h-72 overflow-auto">
                            <table class="min-w-full text-left text-xs">
                                <thead class="sticky top-0 bg-slate-100 dark:bg-slate-800">
                                    <tr>
                                        <th class="px-2 py-2 font-semibold">ID</th>
                                        <th class="px-2 py-2 font-semibold">Título</th>
                                        <th class="px-2 py-2 font-semibold">URL stream</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="row in (status?.result?.dead_samples || [])" :key="row.id">
                                        <tr class="border-t border-slate-200 dark:border-slate-700">
                                            <td class="whitespace-nowrap px-2 py-1.5 font-mono" x-text="row.id"></td>
                                            <td class="max-w-[12rem] px-2 py-1.5" x-text="row.title"></td>
                                            <td class="max-w-[min(28rem,55vw)] break-all px-2 py-1.5 font-mono text-[11px]" x-text="row.stream_url"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <p class="border-t border-slate-200 px-3 py-2 text-[11px] text-slate-600 dark:border-slate-700 dark:text-slate-400" x-show="(status?.result?.removed || 0) > (status?.result?.dead_samples?.length || 0)">
                            … y <strong x-text="(status?.result?.removed || 0) - (status?.result?.dead_samples?.length || 0)"></strong> fila(s) más caídas no listadas. Para revisar o cambiar carátulas: <a href="{{ route('admin.contents.index') }}" class="font-semibold text-indigo-600 underline dark:text-indigo-300">Administración → Contenido</a> (filtrá por TV en vivo o buscá por título/URL).
                        </p>
                    </div>

                    <p class="mt-3 text-xs text-slate-600 dark:text-slate-400" x-show="status?.result?.dry_run && (status?.result?.removed === 0)">
                        No se detectaron URLs remotas caídas en el ámbito elegido.
                    </p>
                </div>

                <div x-show="!status?.running && status?.error">
                    <p class="font-semibold text-red-700 dark:text-red-300">❌ El barrido falló</p>
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400" x-text="status?.error"></p>
                </div>
            </div>

            {{-- Formulario async --}}
            <div x-show="!status?.running" class="mt-5 space-y-4">
                <fieldset class="space-y-2">
                    <legend class="text-sm font-semibold">Ámbito del barrido</legend>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" name="cull_scope" value="all" class="h-4 w-4" x-model="cullScope">
                        <span>Todos los canales remotos (http/https)</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" name="cull_scope" value="type" class="h-4 w-4" x-model="cullScope">
                        <span>Solo por tipo de contenido (VOD, TV en vivo o series)</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" name="cull_scope" value="category" class="h-4 w-4" x-model="cullScope">
                        <span>Solo una categoría (carpeta) y sus subcarpetas</span>
                    </label>
                </fieldset>

                <div x-show="cullScope === 'type'" class="space-y-1">
                    <label class="admin-label">Tipo a barrer</label>
                    <select x-model="cullType" class="admin-select text-slate-900 dark:text-white">
                        <option value="vod">VOD (películas)</option>
                        <option value="live">TV en vivo / canales</option>
                        <option value="series">Series</option>
                    </select>
                </div>

                <div x-show="cullScope === 'category'">
                    <label class="admin-label">Categoría</label>
                    <select x-model="cullCategoryId" class="admin-select text-slate-900 dark:text-white">
                        <option value="">— Elegir —</option>
                        @foreach ($categoryOptions as $opt)
                            <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="errorMsg" class="rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300" x-text="errorMsg"></div>

                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    <button
                        type="button"
                        @click="launch(true)"
                        class="rounded-xl border-2 border-amber-600 bg-amber-600 px-5 py-3 text-sm font-bold text-white shadow hover:bg-amber-700"
                    >
                        🔍 Ver caídos (simulación, no borra)
                    </button>
                    <button
                        type="button"
                        @click="launch(false)"
                        class="rounded-xl border-2 border-emerald-700 bg-emerald-700 px-5 py-3 text-sm font-bold text-white shadow hover:bg-emerald-800"
                    >
                        🚀 Quitar caídos del catálogo (borra filas)
                    </button>
                </div>
            </div>

            <div x-show="status?.running" class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                Esta página se actualiza automáticamente cada 8 segundos. Puedes cerrarla; el barrido seguirá corriendo.
            </div>
        @endif
    </div>

    @if ($remoteTotal === 0)
        <div class="admin-card mb-8 max-w-3xl text-slate-800">
            <p class="font-medium text-slate-900">No hay contenido registrado por URL remota.</p>
            <p class="mt-2 text-sm text-slate-600">Si seguís viendo canales en el cliente, revisá que no vengan de la biblioteca local (RaiDrive) o de otra categoría; en <a href="{{ route('admin.contents.index') }}" class="text-indigo-600 underline">Contenido</a> podés filtrar por tipo y borrar fila a fila.</p>
        </div>
    @else
        <div class="mb-8 overflow-hidden rounded-2xl border border-white/20 bg-white text-slate-900 shadow-xl ring-1 ring-white/10">
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-3">
                <p class="text-sm font-semibold text-slate-800">Últimos {{ $recentRemote->count() }} con URL remota (muestra)</p>
                <p class="mt-1 text-xs text-slate-600">¿Buscás uno concreto? <a href="{{ route('admin.contents.index', ['type' => 'live']) }}" class="font-semibold text-indigo-700 underline hover:text-indigo-900">Ir a Contenido — buscar</a> (aquí no hay buscador).</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-gradient-to-r from-slate-100 to-blue-50/80">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Título</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Categoría</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">URL (recorte)</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($recentRemote as $c)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-medium">{{ $c->title }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $c->category?->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $c->type->value }}</td>
                                <td class="max-w-[14rem] truncate px-4 py-3 font-mono text-xs text-slate-600" title="{{ $c->stream_url }}">{{ Str::limit($c->stream_url, 56) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end">
                                    <a href="{{ route('admin.contents.edit', $c) }}" class="text-indigo-600 underline">Editar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div
            x-data="{ scope: '{{ old('scope', 'all') }}' }"
            class="admin-card max-w-2xl border-2 border-red-200 bg-red-50/40 text-slate-900 shadow-lg dark:border-red-900/70 dark:bg-red-950/30 dark:text-white"
        >
            <h2 class="text-lg font-bold text-red-900 dark:text-red-100">Eliminar contenido por URL remota</h2>
            <p class="mt-2 text-sm text-red-900/85 dark:text-red-100/90">
                Esta acción no se puede deshacer. Escribí <strong class="font-mono text-white">BORRAR</strong> y elegí el alcance.
            </p>

            <form
                method="POST"
                action="{{ route('admin.m3u.purge-remote') }}"
                class="mt-5 space-y-4"
                onsubmit="return confirm('¿Seguro? Se eliminarán permanentemente los ítems con URL http(s) según el alcance elegido.');"
            >
                @csrf
                <fieldset class="space-y-2">
                    <legend class="text-sm font-semibold">Alcance</legend>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" name="scope" value="all" class="h-4 w-4" x-model="scope">
                        <span>Todas las URLs remotas del catálogo</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" name="scope" value="category" class="h-4 w-4" x-model="scope">
                        <span>Solo una rama de categorías (la elegida y sus subcarpetas)</span>
                    </label>
                </fieldset>

                <div>
                    <label class="admin-label" for="purge_category_id">Categoría (si elegiste “Solo una rama…”) </label>
                    <select
                        id="purge_category_id"
                        name="category_id"
                        class="admin-select"
                        :disabled="scope !== 'category'"
                        :required="scope === 'category'"
                    >
                        <option value="">— Elegir categoría —</option>
                        @foreach ($categoryOptions as $opt)
                            <option value="{{ $opt['id'] }}" @selected(old('category_id') == $opt['id'])>{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
                </div>

                <div>
                    <label class="admin-label" for="purge_confirm">Confirmación</label>
                    <input
                        id="purge_confirm"
                        type="text"
                        name="purge_confirm"
                        value="{{ old('purge_confirm') }}"
                        autocomplete="off"
                        class="admin-input-on-royal font-mono"
                        placeholder="BORRAR"
                        required
                    >
                    <x-input-error :messages="$errors->get('purge_confirm')" class="mt-1" />
                </div>

                <x-input-error :messages="$errors->get('scope')" class="mt-1" />

                <button type="submit" class="rounded-xl border-2 border-red-600 bg-red-600 px-5 py-3 text-sm font-bold text-white shadow hover:bg-red-700">
                    Eliminar definitivamente
                </button>
            </form>
        </div>
    @endif
</x-panel-layout>
