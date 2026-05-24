<x-panel-layout title="Crear cuenta demo">
    <div class="max-w-xl space-y-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">

        <div class="rounded-lg bg-blue-50 p-3 text-sm text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
            ⏱ Esta cuenta demo vencerá <strong>{{ $demoDurationHours }} hora(s)</strong> después de ser creada
            (hora Bogotá). No se descuentan créditos.
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-100 p-3 text-sm text-green-800 dark:bg-green-900 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route($routePrefix . '.demos.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium">Nombre</label>
                <input name="name" value="{{ old('name') }}" required
                    class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium">Contraseña</label>
                <input type="password" name="password" required
                    class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
            </div>
            <div>
                <label class="block text-sm font-medium">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" required
                    class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>
            <div class="flex gap-2">
                <x-primary-button type="submit">Crear demo</x-primary-button>
                <a href="{{ route($routePrefix . '.customers.index') }}"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-600">
                    Volver
                </a>
            </div>
        </form>
    </div>
</x-panel-layout>
