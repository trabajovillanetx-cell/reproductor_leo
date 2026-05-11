<x-panel-layout title="Fuentes Xtream Codes">
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.xtream.create') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500">Nueva fuente</a>
        <a href="{{ route('admin.m3u.import') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-600">Importar M3U</a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100">{{ session('error') }}</div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium">Nombre</th>
                    <th class="px-4 py-3 text-left font-medium">Host</th>
                    <th class="px-4 py-3 text-left font-medium">Usuario</th>
                    <th class="px-4 py-3 text-left font-medium">Última sync</th>
                    <th class="px-4 py-3 text-left font-medium">Activo</th>
                    <th class="px-4 py-3 text-right font-medium">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($sources as $src)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $src->name }}</td>
                        <td class="max-w-xs truncate px-4 py-3 font-mono text-xs">{{ $src->host }}</td>
                        <td class="px-4 py-3">{{ $src->username }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $src->last_synced_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $src->is_active ? 'Sí' : 'No' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <form action="{{ route('admin.xtream.test', $src) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-sky-600 hover:underline dark:text-sky-400">Probar</button>
                            </form>
                            <form action="{{ route('admin.xtream.sync', $src) }}" method="POST" class="inline ms-2">
                                @csrf
                                <button type="submit" class="text-indigo-600 hover:underline dark:text-indigo-400">Sincronizar</button>
                            </form>
                            <form action="{{ route('admin.xtream.destroy', $src) }}" method="POST" class="inline ms-2" onsubmit="return confirm('¿Eliminar fuente y todo el contenido Xtream vinculado?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline dark:text-red-400">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No hay fuentes. Creá una con host tipo <code class="rounded bg-gray-100 px-1 dark:bg-black/40">http://servidor:puerto</code>.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($errors->has('sync'))
        <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $errors->first('sync') }}</p>
    @endif
</x-panel-layout>
