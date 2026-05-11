<x-panel-layout title="Planes">
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.plans.create') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500">Nuevo plan</a>
    </div>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium">Nombre</th>
                    <th class="px-4 py-3 text-left font-medium">Meses</th>
                    <th class="px-4 py-3 text-left font-medium">Precio</th>
                    <th class="px-4 py-3 text-left font-medium">Activo</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($plans as $plan)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $plan->name }}</td>
                        <td class="px-4 py-3">{{ $plan->duration_months }}</td>
                        <td class="px-4 py-3">{{ number_format($plan->price, 2) }}</td>
                        <td class="px-4 py-3">{{ $plan->is_active ? 'Sí' : 'No' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.plans.edit', $plan) }}" class="text-indigo-600 hover:underline dark:text-indigo-400">Editar</a>
                            <form action="{{ route('admin.plans.destroy', $plan) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar plan?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="ms-2 text-red-600 hover:underline dark:text-red-400">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-panel-layout>
