<x-panel-layout title="Biblioteca local">
    @if (session('success'))
        <div class="mb-6 rounded-xl border border-emerald-400/50 bg-emerald-500/15 px-4 py-3 text-center text-sm font-medium text-emerald-100">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-8 flex flex-wrap justify-center gap-3">
        <a href="{{ route('admin.library.folders.index') }}" class="admin-btn-ghost-light !py-3 !text-sm">Carpetas del catálogo</a>
        <a href="{{ route('admin.library.folder-posters.index') }}" class="admin-btn-ghost-light !py-3 !text-sm">Carátulas de carpetas</a>
        <a href="{{ route('admin.contents.index') }}" class="admin-btn-ghost-light !py-3 !text-sm">Contenido</a>
        <a href="{{ route('admin.categories.index') }}" class="admin-btn-ghost-light !py-3 !text-sm">Categorías</a>
    </div>

    @php
        $diskSourceLabel = match ($localLibraryRootsBackend ?? 'none') {
            'rclone' => 'rclone mount',
            'raidrive' => 'RaiDrive',
            default => 'sin configurar',
        };
    @endphp

    <details class="mb-6 rounded-2xl border border-emerald-200/90 bg-emerald-50/95 p-5 text-sm text-emerald-950 shadow-md dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-50">
        <summary class="cursor-pointer text-base font-bold text-emerald-900 dark:text-emerald-100">VPS / Linux: nube con rclone mount</summary>
        <div class="mt-3 space-y-3 leading-relaxed text-emerald-950/95 dark:text-emerald-100/95">
            <p>En <strong>Windows</strong> podés usar <strong>RaiDrive</strong> y variables <code class="rounded bg-white/60 px-1 font-mono text-xs dark:bg-black/35">RAIDRIVE_*</code>. En un <strong>VPS</strong> lo habitual es <strong>rclone mount</strong> y variables dedicadas <code class="rounded bg-white/60 px-1 font-mono text-xs dark:bg-black/35">RCLONE_MOUNT_*</code> (recomendado: <code class="font-mono text-xs">LOCAL_LIBRARY_DRIVER=rclone</code>). La app solo lee <strong>carpetas reales</strong> en disco.</p>
            <ol class="list-decimal space-y-2 pl-5">
                <li>Instalá rclone en el VPS y creá el remoto: <code class="rounded bg-white/60 px-1 font-mono text-xs dark:bg-black/35">rclone config</code> (Google Drive, Dropbox, S3, WebDAV del proveedor, etc.).</li>
                <li>Creá un directorio vacío, ej. <code class="rounded bg-white/60 px-1 font-mono text-xs dark:bg-black/35">sudo mkdir -p /var/www/cloud-media &amp;&amp; sudo chown www-data:www-data /var/www/cloud-media</code></li>
                <li>Montá el remoto (ejemplo; ajustá <code class="font-mono text-xs">miRemote:Peliculas</code>):<br>
                    <code class="mt-1 block whitespace-pre-wrap break-all rounded bg-white/70 p-2 font-mono text-[11px] dark:bg-black/40">rclone mount miRemote:Peliculas /var/www/cloud-media --vfs-cache-mode full --dir-cache-time 12h --poll-interval 1m --uid $(id -u www-data) --gid $(id -g www-data) --allow-other --log-file /var/log/rclone-media.log</code>
                    <span class="mt-1 block text-xs">En muchos VPS hace falta <code class="font-mono">user_allow_other</code> en <code class="font-mono">/etc/fuse.conf</code> y <code class="font-mono">--allow-other</code> para que Apache/PHP lean el mount. Si no podés usar FUSE, usá la alternativa de abajo.</span>
                </li>
                <li>En <code class="font-mono">.env</code>: <code class="rounded bg-white/60 px-1 font-mono text-xs dark:bg-black/35">LOCAL_LIBRARY_DRIVER=rclone</code> y <code class="rounded bg-white/60 px-1 font-mono text-xs dark:bg-black/35">RCLONE_MOUNT_PATH=/var/www/cloud-media</code> (varias rutas: <code class="font-mono">RCLONE_MOUNT_PATHS</code>). Si migrás desde una config vieja, <code class="font-mono">auto</code> en Linux sigue aceptando <code class="font-mono">RAIDRIVE_LOCAL_PATH</code> como respaldo.</li>
                <li><code class="rounded bg-white/60 px-1 font-mono text-xs dark:bg-black/35">php artisan config:clear</code> · Diagnóstico: <code class="rounded bg-white/60 px-1 font-mono text-xs dark:bg-black/35">php artisan media:rclone-check</code> · Espacio en la nube: <code class="rounded bg-white/60 px-1 font-mono text-xs dark:bg-black/35">php artisan media:rclone-check --remote=miRemote:</code></li>
            </ol>
            <p class="rounded-lg border border-emerald-300/60 bg-white/50 p-3 text-xs dark:border-emerald-800 dark:bg-black/25"><strong>systemd:</strong> mantené el mount con una unit <code class="font-mono">rclone-media.service</code> para que sobreviva a reinicios del VPS.</p>
            <p class="text-xs"><strong>Sin FUSE:</strong> <code class="font-mono">rclone serve http miRemote:Peliculas --addr 127.0.0.1:5572</code> y cargá películas como URL <code class="font-mono">http://127.0.0.1:5572/…</code> en <strong>Contenido</strong> (no usa <code class="font-mono">local:</code>). Opcional: <code class="font-mono">RCLONE_BASE_URL</code> en <code class="font-mono">.env</code> para validar el prefijo de esas URLs.</p>
        </div>
    </details>

    @if (! $configured)
        {{-- Fondo blanco + texto oscuro forzado: el main del panel usa texto claro y antes heredaba y anulaba el contraste. --}}
        <div class="raidrive-setup-card mx-auto max-w-2xl space-y-5 rounded-2xl border-2 border-slate-300 bg-white p-8 shadow-2xl sm:p-10">
            <h2 class="text-center text-xl font-bold text-slate-900 sm:text-left">Configurá la biblioteca local</h2>
            <p class="text-center text-base leading-relaxed text-slate-800 sm:text-left">
                Elegí en <code class="raidrive-code-inline">.env</code> el modo con <code class="raidrive-code-inline">LOCAL_LIBRARY_DRIVER</code> (<strong class="text-slate-950">auto</strong>, <strong class="text-slate-950">raidrive</strong> o <strong class="text-slate-950">rclone</strong>). La app no usa API de nube: <strong class="text-slate-950">solo lee rutas de disco</strong> que PHP pueda abrir.
            </p>
            <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800">
                Driver en .env: <code class="font-mono text-xs">{{ $localLibraryDriverOption ?? 'auto' }}</code>
                @if (($localLibraryDriverOption ?? 'auto') === 'auto')
                    <span class="block mt-1 text-xs text-slate-600">En Windows se intenta primero <code class="font-mono">RAIDRIVE_*</code>; en Linux primero <code class="font-mono">RCLONE_MOUNT_*</code>.</span>
                @endif
            </p>
            <ol class="mt-4 list-decimal space-y-4 pl-5 text-base text-slate-800 sm:pl-6">
                <li class="pl-1">
                    <strong class="text-slate-950">Windows + RaiDrive:</strong> <code class="raidrive-code-inline">LOCAL_LIBRARY_DRIVER=raidrive</code> (o <code class="raidrive-code-inline">auto</code>) y una o varias rutas:
                    <code class="raidrive-code-block mt-2 whitespace-pre-wrap break-all">RAIDRIVE_LOCAL_PATH=R:\Peliculas</code>
                    <span class="mt-2 block text-sm text-slate-700">o varias letras/unidades:</span>
                    <code class="raidrive-code-block mt-2 whitespace-pre-wrap break-all">RAIDRIVE_LOCAL_PATHS="R:\,S:\,T:\,U:\"</code>
                    <span class="mt-2 block text-xs text-slate-600">Si <code class="raidrive-code-inline">RAIDRIVE_LOCAL_PATHS</code> no está vacío, se ignora <code class="raidrive-code-inline">RAIDRIVE_LOCAL_PATH</code>. Con varias raíces elegís unidad (#0, #1, …) y luego carpetas.</span>
                </li>
                <li class="pl-1">
                    <strong class="text-slate-950">VPS + rclone mount:</strong> <code class="raidrive-code-inline">LOCAL_LIBRARY_DRIVER=rclone</code> y el punto de montaje:
                    <code class="raidrive-code-block mt-2 whitespace-pre-wrap break-all">RCLONE_MOUNT_PATH=/var/www/cloud-media</code>
                    <span class="mt-2 block text-xs text-slate-600">Varias carpetas montadas: <code class="font-mono">RCLONE_MOUNT_PATHS</code> (misma regla de comas que RaiDrive).</span>
                </li>
                <li class="pl-1">
                    Apache/Laragon y RaiDrive deben estar en el <strong class="text-slate-950">mismo PC</strong> en desarrollo Windows. Si el play falla, comprobá permisos del servicio web sobre la unidad.
                </li>
                <li class="pl-1">
                    Tras guardar <code class="raidrive-code-inline">.env</code>, ejecutá <code class="raidrive-code-inline">php artisan config:clear</code>.
                </li>
                <li class="pl-1">
                    La reproducción <code class="raidrive-code-inline">local:</code> es <strong class="text-slate-950">archivo directo</strong> (rangos HTTP). El tráfico pasa por tu PHP/servidor salvo que uses solo URLs remotas en el contenido.
                </li>
            </ol>
        </div>
    @else
        <div class="mb-4 rounded-xl border border-slate-300/80 bg-white/90 px-4 py-2 text-center text-sm text-slate-800 shadow dark:border-slate-600 dark:bg-slate-900/80 dark:text-slate-200">
            Origen activo: <strong>{{ $diskSourceLabel }}</strong>
            <span class="text-slate-500">·</span>
            <span class="text-xs">driver <code class="rounded bg-slate-100 px-1 font-mono dark:bg-slate-800">{{ $localLibraryDriverOption ?? 'auto' }}</code></span>
        </div>
        <div class="mb-6 hidden rounded-2xl border border-indigo-200/80 bg-white p-5 text-sm leading-relaxed text-slate-800 shadow-md dark:border-indigo-500/40 dark:bg-slate-900 dark:text-slate-200" aria-hidden="true">
            <h3 class="mb-2 text-base font-semibold text-indigo-900 dark:text-indigo-200">Cómo se reproduce y qué es “rápido”</h3>
            <ul class="list-disc space-y-2 pl-5 marker:text-indigo-500">
                <li>
                    <strong>Contenido importado desde aquí (<code class="rounded bg-slate-100 px-1 font-mono text-xs dark:bg-slate-800">local:</code>)</strong>: el cliente pide el vídeo a <strong>tu sitio</strong> (<code class="rounded bg-slate-100 px-1 font-mono text-xs dark:bg-slate-800">/play/…</code> con token). Es reproducción <strong>progresiva directa del archivo</strong>. La velocidad depende de tu PC/servidor, del montaje (RaiDrive o rclone) y de la red hasta el usuario.
                </li>
                <li>
                    <strong>Listas M3U / URL <code class="rounded bg-slate-100 px-1 font-mono text-xs dark:bg-slate-800">https://…</code></strong>: el reproductor va al <strong>origen del enlace</strong> (más parecido a “play direct” al hosting del proveedor). Canales en vivo suelen ir así.
                </li>
                <li>
                    <strong>Catálogo</strong>: la app lista el contenido desde la base (paginado). Para que “cargue rápido” con muchas películas, conviene categorías claras, búsqueda y posters livianos; el cuello de botella no suele ser el disco montado sino la cantidad de datos en pantalla.
                </li>
                <li>
                    <strong>Que el play arranque antes</strong> en MP4: si podés, re-mux con <strong class="text-slate-950">moov al inicio</strong> (p. ej. <code class="rounded bg-slate-100 px-1 font-mono text-xs dark:bg-slate-800">ffmpeg -i entrada.mp4 -c copy -movflags +faststart salida.mp4</code>). Los HLS (<code class="rounded bg-slate-100 px-1 font-mono text-xs dark:bg-slate-800">.m3u8</code>) se comportan distinto: arrancan por segmentos.
                </li>
                <li>
                    <strong>Caché de listados (tipo rclone VFS)</strong>: el panel guarda unos segundos los nombres de carpetas/archivos y el recuento recursivo para no golpear el disco/nube en cada clic (por defecto {{ $raidriveBrowseCacheTtl }}s / {{ $raidriveDiskStatsCacheTtl }}s vía <code class="rounded bg-slate-100 px-1 font-mono text-xs dark:bg-slate-800">RAIDRIVE_BROWSE_CACHE_TTL</code> y <code class="rounded bg-slate-100 px-1 font-mono text-xs dark:bg-slate-800">RAIDRIVE_DISK_STATS_CACHE_TTL</code>). Usá <strong class="text-slate-950">Refrescar caché del disco</strong> si copiaste archivos y no aparecen.
                    <span class="mt-2 block text-slate-800">
                        <strong class="text-slate-950">Renombraste un .mkv/.mp4 en la misma carpeta</strong> y el play o la carátula fallan: pulsá <strong class="text-slate-950">Sincronizar renombres (catálogo)</strong> abajo (o <code class="rounded bg-slate-100 px-1 font-mono text-[11px] dark:bg-slate-800">php artisan content:sync-local-renames</code>). Solo actúa cuando en esa carpeta hay exactamente una fila con ruta vieja inválida y un único vídeo en disco que aún no corresponde a ningún título importado.
                    </span>
                </li>
                @if ($raidriveMultiRoot)
                    <li>
                        <strong>Varias raíces</strong>: la ruta en la barra usa el índice (ej. <code class="rounded bg-slate-100 px-1 font-mono text-xs dark:bg-slate-800">0/Peliculas</code> = raíz #0). El orden coincide con <code class="rounded bg-slate-100 px-1 font-mono text-xs dark:bg-slate-800">RAIDRIVE_LOCAL_PATHS</code> o <code class="rounded bg-slate-100 px-1 font-mono text-xs dark:bg-slate-800">RCLONE_MOUNT_PATHS</code> según el modo activo.
                    </li>
                @endif
            </ul>
        </div>

        <div class="mb-4 grid gap-3 rounded-2xl border-2 border-slate-200 bg-white p-5 text-sm text-slate-900 shadow-lg sm:grid-cols-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-600">Vídeos en disco ({{ $diskSourceLabel }})</p>
                    @if ($diskVideoStats === null)
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-500">—</p>
                        <p class="mt-1 text-xs leading-snug text-slate-600">
                            Recuento recursivo <strong class="font-medium text-slate-700">desactivado</strong> al cargar esta pantalla (montajes lentos pueden colgar PHP varios minutos).
                            Para activarlo poné <code class="rounded bg-slate-100 px-1 font-mono text-[11px] dark:bg-slate-800">RAIDRIVE_INDEX_DISK_STATS=true</code> en <code class="rounded bg-slate-100 px-1 font-mono text-[11px] dark:bg-slate-800">.env</code> y <code class="rounded bg-slate-100 px-1 font-mono text-[11px] dark:bg-slate-800">php artisan config:clear</code>.
                        </p>
                    @else
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-indigo-700">{{ number_format($diskVideoStats['count']) }}</p>
                        @if ($diskVideoStats['capped'] || $diskVideoStats['timed_out'])
                            <p class="mt-1 text-xs leading-snug text-slate-600">Recuento parcial (tope de archivos o tiempo de escaneo). Sirve para dimensionar; el listado por carpetas sigue siendo la fuente exacta en cada nivel.</p>
                        @endif
                    @endif
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-600">Ya importados a la web (bajo estas rutas)</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-indigo-700">{{ number_format($dbImportedUnderRaidriveRoot) }}</p>
                    <p class="mt-1 text-xs leading-snug text-slate-600">Filas en la base cuyo archivo local sigue existiendo dentro de alguna de estas rutas:</p>
                    <ul class="mt-1 max-h-24 list-disc space-y-0.5 overflow-y-auto pl-4 text-[11px] leading-snug text-slate-600">
                        @foreach ($raidriveRoots as $rp)
                            <li><code class="break-all">{{ $rp }}</code></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-600">Todos los streams <code class="raidrive-code-inline text-xs">local:</code> en la base</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-indigo-700">{{ number_format($dbLocalStreamsTotal) }}</p>
                    <p class="mt-1 text-xs leading-snug text-slate-600">Incluye subidas al servidor y otras rutas permitidas, no solo esta biblioteca.</p>
                </div>
        </div>

        <div class="admin-panel-hint mb-6 flex flex-wrap items-center justify-center gap-3 text-sm sm:justify-start">
            <span class="font-semibold text-white">Ruta actual:</span>
            <code class="rounded-md bg-white/25 px-2 py-1 font-mono text-xs text-white ring-1 ring-white/30">{{ $path === '' ? ($raidriveMultiRoot ? '(elegí unidad)' : '(raíz)') : $path }}</code>
            @if ($parentPath !== null)
                <a href="{{ route('admin.library.raidrive', $parentPath === '' ? [] : ['path' => $parentPath]) }}" class="font-medium text-sky-200 underline decoration-sky-200/70 hover:text-white">↑ Subir un nivel</a>
            @endif
            <form method="POST" action="{{ route('admin.library.raidrive.refresh-cache') }}" class="inline-flex items-center gap-2">
                @csrf
                <input type="hidden" name="return_path" value="{{ $path }}">
                <button type="submit" class="rounded-lg border border-amber-300/60 bg-amber-500/20 px-3 py-1.5 text-xs font-semibold text-amber-100 transition hover:bg-amber-500/30">
                    Refrescar caché del disco
                </button>
            </form>
            @if ($configured)
                <form method="POST" action="{{ route('admin.library.raidrive.sync-renames') }}" class="inline-flex items-center gap-2" onsubmit="return confirm('Se revisarán todas las películas/series local: donde en la MISMA carpeta haya solo un archivo roto en catálogo y un único vídeo sin registrar (típico tras renombrar), se actualizará stream_url y título. ¿Continuar?');">
                    @csrf
                    <input type="hidden" name="return_path" value="{{ $path }}">
                    <button type="submit" class="rounded-lg border border-teal-300/55 bg-teal-500/20 px-3 py-1.5 text-xs font-semibold text-teal-50 transition hover:bg-teal-500/30">
                        Sincronizar renombres (catálogo)
                    </button>
                </form>
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 font-semibold text-gray-900 dark:text-white">Carpetas</h2>
                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Marcá las carpetas y usá <strong class="font-medium text-gray-700 dark:text-gray-300">«Importar carpetas marcadas»</strong> a la derecha (recursivo). El nombre sigue siendo un enlace para <strong class="font-medium text-gray-700 dark:text-gray-300">entrar</strong> y ver archivos sueltos.</p>
                @if (count($browse['dirs']) === 0)
                    <p class="text-sm text-gray-500">No hay subcarpetas aquí.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($browse['dirs'] as $d)
                            <li class="flex items-start gap-2 rounded-md py-0.5 hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                <input
                                    type="checkbox"
                                    name="folder_paths[]"
                                    value="{{ $d['path'] }}"
                                    form="raidrive-folders-recursive-form"
                                    class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800"
                                    title="Marcar para importar esta carpeta (recursivo)"
                                >
                                <a href="{{ route('admin.library.raidrive', ['path' => $d['path']]) }}" class="min-w-0 flex-1 text-indigo-600 hover:underline dark:text-indigo-400">📁 {{ $d['name'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 font-semibold text-gray-900 dark:text-white">Importar a la biblioteca web</h2>
                <p class="mb-3 text-xs text-gray-500">Marcá <strong class="font-medium text-gray-700 dark:text-gray-300">archivos</strong> de vídeo de este nivel para importar solo esos, o marcá <strong class="font-medium text-gray-700 dark:text-gray-300">carpetas</strong> a la izquierda y usá el bloque verde de abajo.</p>

                <form method="POST" action="{{ route('admin.library.raidrive.import') }}">
                    @csrf
                    <input type="hidden" name="return_path" value="{{ $path }}">

                    <div class="mb-3">
                        <label class="block text-sm font-medium">Categoría destino</label>
                        <select name="category_id" required class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800">
                            @foreach ($categoryOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if (count($browse['files']) === 0)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100">
                            @if (count($browse['dirs']) > 0)
                                <p class="font-medium">No hay vídeos en este nivel, solo subcarpetas.</p>
                                <p class="mt-2 text-xs leading-relaxed opacity-90">Podés <strong>marcar carpetas a la izquierda</strong> y pulsar <strong>«Importar carpetas marcadas»</strong> (abajo), o entrar con el enlace del nombre hasta ver archivos y marcar los checkboxes de cada .mp4 / .mkv.</p>
                            @else
                                <p>No hay vídeos ni subcarpetas aquí.</p>
                            @endif
                        </div>
                    @else
                        <div class="max-h-80 space-y-2 overflow-y-auto rounded-lg border border-gray-100 p-2 dark:border-gray-800">
                            @foreach ($browse['files'] as $f)
                                <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <input type="checkbox" name="files[]" value="{{ $f['absolute'] }}" class="rounded border-gray-300 dark:border-gray-600">
                                    <span class="truncate">{{ $f['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        <x-primary-button type="submit" class="mt-4">Importar seleccionados</x-primary-button>
                    @endif
                </form>

                @if (count($browse['dirs']) > 0)
                    <form
                        id="raidrive-folders-recursive-form"
                        method="POST"
                        action="{{ route('admin.library.raidrive.import-recursive-folders') }}"
                        class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50/90 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/40"
                        onsubmit="return confirm('Se importarán en recursivo las carpetas marcadas (tope total {{ number_format($importRecursiveMax) }} archivos). ¿Continuar?');"
                    >
                        @csrf
                        <input type="hidden" name="redirect_path" value="{{ $path }}">
                        <h3 class="mb-2 text-sm font-semibold text-emerald-950 dark:text-emerald-100">Importar carpetas marcadas</h3>
                        <p class="mb-3 text-xs text-emerald-900/90 dark:text-emerald-200/90">Las casillas están en la lista de la izquierda. Incluye subcarpetas; se omiten duplicados. Tope global: <strong>{{ number_format($importRecursiveMax) }}</strong> (<code class="font-mono">RAIDRIVE_IMPORT_RECURSIVE_MAX</code>).</p>
                        <p class="mb-3 rounded-lg border border-amber-200/70 bg-amber-50/90 px-2 py-2 text-[11px] text-amber-950 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-100/95">Si la página <strong>tarda mucho o parece trabada</strong>: es normal en carpetas grandes o disco de red lento — no cierres el navegador hasta que termine la redirección. PHP ya no corta por tiempo en esta petición; si ves <strong>502/504</strong>, subí <code class="font-mono">fastcgi_read_timeout</code> (y similares) en Nginx dentro del bloque PHP. En recursivo masivo, TMDB no corre salvo <code class="rounded bg-black/15 px-0.5 font-mono">RAIDRIVE_IMPORT_RECURSIVE_ENRICH_TMDB=true</code> (evita cargas eternas).</p>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-emerald-950 dark:text-emerald-100">Categoría destino</label>
                            <select name="category_id" required class="mt-1 w-full rounded-lg border-emerald-200 bg-white text-sm dark:border-emerald-800 dark:bg-gray-900 dark:text-white">
                                @foreach ($categoryOptions as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-input-error :messages="$errors->get('folder_paths')" class="mb-2" />
                        <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-600 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                            Importar carpetas marcadas (recursivo)
                        </button>
                    </form>
                @endif

                @if ($canImportRecursive)
                    <form method="POST" action="{{ route('admin.library.raidrive.import-recursive') }}" class="mt-6 border-t border-gray-200 pt-4 dark:border-gray-700" onsubmit="return confirm('Se importarán todos los vídeos bajo esta carpeta (hasta {{ $importRecursiveMax }} archivos). ¿Continuar?');">
                        @csrf
                        <input type="hidden" name="return_path" value="{{ $path }}">
                        <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">Importar esta carpeta (actual)</h3>
                        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Todo lo que cuelga de la <strong>ruta actual</strong> (no hace falta marcar nada). Mismo tope y deduplicado que arriba. Puede tardar minutos: dejá la pestaña abierta.</p>
                        <p class="mb-3 rounded-lg border border-slate-200/80 bg-slate-50/90 px-2 py-2 text-[11px] text-slate-800 dark:border-slate-600 dark:bg-slate-800/60 dark:text-slate-200/95">No se importan rutas típicas de <strong>extras</strong> (<code class="font-mono">extras</code>, <code class="font-mono">featurettes</code>, entrevistas, etc.), archivos bajo <code class="font-mono">BDMV/STREAM</code> (fragmentos Blu-ray), ni nombres que empiecen por <code class="font-mono">sample</code> / <code class="font-mono">trailer</code>. Así el catálogo no se infla con miles de archivos por una sola película. Podés ampliar exclusiones con <code class="font-mono">RAIDRIVE_IMPORT_EXTRA_SKIP_SEGMENTS</code> y desactivar lo de Blu-ray con <code class="font-mono">RAIDRIVE_IMPORT_SKIP_BDMV_STREAM=false</code> en <code class="font-mono">.env</code> (luego <code class="font-mono">config:clear</code>).</p>
                        <div class="mb-3">
                            <label class="block text-sm font-medium">Categoría destino (recursivo)</label>
                            <select name="category_id" required class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800">
                                @foreach ($categoryOptions as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-700 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-600 dark:bg-indigo-600 dark:hover:bg-indigo-500">
                            Importar todo en esta carpeta
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif
</x-panel-layout>
