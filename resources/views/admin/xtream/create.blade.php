<x-panel-layout title="Nueva fuente Xtream">
    <div class="mb-4">
        <a href="{{ route('admin.xtream.index') }}" class="text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-400">&larr; Volver al listado</a>
    </div>

    <form method="POST" action="{{ route('admin.xtream.store') }}" class="admin-card max-w-2xl space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-medium">Nombre visible</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium">Host (sin <code class="text-xs">player_api.php</code>)</label>
            <input type="url" name="host" value="{{ old('host') }}" required placeholder="http://ejemplo.com:8080" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
            @error('host')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium">Usuario</label>
                <input type="text" name="username" value="{{ old('username') }}" required autocomplete="off" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                @error('username')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Contraseña</label>
                <input type="password" name="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium">Categoría destino — TV en vivo (opcional)</label>
            <select name="live_category_id" class="mt-1 w-full rounded-lg border border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                <option value="">—</option>
                @foreach ($categoryOptions as $opt)
                    <option value="{{ $opt['id'] }}" @selected(old('live_category_id') == $opt['id'])>{{ $opt['label'] }}</option>
                @endforeach
            </select>
            @error('live_category_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium">Categoría destino — VOD (opcional)</label>
            <select name="vod_category_id" class="mt-1 w-full rounded-lg border border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                <option value="">—</option>
                @foreach ($categoryOptions as $opt)
                    <option value="{{ $opt['id'] }}" @selected(old('vod_category_id') == $opt['id'])>{{ $opt['label'] }}</option>
                @endforeach
            </select>
            @error('vod_category_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 dark:border-gray-600" @checked(old('is_active', true))>
            Fuente activa
        </label>
        <p class="text-xs text-gray-500 dark:text-gray-400">La sincronización importa listados <strong>live</strong> y <strong>VOD</strong> vía API y crea/actualiza filas en Contenido con <code class="rounded bg-gray-100 px-1 dark:bg-black/40">source_type=xtream</code>. Definí al menos una categoría antes de sincronizar.</p>
        <button type="submit" class="inline-flex rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-500">Guardar</button>
    </form>
</x-panel-layout>
