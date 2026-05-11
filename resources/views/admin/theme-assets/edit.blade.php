<x-panel-layout title="Imágenes del sitio">
    @include('partials.flash')

    <div class="admin-hint mb-8 hidden max-w-3xl" aria-hidden="true">
        <p class="font-semibold text-indigo-900 dark:text-indigo-100">Logos, favicon y fondos</p>
        <p class="mt-2 text-sm text-indigo-950/95 dark:text-indigo-50/95">
            Cada archivo que subís <strong>reemplaza al anterior de inmediato</strong> y el viejo se borra del disco (<code class="rounded bg-black/10 px-1 font-mono text-xs">storage/app/public/theme/</code>), así no se acumulan imágenes en desuso.
            Asegurate de tener <code class="rounded bg-black/10 px-1 font-mono text-xs">php artisan storage:link</code>.
        </p>
    </div>

    <form
        id="theme-assets-form"
        method="POST"
        action="{{ route('admin.theme-assets.update') }}"
        enctype="multipart/form-data"
        class="max-w-3xl space-y-10 pb-28"
    >
        @csrf
        @method('PUT')

        @php
            $blocks = [
                [
                    'title' => 'Fondo de la pantalla de login',
                    'name' => 'login_background',
                    'remove' => 'remove_login_background',
                    'preview' => $loginBackgroundUrl !== '' ? $loginBackgroundUrl : null,
                    'hint' => 'JPG, PNG, WebP o GIF (máx. 5 MB). Si no subís nada aquí, se usa la URL del .env LOGIN_BACKGROUND_URL.',
                ],
                [
                    'title' => 'Logo en login (imagen)',
                    'name' => 'login_logo',
                    'remove' => 'remove_login_logo',
                    'preview' => $loginLogoUrl,
                    'hint' => 'Opcional. Si hay imagen, se muestra arriba del formulario; si no, solo el texto de marca (STREAMING_LOGIN_BRAND). SVG permitido.',
                ],
                [
                    'title' => 'Fondo del catálogo (cliente con sesión)',
                    'name' => 'app_background',
                    'remove' => 'remove_app_background',
                    'preview' => $appBackgroundUrl !== '' ? $appBackgroundUrl : null,
                    'hint' => 'JPG, PNG, WebP o GIF. Si no hay archivo, se usa STREAMING_APP_BACKGROUND_URL del .env.',
                ],
                [
                    'title' => 'Fondo al elegir espacio (/app/profiles)',
                    'name' => 'profiles_picker',
                    'remove' => 'remove_profiles_picker',
                    'preview' => $profilesPickerUrl !== '' ? $profilesPickerUrl : null,
                    'hint' => 'Archivo subido tiene prioridad. Debajo podés usar una URL externa (sin borrar el archivo hasta que subas otro o marques quitar).',
                ],
                [
                    'title' => 'Favicon',
                    'name' => 'favicon',
                    'remove' => 'remove_favicon',
                    'preview' => $faviconUrl,
                    'hint' => 'ICO, PNG, SVG, etc. (máx. 1 MB). Visible en login, catálogo y panel si el layout lo incluye.',
                ],
            ];
        @endphp

        @foreach ($blocks as $b)
            <div class="admin-card space-y-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $b['title'] }}</h2>
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ $b['hint'] }}</p>
                @if (! empty($b['preview']))
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-900">
                        @if (in_array($b['name'], ['favicon', 'login_logo'], true))
                            <div class="flex items-center gap-3 p-4">
                                <img src="{{ $b['preview'] }}" alt="" class="h-14 w-14 object-contain sm:h-16 sm:w-16">
                                <span class="break-all text-xs text-slate-600 dark:text-slate-400">{{ $b['preview'] }}</span>
                            </div>
                        @else
                            <div class="h-40 w-full bg-cover bg-center sm:h-48" style="background-image: url('{{ e($b['preview']) }}');"></div>
                        @endif
                    </div>
                @endif
                <div>
                    <label class="admin-label" for="{{ $b['name'] }}">Nuevo archivo</label>
                    <input
                        id="{{ $b['name'] }}"
                        name="{{ $b['name'] }}"
                        type="file"
                        class="folder-poster-file-input mt-1 block w-full cursor-pointer rounded-xl border border-white/20 bg-black/35 px-3 py-2.5 text-sm text-slate-100 file:mr-4 file:cursor-pointer file:rounded-lg file:border-2 file:border-indigo-300/50 file:bg-indigo-600 file:px-4 file:py-2.5 file:text-sm file:font-bold file:text-white file:shadow file:shadow-indigo-900/40 hover:file:border-indigo-200/70 hover:file:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400/50"
                    >
                    <x-input-error :messages="$errors->get($b['name'])" class="mt-1" />
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="{{ $b['remove'] }}" value="1" class="rounded border-slate-400">
                    Quitar personalización (borra archivo en servidor si era subido)
                </label>
            </div>
        @endforeach

        <div class="admin-card space-y-3">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">URL externa — fondo elegir espacio (opcional)</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400">Solo si no usás archivo subido arriba. Dejá vacío para quitar.</p>
            <input type="url" name="profiles_picker_background_url" value="{{ old('profiles_picker_background_url', $profilesExternalUrl) }}" placeholder="https://…" class="admin-input w-full">
            <x-input-error :messages="$errors->get('profiles_picker_background_url')" class="mt-1" />
        </div>

        <button type="submit" class="admin-btn-primary">Guardar cambios</button>
    </form>

    <div
        class="pointer-events-none fixed inset-x-0 bottom-0 z-40 border-t border-white/15 bg-[#060a14]/95 px-4 py-3 shadow-[0_-8px_24px_rgba(0,0,0,0.35)] backdrop-blur-md sm:px-8 lg:left-64"
    >
        <div class="pointer-events-auto mx-auto flex max-w-3xl justify-end">
            <button type="submit" form="theme-assets-form" class="admin-btn-primary shadow-lg shadow-indigo-900/40">
                Guardar cambios
            </button>
        </div>
    </div>
</x-panel-layout>
