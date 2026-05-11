<x-panel-layout title="Editar vendedor">
    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.vendors.update', $vendor) }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            @csrf @method('PUT')
            <div>
                <label class="block text-sm font-medium">Nombre</label>
                <input name="name" value="{{ old('name', $vendor->name) }}" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
            </div>
            <div>
                <label class="block text-sm font-medium">Email</label>
                <input type="email" name="email" value="{{ old('email', $vendor->email) }}" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
            </div>
            <div>
                <label class="block text-sm font-medium">Nueva contraseña (opcional)</label>
                <input type="password" name="password" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
            </div>
            <div>
                <label class="block text-sm font-medium">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium">Estado</label>
                <select name="status" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                    <option value="active" @selected(old('status', $vendor->status->value)==='active')>Activo</option>
                    <option value="suspended" @selected(old('status', $vendor->status->value)==='suspended')>Suspendido</option>
                </select>
            </div>
            <x-primary-button type="submit">Guardar cambios</x-primary-button>
        </form>

        <form method="POST" action="{{ route('admin.vendors.credits', $vendor) }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            @csrf
            <h2 class="font-semibold text-gray-900 dark:text-white">Créditos (meses)</h2>
            <p class="text-sm text-gray-500">Cada mes de plan asignado a un cliente consume un crédito.</p>
            <div>
                <label class="block text-sm font-medium">Total de créditos</label>
                <input type="number" name="credits" value="{{ old('credits', $vendor->resellerCredits?->credits ?? 0) }}" min="0" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                <x-input-error :messages="$errors->get('credits')" class="mt-1" />
            </div>
            <x-primary-button type="submit">Actualizar créditos</x-primary-button>
        </form>
    </div>

    <div class="mt-6">
        <form method="POST" action="{{ route('admin.vendors.destroy', $vendor) }}" onsubmit="return confirm('¿Eliminar vendedor?');">
            @csrf @method('DELETE')
            <x-danger-button type="submit">Eliminar vendedor</x-danger-button>
        </form>
    </div>
</x-panel-layout>
