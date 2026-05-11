<x-panel-layout title="Apariencia cliente (streaming)">
    @include('partials.flash')

    <div class="admin-hint mb-8 max-w-3xl">
        <p class="font-semibold text-indigo-900 dark:text-indigo-100">Fondo al elegir espacio</p>
        <p class="mt-2 text-sm text-indigo-950/95 dark:text-indigo-50/95">
            Los clientes ven esta imagen en <strong>/app/profiles</strong>. Si lo dejas vacío, se usa la URL del archivo <code class="rounded bg-black/10 px-1 font-mono text-xs">.env</code>
            <code class="rounded bg-black/10 px-1 font-mono text-xs">STREAMING_PROFILES_PICKER_BG_URL</code> o una imagen por defecto (cine) definida en configuración.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.streaming-appearance.update') }}" class="admin-card max-w-2xl space-y-5">
        @csrf
        <div>
            <label class="admin-label" for="profiles_picker_background_url">URL de imagen de fondo (HTTPS recomendado)</label>
            <input
                id="profiles_picker_background_url"
                name="profiles_picker_background_url"
                type="url"
                value="{{ old('profiles_picker_background_url', $profilesBackgroundUrl) }}"
                placeholder="https://…"
                class="admin-input mt-1 w-full"
            >
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Deja en blanco y guarda para quitar la personalización y volver al encadenado (.env → imagen por defecto).</p>
            <x-input-error :messages="$errors->get('profiles_picker_background_url')" class="mt-1" />
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4 text-sm dark:border-slate-700 dark:bg-slate-800/60">
            <p class="font-medium text-slate-800 dark:text-slate-100">Vista previa efectiva ahora</p>
            @if ($effectiveProfilesBg !== '')
                <p class="mt-2 break-all text-xs text-slate-600 dark:text-slate-400">{{ $effectiveProfilesBg }}</p>
                @php $pvBg = htmlspecialchars($effectiveProfilesBg, ENT_QUOTES, 'UTF-8'); @endphp
                <div class="mt-3 h-28 w-full max-w-md rounded-lg bg-cover bg-center ring-1 ring-slate-300 dark:ring-slate-600" style="background-image: url('{{ $pvBg }}');"></div>
            @else
                <p class="mt-2 text-slate-600 dark:text-slate-400">No hay URL (revisa configuración).</p>
            @endif
        </div>

        <button type="submit" class="admin-btn-primary">Guardar</button>
    </form>
</x-panel-layout>
