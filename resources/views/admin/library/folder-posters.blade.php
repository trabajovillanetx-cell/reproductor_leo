<x-panel-layout title="Carátulas de carpetas (cliente)">
    @if (session('success'))
        <div class="mb-6 rounded-xl border border-emerald-400/50 bg-emerald-500/15 px-4 py-3 text-center text-sm font-medium text-emerald-100">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 rounded-xl border border-rose-400/50 bg-rose-500/15 px-4 py-3 text-center text-sm font-medium text-rose-100">
            {{ session('error') }}
        </div>
    @endif

    <div class="mb-8 flex flex-wrap justify-center gap-3">
        <a href="{{ route('admin.library.folders.index') }}" class="admin-btn-ghost-light !py-3 !text-sm">Carpetas del catálogo</a>
        <a href="{{ route('admin.library.raidrive') }}" class="admin-btn-ghost-light !py-3 !text-sm">Biblioteca local</a>
        <a href="{{ route('admin.contents.index') }}" class="admin-btn-ghost-light !py-3 !text-sm">Contenido</a>
    </div>

    <div class="mx-auto max-w-3xl rounded-2xl border border-white/15 bg-white/[0.06] p-6 shadow-lg backdrop-blur sm:p-8">
        <div class="hidden" aria-hidden="true">
            <h2 class="text-lg font-bold text-white sm:text-xl">Carátula manual por carpeta</h2>
            <p class="mt-2 text-sm leading-relaxed text-white/70">
                Se usa en el cliente en <strong class="text-white/90">Carpetas principales</strong> y al navegar por subcarpetas: la imagen reemplaza el cartel “Sin carátula” cuando no hay póster en los títulos.
                La ruta debe ser la misma que en el catálogo (se guarda en minúsculas). Podés <strong class="text-white/90">subir un archivo</strong> (JPG, PNG, WebP, GIF hasta ~12&nbsp;MB) o pegar una <strong class="text-white/90">URL</strong> (TMDB, Imgur, etc.). Si subís archivo, tiene prioridad sobre la URL.
            </p>
            <p class="mt-2 text-xs text-amber-100/90">Si la imagen subida no carga en el navegador: ejecutá <code class="rounded bg-black/30 px-1 font-mono">php artisan storage:link</code> en el servidor.</p>
        </div>

        @if ($suggestedFolderPaths->isNotEmpty())
            <div class="mt-6 rounded-xl border border-cyan-500/25 bg-black/25 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-cyan-200/90">Rutas detectadas en el catálogo (tocá para copiar al campo)</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($suggestedFolderPaths as $libPath)
                        <button
                            type="button"
                            class="rounded-lg border border-white/15 bg-white/[0.07] px-2.5 py-1.5 font-mono text-[11px] text-sky-100 transition hover:border-cyan-400/40 hover:bg-white/[0.12]"
                            data-fill-folder="{{ $libPath }}"
                        >{{ $libPath }}</button>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.library.folder-posters.store') }}" enctype="multipart/form-data" class="mt-8 space-y-4">
            @csrf
            <div>
                <label class="admin-label" for="folder_path">Ruta de carpeta</label>
                <input
                    id="folder_path"
                    name="folder_path"
                    value="{{ old('folder_path') }}"
                    required
                    class="admin-input font-mono text-sm"
                    placeholder="peliculas/apple tv"
                >
                <x-input-error :messages="$errors->get('folder_path')" class="mt-1" />
            </div>
            <div>
                <label class="admin-label" for="poster_file">Imagen personalizada (archivo)</label>
                <input
                    id="poster_file"
                    name="poster_file"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif"
                    class="folder-poster-file-input block w-full cursor-pointer rounded-xl border border-white/20 bg-black/35 px-3 py-2.5 text-sm text-slate-100 file:mr-4 file:cursor-pointer file:rounded-lg file:border-2 file:border-indigo-300/50 file:bg-indigo-600 file:px-4 file:py-2.5 file:text-sm file:font-bold file:text-white file:shadow file:shadow-indigo-900/40 file:outline-none hover:file:border-indigo-200/70 hover:file:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400/50"
                >
                <x-input-error :messages="$errors->get('poster_file')" class="mt-1" />
            </div>
            <div>
                <label class="admin-label" for="poster_url">O URL de imagen (opcional si subís archivo)</label>
                <input
                    id="poster_url"
                    name="poster_url"
                    type="url"
                    value="{{ old('poster_url') }}"
                    class="admin-input font-mono text-sm"
                    placeholder="https://…"
                >
                <x-input-error :messages="$errors->get('poster_url')" class="mt-1" />
            </div>
            <button type="submit" class="admin-btn-primary">Guardar o actualizar</button>
        </form>
    </div>

    @if ($suggestedFolderPaths->isNotEmpty())
        <script>
            document.querySelectorAll('[data-fill-folder]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var el = document.getElementById('folder_path');
                    if (el) el.value = btn.getAttribute('data-fill-folder') || '';
                    el && el.focus();
                });
            });
        </script>
    @endif

    <div class="mx-auto mt-10 max-w-4xl">
        <h3 class="mb-4 text-center text-base font-bold text-white">Carátulas definidas</h3>
        @if ($rows->isEmpty())
            <p class="rounded-xl border border-white/10 bg-white/[0.04] py-10 text-center text-sm text-white/60">Todavía no hay carátulas manuales. Usá el formulario de arriba.</p>
        @else
            <div class="overflow-hidden rounded-2xl border border-white/15 bg-white/[0.04] shadow-lg">
                <table class="min-w-full divide-y divide-white/10 text-sm">
                    <thead class="bg-black/30 text-left text-xs font-bold uppercase tracking-wider text-white/55">
                        <tr>
                            <th class="px-4 py-3">Vista</th>
                            <th class="px-4 py-3">Ruta</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/90">
                        @foreach ($rows as $row)
                            <tr class="align-middle">
                                <td class="px-4 py-3">
                                    <img src="{{ $row->poster_url }}" alt="" class="h-16 w-11 rounded object-cover ring-1 ring-white/15" loading="lazy" referrerpolicy="no-referrer">
                                </td>
                                <td class="px-4 py-3">
                                    <code class="break-all rounded bg-black/35 px-1.5 py-0.5 text-xs text-sky-200">{{ $row->folder_path }}</code>
                                    <p class="mt-1 line-clamp-1 max-w-md font-mono text-[11px] text-white/45" title="{{ $row->poster_url }}">{{ $row->poster_url }}</p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.library.folder-posters.destroy', $row) }}" class="inline" onsubmit="return confirm('¿Quitar esta carátula manual? En el cliente volverá la imagen automática si existe.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="admin-btn-ghost-light !px-3 !py-2 text-xs text-rose-200 hover:border-rose-400/50">Quitar</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-panel-layout>
