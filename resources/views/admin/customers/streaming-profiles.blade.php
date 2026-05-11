<x-panel-layout title="Espacios de reproducción — {{ $customer->name }}">
    @include('partials.flash')

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.customers.show', $customer) }}" class="admin-btn-secondary">← Volver al cliente</a>
        <a href="{{ route('admin.customers.edit', $customer) }}" class="admin-btn-secondary">Editar cuenta</a>
    </div>

    <div class="admin-hint mb-8 max-w-4xl">
        <p class="font-semibold text-indigo-900 dark:text-indigo-100">Configuración solo para administradores</p>
        <p class="mt-2 text-sm leading-relaxed text-indigo-950/95 dark:text-indigo-50/95">
            Aquí defines el <strong>nombre</strong>, <strong>avatar</strong> (subiendo un archivo o pegando una URL) y <strong>PIN de 4 dígitos</strong> de cada espacio. El usuario del catálogo <strong>no puede</strong> abrir esta pantalla: solo elige su espacio e ingresa el PIN que tú asignes. Cada PIN debe ser único entre los cinco espacios de esta cuenta. En el servidor debe existir el enlace <code class="rounded bg-black/10 px-1 py-0.5 font-mono text-xs dark:bg-white/10">public/storage</code> (<code class="font-mono text-xs">php artisan storage:link</code>).
        </p>
    </div>

    <div class="space-y-8">
        @foreach ($profiles as $i => $profile)
            @php
                $isOld = (int) old('_editing_profile') === (int) $profile->id;
                $prevAvatar = trim((string) ($profile->avatar_url ?? ''));
                $letter = mb_strtoupper(mb_substr(trim((string) $profile->name), 0, 1));
                $accents = [
                    'border-amber-400/35 bg-gradient-to-br from-amber-500/15 to-transparent',
                    'border-emerald-400/35 bg-gradient-to-br from-emerald-500/15 to-transparent',
                    'border-sky-400/35 bg-gradient-to-br from-sky-500/15 to-transparent',
                ];
                $a = $accents[$i % 3];
                $showAvatar = $isOld ? trim((string) old('avatar_url', $prevAvatar)) : $prevAvatar;
            @endphp
            <section class="admin-card border {{ $a }}">
                <div class="flex flex-wrap items-center gap-4 border-b border-white/10 pb-5 dark:border-slate-700/80">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-white/25 bg-slate-800 text-xl font-bold text-white shadow-md">
                        @if ($showAvatar !== '')
                            <img src="{{ $showAvatar }}" alt="" class="h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer">
                        @else
                            {{ $letter }}
                        @endif
                    </div>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Espacio {{ $profile->sort_order + 1 }}</p>
                        <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $profile->name }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.customers.streaming-profiles.update', [$customer, $profile]) }}" enctype="multipart/form-data" class="mt-6 space-y-5">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_editing_profile" value="{{ $profile->id }}">

                    <div>
                        <label class="admin-label" for="name-{{ $profile->id }}">Nombre en pantalla</label>
                        <input
                            id="name-{{ $profile->id }}"
                            name="name"
                            value="{{ $isOld ? old('name', $profile->name) : $profile->name }}"
                            required
                            maxlength="100"
                            class="admin-input mt-1 w-full max-w-xl"
                            autocomplete="off"
                        >
                        @if ($isOld)
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        @endif
                    </div>

                    <div>
                        <label class="admin-label" for="avatar-{{ $profile->id }}">Imagen del avatar</label>
                        <p class="mb-2 text-xs text-slate-600 dark:text-slate-400">Subí un archivo (JPG, PNG, WebP o GIF, máx. 5&nbsp;MB) o indicá una URL abajo. Si subís archivo, reemplaza la URL guardada.</p>
                        <input
                            id="avatar-{{ $profile->id }}"
                            name="avatar"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            class="block w-full max-w-xl text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-indigo-500 dark:text-slate-300 dark:file:bg-indigo-500 dark:hover:file:bg-indigo-400"
                        >
                        @if ($isOld)
                            <x-input-error :messages="$errors->get('avatar')" class="mt-1" />
                        @endif
                    </div>

                    <div>
                        <label class="admin-label" for="avatar_url-{{ $profile->id }}">O bien: URL del avatar</label>
                        <p class="mb-2 text-xs text-slate-600 dark:text-slate-400">Enlace HTTPS a una imagen externa. Dejá vacío y guardá sin subir archivo para quitar el avatar.</p>
                        <input
                            id="avatar_url-{{ $profile->id }}"
                            name="avatar_url"
                            type="url"
                            value="{{ $isOld ? old('avatar_url', $prevAvatar) : $prevAvatar }}"
                            maxlength="2048"
                            placeholder="https://…"
                            class="admin-input w-full max-w-3xl"
                        >
                        @if ($isOld)
                            <x-input-error :messages="$errors->get('avatar_url')" class="mt-1" />
                        @endif
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-900/50">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-300">PIN de acceso (4 dígitos)</p>
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">Deja ambos campos vacíos si no querés cambiar el PIN actual. Entre espacios del mismo cliente no puede repetirse.</p>
                        <div class="mt-4 grid max-w-2xl gap-4 sm:grid-cols-2">
                            <div>
                                <label class="admin-label" for="pin-{{ $profile->id }}">Nuevo PIN</label>
                                <input
                                    id="pin-{{ $profile->id }}"
                                    name="pin"
                                    type="password"
                                    inputmode="numeric"
                                    pattern="\d{4}"
                                    maxlength="4"
                                    autocomplete="new-password"
                                    class="admin-input mt-1 font-mono tracking-widest"
                                    placeholder="••••"
                                >
                            </div>
                            <div>
                                <label class="admin-label" for="pin-conf-{{ $profile->id }}">Confirmar PIN</label>
                                <input
                                    id="pin-conf-{{ $profile->id }}"
                                    name="pin_confirmation"
                                    type="password"
                                    inputmode="numeric"
                                    pattern="\d{4}"
                                    maxlength="4"
                                    autocomplete="new-password"
                                    class="admin-input mt-1 font-mono tracking-widest"
                                    placeholder="••••"
                                >
                            </div>
                        </div>
                        @if ($isOld)
                            <x-input-error :messages="$errors->get('pin')" class="mt-2" />
                            <x-input-error :messages="$errors->get('pin_confirmation')" class="mt-1" />
                        @endif
                    </div>

                    <button type="submit" class="admin-btn-primary">Guardar este espacio</button>
                </form>
            </section>
        @endforeach
    </div>
</x-panel-layout>
