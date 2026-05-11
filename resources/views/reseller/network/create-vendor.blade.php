<x-panel-layout title="Nuevo vendedor en tu red">
    <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Créditos disponibles: <strong>{{ $balance }}</strong>. Lo que asignes se descuenta de tu cuenta y pasa al vendedor.</p>

    <form method="POST" action="{{ route('reseller.network.vendors.store') }}" class="max-w-xl space-y-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        @csrf
        <div>
            <label class="block text-sm font-medium">Nombre</label>
            <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>
        <div>
            <label class="block text-sm font-medium">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>
        <div>
            <label class="block text-sm font-medium">Contraseña</label>
            <input type="password" name="password" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
        </div>
        <div>
            <label class="block text-sm font-medium">Confirmar contraseña</label>
            <input type="password" name="password_confirmation" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>
        <div>
            <label class="block text-sm font-medium">Créditos a transferir (meses)</label>
            <input type="number" name="credits" value="{{ old('credits', 0) }}" min="0" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
            <x-input-error :messages="$errors->get('credits')" class="mt-1" />
        </div>
        <div class="flex gap-2">
            <x-primary-button type="submit">Crear y transferir</x-primary-button>
            <a href="{{ route('reseller.network.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-600">Volver</a>
        </div>
    </form>
</x-panel-layout>
