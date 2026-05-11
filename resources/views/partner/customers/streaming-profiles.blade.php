@php
    $pfx = $routePrefix;
    $indexRoute = $pfx.'.customers.index';
    $updateRoute = $pfx.'.customers.streaming-profiles.update';
@endphp
<x-panel-layout title="Espacios de reproducción — {{ $customer->name }}">
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <a href="{{ route($indexRoute) }}" class="inline-flex rounded-lg border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/15">← Volver a clientes</a>
    </div>

    <div class="mb-8 rounded-xl border border-white/15 bg-white/5 p-5 text-sm text-blue-100/90">
        <p class="font-semibold text-white">Nombre, avatar y PIN por espacio</p>
        <p class="mt-2 leading-relaxed">
            Cada espacio es un perfil del catálogo: el usuario final elige uno e ingresa el PIN que definas. Los PIN deben ser únicos entre los cinco espacios de esta cuenta. Para avatares subidos hace falta <code class="rounded bg-black/20 px-1 py-0.5 font-mono text-xs">php artisan storage:link</code> en el servidor.
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
            <section class="overflow-hidden rounded-2xl border border-white/10 bg-[#071231]/80 shadow-lg shadow-black/20">
                <div class="flex flex-wrap items-center gap-4 border-b border-white/10 p-5">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-white/25 bg-slate-800 text-xl font-bold text-white shadow-md">
                        @if ($showAvatar !== '')
                            <img src="{{ $showAvatar }}" alt="" class="h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer">
                        @else
                            {{ $letter }}
                        @endif
                    </div>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-sky-300/90">Espacio {{ $profile->sort_order + 1 }} @if($profile->is_sold)<span class="ml-2 rounded bg-amber-500/30 px-2 py-0.5 text-amber-100">Vendido</span>@else<span class="ml-2 rounded bg-emerald-500/25 px-2 py-0.5 text-emerald-100">Disponible</span>@endif</p>
                        <p class="text-lg font-semibold text-white">{{ $profile->name }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route($updateRoute, [$customer, $profile]) }}" enctype="multipart/form-data" class="space-y-5 p-5">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_editing_profile" value="{{ $profile->id }}">

                    <div>
                        <label class="mb-1 block text-xs font-medium text-blue-100/90" for="name-{{ $profile->id }}">Nombre en pantalla</label>
                        <input
                            id="name-{{ $profile->id }}"
                            name="name"
                            value="{{ $isOld ? old('name', $profile->name) : $profile->name }}"
                            required
                            maxlength="100"
                            class="w-full max-w-xl rounded-lg border border-white/15 bg-[#0a1744]/80 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                            autocomplete="off"
                        >
                        @if ($isOld)
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        @endif
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-blue-100/90" for="avatar-{{ $profile->id }}">Imagen del avatar</label>
                        <p class="mb-2 text-xs text-blue-100/70">Archivo JPG, PNG, WebP o GIF (máx. 5&nbsp;MB) o URL abajo.</p>
                        <input
                            id="avatar-{{ $profile->id }}"
                            name="avatar"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            class="block w-full max-w-xl text-sm text-blue-100 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-indigo-500"
                        >
                        @if ($isOld)
                            <x-input-error :messages="$errors->get('avatar')" class="mt-1" />
                        @endif
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-blue-100/90" for="avatar_url-{{ $profile->id }}">URL del avatar</label>
                        <input
                            id="avatar_url-{{ $profile->id }}"
                            name="avatar_url"
                            type="url"
                            value="{{ $isOld ? old('avatar_url', $prevAvatar) : $prevAvatar }}"
                            maxlength="2048"
                            placeholder="https://…"
                            class="w-full max-w-3xl rounded-lg border border-white/15 bg-[#0a1744]/80 px-3 py-2 text-sm text-white placeholder:text-white/40"
                        >
                        @if ($isOld)
                            <x-input-error :messages="$errors->get('avatar_url')" class="mt-1" />
                        @endif
                    </div>

                    <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-100/90">PIN de acceso (4 dígitos)</p>
                        <p class="mt-1 text-xs text-blue-100/70">Dejá vacío si no cambiás el PIN. No puede repetirse entre espacios del mismo cliente.</p>
                        <div class="mt-4 grid max-w-2xl gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs text-blue-100/80" for="pin-{{ $profile->id }}">Nuevo PIN</label>
                                <input
                                    id="pin-{{ $profile->id }}"
                                    name="pin"
                                    type="password"
                                    inputmode="numeric"
                                    pattern="\d{4}"
                                    maxlength="4"
                                    autocomplete="new-password"
                                    class="w-full rounded-lg border border-white/15 bg-[#0a1744]/80 px-3 py-2 font-mono tracking-widest text-white"
                                    placeholder="••••"
                                >
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-blue-100/80" for="pin-conf-{{ $profile->id }}">Confirmar PIN</label>
                                <input
                                    id="pin-conf-{{ $profile->id }}"
                                    name="pin_confirmation"
                                    type="password"
                                    inputmode="numeric"
                                    pattern="\d{4}"
                                    maxlength="4"
                                    autocomplete="new-password"
                                    class="w-full rounded-lg border border-white/15 bg-[#0a1744]/80 px-3 py-2 font-mono tracking-widest text-white"
                                    placeholder="••••"
                                >
                            </div>
                        </div>
                        @if ($isOld)
                            <x-input-error :messages="$errors->get('pin')" class="mt-2" />
                            <x-input-error :messages="$errors->get('pin_confirmation')" class="mt-1" />
                        @endif
                    </div>

                    <button type="submit" class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Guardar este espacio</button>
                </form>
            </section>
        @endforeach
    </div>
</x-panel-layout>
