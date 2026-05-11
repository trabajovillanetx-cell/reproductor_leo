<x-panel-layout title="Categorías y carpetas">
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.categories.create') }}" class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Nueva categoría / carpeta</a>
        <a href="{{ route('admin.m3u.import') }}" class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-600">Importar M3U</a>
    </div>
    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Las carpetas anidadas comparten el mismo tipo (live / vod / series) que su padre. Úsalas para ordenar canales, películas o series antes de importar M3U.</p>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium">Nombre</th>
                    <th class="px-4 py-3 text-left font-medium">Carpeta padre</th>
                    <th class="px-4 py-3 text-left font-medium">Tipo</th>
                    <th class="px-4 py-3 text-left font-medium">Activa</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($categories as $cat)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $cat->name }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $cat->parent?->name ?? '— (raíz)' }}</td>
                        <td class="px-4 py-3">{{ $cat->type->value }}</td>
                        <td class="px-4 py-3">{{ $cat->is_active ? 'Sí' : 'No' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.categories.edit', $cat) }}" class="text-indigo-600 hover:underline dark:text-indigo-400">Editar</a>
                            <form action="{{ route('admin.categories.destroy', $cat) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar?');">@csrf @method('DELETE')
                                <button type="submit" class="ms-2 text-red-600 hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-800">{{ $categories->links() }}</div>
    </div>
</x-panel-layout>
