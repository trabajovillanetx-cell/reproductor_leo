<x-panel-layout title="Carpetas del catálogo (cliente)">
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
        <a href="{{ route('admin.library.raidrive') }}" class="admin-btn-ghost-light !py-3 !text-sm">Biblioteca local</a>
        <a href="{{ route('admin.library.folder-posters.index') }}" class="admin-btn-ghost-light !py-3 !text-sm">Carátulas de carpetas</a>
        <a href="{{ route('admin.contents.index') }}" class="admin-btn-ghost-light !py-3 !text-sm">Contenido</a>
        <a href="{{ route('admin.categories.index') }}" class="admin-btn-ghost-light !py-3 !text-sm">Categorías</a>
    </div>

    <div class="mx-auto max-w-5xl rounded-2xl border border-white/15 bg-white/[0.06] p-6 shadow-lg backdrop-blur sm:p-8">
        <h2 class="text-lg font-bold text-white sm:text-xl">Carpetas del catálogo</h2>
        <p class="mt-2 hidden text-sm leading-relaxed text-white/70" aria-hidden="true">
            Navegá por niveles: en la raíz solo ves las carpetas de primer nivel (ej. <code class="rounded bg-white/10 px-1 text-xs">PELICULAS</code>).
            Al hacer clic entrás a lo que hay dentro (ej. <code class="rounded bg-white/10 px-1 text-xs">AMC+</code>).
            Marcá con los checkboxes y usá <strong class="text-white/90">Eliminar seleccionadas</strong> para borrar del catálogo todo lo que cuelga de esas rutas (no borra archivos en el disco).
        </p>

        @if (! empty($breadcrumbs))
            <nav class="mt-6 flex flex-wrap items-center gap-2 text-sm text-white/80" aria-label="Ruta">
                <a href="{{ route('admin.library.folders.index') }}" class="rounded-lg px-2 py-1 font-medium text-sky-300 hover:bg-white/10 hover:text-white">Raíz</a>
                @foreach ($breadcrumbs as $i => $crumb)
                    <span class="text-white/35" aria-hidden="true">/</span>
                    @if ($i < count($breadcrumbs) - 1)
                        <a href="{{ route('admin.library.folders.index', ['parent' => $crumb['path']]) }}" class="rounded-lg px-2 py-1 font-medium text-sky-300 hover:bg-white/10 hover:text-white">{{ $crumb['label'] }}</a>
                    @else
                        <span class="rounded-lg px-2 py-1 font-semibold text-white">{{ $crumb['label'] }}</span>
                    @endif
                @endforeach
            </nav>
            @php
                $parentUp = $parent !== '' && str_contains($parent, '/') ? \Illuminate\Support\Str::beforeLast($parent, '/') : '';
            @endphp
            <p class="mt-2 text-xs text-white/50">
                Carpeta actual: <code class="break-all rounded bg-black/30 px-1.5 py-0.5 text-white/90">{{ $parent }}</code>
                @if ($parent !== '')
                    <a href="{{ route('admin.library.folders.index', $parentUp !== '' ? ['parent' => $parentUp] : []) }}" class="ml-2 font-semibold text-sky-300 underline hover:text-white">↑ Subir un nivel</a>
                @endif
            </p>
        @endif

        @if ($catalogEmpty ?? false)
            <p class="mt-8 rounded-xl border border-white/10 bg-black/20 px-4 py-10 text-center text-white/75">
                Todavía no hay carpetas en el catálogo. Importá desde <a href="{{ route('admin.library.raidrive') }}" class="font-semibold text-sky-300 underline hover:text-white">Biblioteca local</a> con importación recursiva para rellenar <code class="rounded bg-white/10 px-1 text-xs">library_folder</code>.
            </p>
        @elseif ($nothingHere ?? false)
            @php
                $upEmpty = $parent !== '' && str_contains($parent, '/') ? \Illuminate\Support\Str::beforeLast($parent, '/') : '';
            @endphp
            <p class="mt-8 rounded-xl border border-white/10 bg-black/20 px-4 py-10 text-center text-white/75">
                No hay subcarpetas ni ítems bajo esta ruta.
                <a href="{{ route('admin.library.folders.index', $upEmpty !== '' ? ['parent' => $upEmpty] : []) }}" class="mt-3 block font-semibold text-sky-300 underline hover:text-white">Volver atrás</a>
            </p>
        @elseif ($childRows->isEmpty() && ! ($directRow ?? null))
            <p class="mt-8 rounded-xl border border-white/10 bg-black/20 px-4 py-10 text-center text-white/75">No hay filas para mostrar.</p>
        @else
            @php
                $bulkMsg = '¿Eliminar del catálogo todo el contenido bajo las rutas seleccionadas (incluye subcarpetas)? No se puede deshacer.';
            @endphp
            <form id="folders-bulk-form" method="POST" action="{{ route('admin.library.folders.bulk-destroy') }}" class="mt-8" onsubmit="return document.querySelectorAll('#folders-bulk-form .folder-cb:checked').length > 0 && confirm({{ \Illuminate\Support\Js::from($bulkMsg) }});">
                @csrf
                <input type="hidden" name="return_parent" value="{{ $parent }}">

                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-white/80">
                        <input type="checkbox" id="folders-select-all" class="h-4 w-4 rounded border-white/30 bg-white/10 text-violet-600 focus:ring-violet-500">
                        <span>Seleccionar todas las filas</span>
                    </label>
                    <button type="submit" class="rounded-xl border border-rose-400/50 bg-rose-600/90 px-5 py-2.5 text-sm font-bold text-white shadow-lg transition hover:bg-rose-500">
                        Eliminar seleccionadas
                    </button>
                </div>

                <div class="overflow-x-auto rounded-xl border border-white/10">
                    <table class="min-w-full divide-y divide-white/10 text-left text-sm text-white/90">
                        <thead class="bg-black/25 text-xs font-bold uppercase tracking-wider text-white/55">
                            <tr>
                                <th class="w-12 px-3 py-3 sm:px-4" scope="col"></th>
                                <th class="px-4 py-3 sm:px-5">Carpeta</th>
                                <th class="px-4 py-3 sm:px-5">Total</th>
                                <th class="hidden px-4 py-3 sm:table-cell sm:px-5">VOD</th>
                                <th class="hidden px-4 py-3 sm:table-cell sm:px-5">Series</th>
                                <th class="hidden px-4 py-3 md:table-cell sm:px-5">Live</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @if ($directRow ?? null)
                                <tr class="bg-amber-500/5 hover:bg-white/[0.04]">
                                    <td class="px-3 py-3 sm:px-4">
                                        <input type="checkbox" name="paths[]" value="{{ $directRow['path'] }}" class="folder-cb h-4 w-4 rounded border-white/30 bg-white/10 text-violet-600 focus:ring-violet-500" aria-label="Seleccionar ruta exacta">
                                    </td>
                                    <td class="max-w-[16rem] px-4 py-3 text-sm text-amber-100/95 sm:max-w-xl sm:px-5">
                                        <span class="font-semibold">{{ $directRow['label'] }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 tabular-nums sm:px-5">{{ (int) $directRow['total'] }}</td>
                                    <td class="hidden whitespace-nowrap px-4 py-3 tabular-nums sm:table-cell sm:px-5">{{ (int) $directRow['vod'] }}</td>
                                    <td class="hidden whitespace-nowrap px-4 py-3 tabular-nums sm:table-cell sm:px-5">{{ (int) $directRow['series'] }}</td>
                                    <td class="hidden whitespace-nowrap px-4 py-3 tabular-nums md:table-cell sm:px-5">{{ (int) $directRow['live'] }}</td>
                                </tr>
                            @endif
                            @foreach ($childRows as $row)
                                <tr class="hover:bg-white/[0.04]">
                                    <td class="px-3 py-3 sm:px-4">
                                        <input type="checkbox" name="paths[]" value="{{ $row['path'] }}" class="folder-cb h-4 w-4 rounded border-white/30 bg-white/10 text-violet-600 focus:ring-violet-500" aria-label="Seleccionar {{ $row['label'] }}">
                                    </td>
                                    <td class="max-w-[16rem] px-4 py-3 sm:max-w-xl sm:px-5">
                                        @if ($row['has_children'])
                                            <a href="{{ route('admin.library.folders.index', ['parent' => $row['path']]) }}" class="break-all font-semibold text-sky-300 underline decoration-sky-400/50 hover:text-white">
                                                {{ $row['label'] }}
                                            </a>
                                            <span class="ml-1 text-xs text-white/40">→</span>
                                        @else
                                            <span class="break-all font-mono text-xs text-white/90 sm:text-sm">{{ $row['path'] }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 tabular-nums sm:px-5">{{ (int) $row['total'] }}</td>
                                    <td class="hidden whitespace-nowrap px-4 py-3 tabular-nums sm:table-cell sm:px-5">{{ (int) $row['vod'] }}</td>
                                    <td class="hidden whitespace-nowrap px-4 py-3 tabular-nums sm:table-cell sm:px-5">{{ (int) $row['series'] }}</td>
                                    <td class="hidden whitespace-nowrap px-4 py-3 tabular-nums md:table-cell sm:px-5">{{ (int) $row['live'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            <script>
                (function () {
                    var master = document.getElementById('folders-select-all');
                    if (!master) return;
                    master.addEventListener('change', function () {
                        document.querySelectorAll('.folder-cb').forEach(function (cb) { cb.checked = master.checked; });
                    });
                })();
            </script>
        @endif
    </div>
</x-panel-layout>
