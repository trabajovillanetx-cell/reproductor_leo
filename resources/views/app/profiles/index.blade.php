@php
    use App\Support\SiteTheme;
    $profilesBgUrl = SiteTheme::profilesPickerBackgroundUrl();
    $bgSafe = $profilesBgUrl !== '' ? htmlspecialchars($profilesBgUrl, ENT_QUOTES, 'UTF-8') : '';
    $brand = config('streaming.login_brand_display', 'DIGITALVISION');
    /** Bordes rotativos tipo anillo (colores propios, no copia de marca). */
    $ringSets = [
        'ring-2 ring-cyan-400/85 ring-offset-4 ring-offset-[#050814] shadow-[0_0_28px_rgba(34,211,238,0.35)]',
        'ring-2 ring-fuchsia-400/80 ring-offset-4 ring-offset-[#050814] shadow-[0_0_28px_rgba(217,70,239,0.3)]',
        'ring-2 ring-lime-400/75 ring-offset-4 ring-offset-[#050814] shadow-[0_0_28px_rgba(163,230,53,0.28)]',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Elegir espacio — {{ config('app.name') }}</title>
    @include('partials.site-favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Si Vite no está compilado, al menos fondo oscuro + círculos legibles --}}
    <style>
        .dv-profiles-root { min-height: 100vh; min-height: 100dvh; background-color: #030712; color: #f8fafc; }
        .dv-profile-orb {
            width: 7.5rem; height: 7.5rem; border-radius: 9999px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 800; color: #fff;
            background: linear-gradient(145deg, #334155 0%, #0f172a 100%);
            box-shadow: 0 18px 50px rgba(0,0,0,.55);
            overflow: hidden;
        }
        .dv-profile-orb img { width: 100%; height: 100%; object-fit: cover; }
        @media (min-width: 640px) { .dv-profile-orb { width: 9.25rem; height: 9.25rem; font-size: 2.5rem; } }
    </style>
</head>
<body class="dv-profiles-root streaming-cyber-profiles relative overflow-x-hidden antialiased">
    @if ($bgSafe !== '')
        <div
            class="pointer-events-none fixed inset-0 z-0 bg-cover bg-center bg-no-repeat"
            style="background-image: url('{{ $bgSafe }}');"
            aria-hidden="true"
        ></div>
    @endif
    <div class="pointer-events-none fixed inset-0 z-0 bg-gradient-to-b from-[#050814]/88 via-[#080c18]/90 to-[#0a0e1a]/95" aria-hidden="true"></div>
    <div class="pointer-events-none fixed inset-0 z-0 bg-[radial-gradient(ellipse_90%_60%_at_50%_-20%,rgba(34,211,238,0.1),transparent_55%),radial-gradient(ellipse_70%_50%_at_100%_80%,rgba(192,38,211,0.08),transparent_50%)]" aria-hidden="true"></div>

    <div class="relative z-10 mx-auto flex min-h-screen min-h-[100dvh] max-w-6xl flex-col items-center px-5 py-14 sm:px-10 sm:py-20">
        <header class="w-full max-w-2xl text-center">
            <p class="text-xs font-bold uppercase tracking-[0.32em] text-cyan-200/95 drop-shadow-[0_1px_12px_rgba(0,0,0,0.55)] sm:text-sm sm:tracking-[0.38em]">{{ $brand }}</p>
            <h1 class="mt-5 text-balance text-3xl font-extrabold tracking-tight text-white drop-shadow-[0_2px_18px_rgba(0,0,0,0.45)] sm:mt-6 sm:text-4xl md:text-5xl md:leading-tight">¿Quién va a usar el catálogo?</h1>
            <p class="mt-4 text-pretty text-base font-medium leading-relaxed text-white/80 drop-shadow-sm sm:mt-5 sm:text-lg md:text-xl">Bienvenido al mejor entretenimiento</p>
        </header>

        @include('partials.flash')

        <div
            class="mt-14 flex w-full max-w-5xl flex-col items-center"
            x-data="{
                open: false,
                profileId: null,
                profileLabel: '',
                pin: '',
                focusPin() { this.$nextTick(() => this.$refs.pinInput?.focus()); },
                openFor(p, initialPin = '') {
                    this.profileId = p.id;
                    this.profileLabel = p.name;
                    const raw = typeof initialPin === 'string' ? initialPin.replace(/[^\d]/g, '') : '';
                    this.pin = raw.slice(0, 4);
                    this.open = true;
                    this.focusPin();
                },
                close() {
                    this.open = false;
                    this.profileId = null;
                    this.profileLabel = '';
                    this.pin = '';
                },
            }"
            x-init="$nextTick(() => {
@if ($errors->has('pin') && old('profile_id'))
                openFor(
                    { id: {{ (int) old('profile_id') }}, name: @json($profiles->firstWhere('id', (int) old('profile_id'))?->name ?? 'Espacio') },
                    @json((string) old('pin', ''))
                )
@endif
            })"
            @keydown.escape.window="close()"
        >
            {{-- Fila de avatares circulares (referencia tipo “elige perfil” de plataformas de streaming, diseño propio) --}}
            <div class="flex w-full flex-wrap items-start justify-center gap-x-10 gap-y-12 sm:gap-x-14 md:flex-nowrap md:justify-center md:gap-x-12 lg:gap-x-16">
                @foreach ($profiles as $i => $profile)
                    @php
                        $letter = mb_strtoupper(mb_substr(trim((string) $profile->name), 0, 1));
                        $rings = $ringSets[$i % 3];
                        $avatarUrl = trim((string) ($profile->avatar_url ?? ''));
                    @endphp
                    <button
                        type="button"
                        class="group flex w-[46%] max-w-[12rem] flex-col items-center gap-4 text-center sm:w-auto sm:max-w-none md:w-[18%] md:min-w-[8rem]"
                        @click="openFor({ id: {{ (int) $profile->id }}, name: {{ json_encode($profile->name) }} })"
                    >
                        <span class="dv-profile-orb relative ring-4 transition duration-300 {{ $rings }} group-hover:scale-[1.06] group-hover:brightness-110 sm:ring-[5px]">
                            @if ($avatarUrl !== '')
                                <img src="{{ $avatarUrl }}" alt="" loading="lazy" referrerpolicy="no-referrer">
                            @else
                                {{ $letter }}
                            @endif
                            <span class="pointer-events-none absolute inset-[6%] rounded-full border border-white/10"></span>
                        </span>
                        <span class="max-w-[11rem] truncate text-[15px] font-semibold tracking-wide text-white/92 drop-shadow group-hover:text-white">{{ $profile->name }}</span>
                    </button>
                @endforeach
            </div>

            <div x-show="open" x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center bg-black/86 p-5 backdrop-blur-md" style="display: none;">
                <div class="cyber-stack relative w-full max-w-[26rem]" @click.stop>
                    <div class="admin-cyber-card relative rounded-2xl p-9 shadow-[0_28px_70px_-12px_rgba(0,0,0,0.9)]">
                    <button
                        type="button"
                        class="absolute right-3 top-3 rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-white/80 hover:bg-white/10"
                        @click="close()"
                    >
                        Cerrar
                    </button>
                    <p class="text-center text-[10px] font-bold uppercase tracking-[0.38em] text-cyan-300/90">PIN del espacio</p>
                    <h2 class="mt-4 text-center text-lg font-semibold text-white">Acceso protegido</h2>
                    <p class="mt-2 text-center text-sm text-white/70">Introduce los 4 dígitos de <strong class="text-fuchsia-300/95" x-text="profileLabel"></strong></p>

                    @if ($errors->has('pin'))
                        <p class="mt-5 rounded-xl border border-red-500/40 bg-red-950/50 px-4 py-3 text-center text-sm text-red-100">{{ $errors->first('pin') }}</p>
                    @endif

                    <form
                        method="POST"
                        action="{{ route('app.profiles.select') }}"
                        class="mt-8 space-y-6"
                        novalidate
                        @submit="
                            const digits = String(pin ?? '').replace(/\D/g, '').slice(0, 4);
                            pin = digits;
                            if ($refs.pinInput) { $refs.pinInput.value = digits; }
                            if (digits.length !== 4) { $event.preventDefault(); }
                        "
                    >
                        @csrf
                        <input type="hidden" name="profile_id" :value="profileId">

                        <div>
                            <label for="streaming-pin-input" class="sr-only">PIN de cuatro dígitos</label>
                            <input
                                id="streaming-pin-input"
                                name="pin"
                                type="password"
                                inputmode="numeric"
                                maxlength="4"
                                x-model="pin"
                                required
                                x-ref="pinInput"
                                class="mx-auto flex h-14 w-[12.5rem] rounded-xl border-2 border-cyan-500/30 bg-black/50 px-4 text-center text-xl font-semibold tracking-[0.6em] text-white outline-none ring-0 focus:border-cyan-400/70 focus:shadow-[0_0_18px_rgba(34,211,238,0.25)]"
                                placeholder="••••"
                                autocomplete="one-time-code"
                            >
                        </div>

                        <button type="submit" class="w-full rounded-xl border border-cyan-400/35 bg-gradient-to-r from-cyan-500/25 to-fuchsia-600/25 py-4 text-[13px] font-bold uppercase tracking-[0.2em] text-cyan-50 shadow-[0_0_24px_rgba(34,211,238,0.2)] transition hover:from-cyan-500/35 hover:to-fuchsia-600/35">
                            Continuar
                        </button>
                    </form>

                    @if (session('status'))
                        <p class="mt-6 text-center text-sm text-lime-300">{{ session('status') }}</p>
                    @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
