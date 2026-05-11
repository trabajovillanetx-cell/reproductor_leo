<x-panel-layout title="Nuevo cliente">
    <form method="POST" action="{{ route('vendor.customers.store') }}" class="max-w-xl space-y-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
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
            <label class="block text-sm font-medium">Plan inicial</label>
            <select name="plan_id" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                @foreach ($plans as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} — {{ $p->duration_months }} mes(es) ({{ $p->duration_months }} créditos)</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('plan_id')" class="mt-1" />
        </div>
        <p class="text-xs text-gray-500">Se descontarán créditos equivalentes a los meses del plan.</p>
        <div class="flex gap-2">
            <x-primary-button type="submit">Crear cliente</x-primary-button>
            <a href="{{ route('vendor.customers.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-600">Volver</a>
        </div>
    </form>
</x-panel-layout>
