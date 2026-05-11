<x-panel-layout title="Importar lista M3U / M3U8">
    <div class="mb-6 flex flex-wrap gap-3">
        <a href="{{ route('admin.m3u.manage') }}" class="admin-btn-secondary">Gestión listas: borrar todo lo remoto o podar caídos</a>
        <a href="{{ route('admin.categories.index') }}" class="admin-btn-secondary">Gestionar categorías y carpetas</a>
    </div>

    <div class="mb-4 hidden rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100" aria-hidden="true">
        <p class="font-medium">Solo contenido con derechos.</p>
        <p class="mt-2">Formato: línea <code class="rounded bg-white/50 px-1 dark:bg-black/30">#EXTINF</code> y en la siguiente la URL.</p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            <li><strong>Cambiar de lista M3U entera:</strong> no hace falta otro programa. Andá al menú <strong class="text-amber-950 dark:text-white">Gestión listas M3U (borrar)</strong> (ruta admin <code class="rounded bg-black/10 px-1 font-mono text-xs dark:bg-black/35">/admin/m3u/gestion</code>), apartado rojo «Eliminar contenido por URL remota», elegí «Todas las URLs remotas», escribí <code class="rounded bg-black/10 px-1 dark:bg-black/35">BORRAR</code> y confirmá. Ahí borrás <em>todo</em> lo importado por http/https; después importás la lista nueva. Lo de biblioteca (<code class="rounded bg-black/10 px-1">local:</code>) no se toca.</li>
            <li><strong>Una sola carpeta:</strong> elige la categoría destino y deja desmarcada la opción de <code>group-title</code>; todo el archivo se guarda ahí.</li>
            <li><strong>Subcarpetas automáticas:</strong> marca “Organizar por group-title”; el sistema creará subcategorías bajo la carpeta elegida usando el atributo <code>group-title</code> de cada canal (ej. “Películas”, “Deportes”).</li>
            <li><strong>Lista M3U:</strong> sube <code>.m3u</code> / <code>.m3u8</code> / <code>.txt</code> o pega el texto. Opción <code>group-title</code> solo aplica a listas.</li>
            <li><strong>Un solo vídeo:</strong> se guarda siempre en una sola carpeta del servidor (<code class="rounded bg-white/50 px-1 dark:bg-black/30">storage/app/public/{{ \App\Services\LocalMediaService::UPLOADS_PUBLIC_SUBDIR }}</code>) con nombre único; elige la categoría para catalogarlo.</li>
            <li><strong>Límite real de subida:</strong> lo marca PHP (<code>upload_max_filesize</code> y <code>post_max_size</code>); si se queda “cargando” y luego falla, casi siempre es un límite bajo o un vídeo demasiado grande.</li>
            <li><strong>Listas .m3u:</strong> son casi siempre <em>texto pequeño</em> (kilobytes): lo “pesado” son las URLs remotas o los archivos locales a los que apuntan, no el .m3u en sí. El servidor las procesa <strong>línea a línea</strong> para poder manejar listas largas sin cargar todo en memoria.</li>
            <li><strong>Películas de varios GB:</strong> subir un .mp4 de p. ej. 7&nbsp;GB por el navegador no es práctico (límites de PHP, tiempo, proxies). Lo habitual es: (1) poner el vídeo en la carpeta montada con RaiDrive y registrarlo en <strong>Biblioteca local</strong>, o (2) alojar el archivo en un servidor/URL y en el M3U usar solo la línea <code>https://…/pelicula.mp4</code>.</li>
        </ul>
    </div>

    <div class="mb-4 hidden rounded-lg border px-4 py-3 text-sm {{ $phpEffectiveLow ? 'border-red-300 bg-red-50 text-red-950 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100' : 'border-gray-200 bg-gray-50 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200' }}" aria-hidden="true">
        <p class="font-medium">Límites PHP en este servidor</p>
        <p class="mt-1 font-mono text-xs">upload_max_filesize={{ $phpUploadMax }} · post_max_size={{ $phpPostMax }} · efectivo ≈ {{ $phpEffectiveHuman }}</p>
        @if ($phpEffectiveLow)
            <p class="mt-2">Subir vídeos grandes fallará hasta subir ambos valores en el <code class="rounded bg-black/10 px-1">php.ini</code> de Laragon (p. ej. 512M), guardar y reiniciar Apache.</p>
        @endif
    </div>

    <form id="m3u-import-form" method="POST" action="{{ route('admin.m3u.import.store') }}" enctype="multipart/form-data" class="admin-card max-w-4xl space-y-6">
        @csrf
        <div>
            <label class="admin-label" for="m3u_category_id">Categoría / carpeta destino</label>
            <select id="m3u_category_id" name="category_id" required class="admin-select">
                <option value="" disabled @selected(! old('category_id'))>— Elige dónde importar —</option>
                @foreach ($categoryOptions as $opt)
                    <option value="{{ $opt['id'] }}" @selected(old('category_id')==$opt['id'])>{{ $opt['label'] }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">El tipo del contenido importado será el de esta categoría (live / vod / series).</p>
            <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
        </div>

        <label class="flex cursor-pointer items-start gap-4 rounded-xl border-2 border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-800/40">
            <input type="hidden" name="split_by_group" value="0">
            <input type="checkbox" name="split_by_group" value="1" @checked(old('split_by_group')) class="mt-1 h-5 w-5 rounded border-slate-300 text-indigo-600 dark:border-slate-600">
            <span>
                <span class="font-medium text-gray-900 dark:text-white">Organizar por group-title del M3U</span>
                <span class="block text-sm text-gray-600 dark:text-gray-400">Crea subcarpetas bajo la categoría elegida (una por cada valor distinto de <code>group-title</code>). Si una línea no trae grupo, se usa “Sin grupo”.</span>
            </span>
        </label>

        @php
            $probeStreamsOn = old('probe_streams') !== null
                ? (string) old('probe_streams') === '1'
                : ($probeStreamsDefault ?? true);
        @endphp
        <label class="flex cursor-pointer items-start gap-4 rounded-xl border-2 border-emerald-200/60 bg-emerald-50/80 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/30">
            <input type="hidden" name="probe_streams" value="0">
            <input type="checkbox" name="probe_streams" value="1" @checked($probeStreamsOn) class="mt-1 h-5 w-5 rounded border-slate-300 text-emerald-600 dark:border-slate-600">
            <span>
                <span class="font-medium text-gray-900 dark:text-white">Comprobar canales antes de registrar</span>
                <span class="hidden text-sm text-gray-600 dark:text-gray-400" aria-hidden="true">El servidor consulta cada URL (http/https) y <strong>solo guarda las que responden</strong>. Las caídas o inaccesibles <strong>no se añaden</strong> al catálogo. Listas muy largas pueden tardar varios minutos; desmarcá esta opción para importar todo sin comprobar (más rápido, pero puede incluir enlaces muertos).</span>
                <span class="mt-2 hidden text-xs text-gray-500 dark:text-gray-500" aria-hidden="true">TLS con certificado dudoso: <code class="rounded bg-black/5 px-1 font-mono dark:bg-white/10">M3U_PROBE_ALLOW_INSECURE_TLS=true</code> en <code class="rounded bg-black/5 px-1 font-mono">.env</code>. Ajustá concurrencia: <code class="font-mono">M3U_PROBE_POOL_SIZE</code> (p. ej. 24).</span>
            </span>
        </label>

        <div>
            <label class="admin-label">Subir archivo desde tu PC</label>
            @php
                $acceptList = '.m3u,.m3u8,.txt,text/plain,'.collect($videoExtensions)->map(fn ($e) => '.'.$e)->implode(',');
            @endphp
            <input
                type="file"
                name="m3u_file"
                accept="{{ $acceptList }}"
                class="mt-2 block w-full cursor-pointer rounded-xl border-2 border-slate-200 bg-white py-2 pl-2 text-base file:me-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-indigo-600 file:px-5 file:py-3 file:text-sm file:font-semibold file:text-white hover:file:bg-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:file:bg-indigo-500 dark:hover:file:bg-indigo-400"
            >
            <p class="mt-1 text-xs text-gray-500">
                Listas: .m3u, .m3u8, .txt. Vídeos: {{ implode(', ', array_map(fn ($e) => '.'.$e, $videoExtensions)) }}.
                Tamaño máximo por archivo: el menor entre ~400&nbsp;MB (aplicación) y el límite efectivo de PHP (arriba).
            </p>
            <x-input-error :messages="$errors->get('m3u_file')" class="mt-1" />
        </div>

        <div>
            <label class="admin-label" for="m3u_paste">O pegar contenido M3U</label>
            <textarea id="m3u_paste" name="m3u" rows="14" class="admin-textarea min-h-[18rem] font-mono text-sm" placeholder="#EXTM3U&#10;#EXTINF:-1 tvg-name=&quot;Canal 1&quot; group-title=&quot;TV Colombia&quot;,Canal 1&#10;https://...">{{ old('m3u') }}</textarea>
            <x-input-error :messages="$errors->get('m3u')" class="mt-1" />
        </div>
        <button id="m3u-import-submit" type="submit" class="admin-btn-primary">Procesar importación</button>
        <p id="m3u-import-wait" class="hidden text-sm text-gray-600 dark:text-gray-400">Subiendo… no cierres la pestaña. Con vídeos grandes el navegador puede tardar varios minutos hasta que PHP termine de recibir el archivo.</p>
    </form>

    <script>
        document.getElementById('m3u-import-form')?.addEventListener('submit', function () {
            const btn = document.getElementById('m3u-import-submit');
            const wait = document.getElementById('m3u-import-wait');
            const fileInput = this.querySelector('input[name="m3u_file"]');
            const probeOn = !!this.querySelector('input[name="probe_streams"][type="checkbox"]')?.checked;
            const pasted = this.querySelector('textarea[name="m3u"]')?.value?.trim() ?? '';
            const looksPlaylist = pasted.includes('#EXT') || /https?:\/\//i.test(pasted);

            let showWait = !!(fileInput?.files?.length);
            if (! showWait && probeOn && pasted.length > 80 && looksPlaylist) {
                showWait = true;
            }

            if (showWait) {
                btn?.setAttribute('disabled', 'disabled');
                wait?.classList.remove('hidden');
            }
        });
    </script>
</x-panel-layout>
