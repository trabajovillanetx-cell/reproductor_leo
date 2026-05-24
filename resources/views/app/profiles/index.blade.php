@php
    use App\Support\SiteTheme;
    $brand = config('streaming.login_brand_display', 'DIGITALVISION');
    $profileColors = [
        ['bg' => 'linear-gradient(145deg,#3b5bdb,#1e3a8a)', 'shadow' => '0 8px 32px rgba(59,91,219,0.5)'],
        ['bg' => 'linear-gradient(145deg,#7c3aed,#4c1d95)', 'shadow' => '0 8px 32px rgba(124,58,237,0.5)'],
        ['bg' => 'linear-gradient(145deg,#db2777,#831843)', 'shadow' => '0 8px 32px rgba(219,39,119,0.5)'],
        ['bg' => 'linear-gradient(145deg,#d97706,#92400e)', 'shadow' => '0 8px 32px rgba(217,119,6,0.5)'],
        ['bg' => 'linear-gradient(145deg,#059669,#064e3b)', 'shadow' => '0 8px 32px rgba(5,150,105,0.5)'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Elegir perfil — {{ config('app.name') }}</title>
    @include('partials.site-favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            background: linear-gradient(160deg, #0d0221 0%, #1a0533 30%, #0a1628 60%, #050814 100%);
            color: #fff;
            overflow-x: hidden;
        }
        .profiles-bg-orbs {
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
        }
        .profiles-bg-orbs::before {
            content: '';
            position: absolute;
            top: -20%; left: -10%;
            width: 60%; height: 60%;
            background: radial-gradient(ellipse, rgba(124,58,237,0.18) 0%, transparent 70%);
            border-radius: 50%;
        }
        .profiles-bg-orbs::after {
            content: '';
            position: absolute;
            bottom: -20%; right: -10%;
            width: 55%; height: 55%;
            background: radial-gradient(ellipse, rgba(34,211,238,0.12) 0%, transparent 70%);
            border-radius: 50%;
        }
        .profile-card {
            position: relative;
            width: 160px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        .profile-card:hover { transform: translateY(-8px); }
        .profile-avatar {
            width: 140px; height: 140px;
            border-radius: 28px;
            display: flex; align-items: center; justify-content: center;
            font-size: 3rem; font-weight: 800; color: #fff;
            position: relative;
            overflow: hidden;
            border: 2px solid rgba(255,255,255,0.15);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .profile-card:hover .profile-avatar {
            border-color: rgba(255,255,255,0.4);
        }
        .profile-avatar img {
            width: 100%; height: 100%; object-fit: cover;
        }
        .profile-lock {
            position: absolute;
            bottom: 8px; right: 8px;
            width: 28px; height: 28px;
            background: rgba(0,0,0,0.65);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .profile-lock svg {
            width: 15px; height: 15px; color: #fff;
        }
        .profile-name {
            font-size: 15px; font-weight: 600;
            color: rgba(255,255,255,0.9);
            text-align: center;
            max-width: 150px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        @media (max-width: 640px) {
            .profile-avatar { width: 100px; height: 100px; border-radius: 22px; font-size: 2.2rem; }
            .profile-card { width: 110px; gap: 10px; }
            .profile-name { font-size: 13px; }
        }
    </style>
</head>
<body class="relative antialiased">
    <div class="profiles-bg-orbs"></div>

    <div class="relative z-10 flex min-h-screen min-h-[100dvh] flex-col items-center justify-center px-5 py-14">

        {{-- Header --}}
        <div class="mb-10 text-center">
            <p class="text-xs font-bold uppercase tracking-[0.35em] text-purple-300/90 mb-3">{{ $brand }}</p>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-white mb-2" style="text-shadow: 0 0 40px rgba(124,58,237,0.4);">
                ¿Quién eres?
            </h1>
            <p class="text-white/60 text-sm">Elige tu perfil para continuar</p>
        </div>

        @include('partials.flash')

        {{-- Perfiles --}}
        <div
            class="flex flex-wrap items-center justify-center gap-10 sm:gap-14"
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
            @foreach ($profiles as $i => $profile)
                @php
                    $letter = mb_strtoupper(mb_substr(trim((string) $profile->name), 0, 1));
                    $color = $profileColors[$i % count($profileColors)];
                    $avatarUrl = trim((string) ($profile->avatar_url ?? ''));
                @endphp
                <button
                    type="button"
                    class="profile-card"
                    @click="openFor({ id: {{ (int) $profile->id }}, name: {{ json_encode($profile->name) }} })"
                >
                    <div
                        class="profile-avatar"
                        style="background: {{ $color['bg'] }}; box-shadow: {{ $color['shadow'] }};"
                    >
                        @if ($avatarUrl !== '')
                            <img src="{{ $avatarUrl }}" alt="{{ $profile->name }}" loading="lazy">
                        @else
                            {{ $letter }}
                        @endif
                        {{-- Ícono candado --}}
                        <div class="profile-lock">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                        </div>
                    </div>
                    <span class="profile-name">{{ $profile->name }}</span>
                </button>
            @endforeach

            {{-- Modal PIN --}}
            <div x-show="open" x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-5 backdrop-blur-md" style="display: none;">
                <div class="relative w-full mx-auto rounded-2xl border border-white/10 p-8 shadow-2xl" style="max-width: 22rem; background: linear-gradient(145deg, #1a0533, #0a1628);" @click.stop>
                    <button
                        type="button"
                        class="absolute right-3 top-3 rounded-lg border border-white/15 px-3 py-1.5 text-xs font-semibold text-white/70 hover:bg-white/10"
                        @click="close()"
                    >Cerrar</button>

                    <div class="mb-5 flex flex-col items-center gap-2">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl" style="background: linear-gradient(145deg,#7c3aed,#3b5bdb);">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                        </div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.3em] text-purple-300/90">PIN del perfil</p>
                        <h2 class="text-lg font-bold text-white">Acceso protegido</h2>
                        <p class="text-center text-sm text-white/60">Introduce el PIN de <strong class="text-purple-300" x-text="profileLabel"></strong></p>
                    </div>

                    @if ($errors->has('pin'))
                        <p class="mb-4 rounded-xl border border-red-500/40 bg-red-950/50 px-4 py-3 text-center text-sm text-red-100">{{ $errors->first('pin') }}</p>
                    @endif

                    <form
                        method="POST"
                        action="{{ route('app.profiles.select') }}"
                        class="space-y-4"
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
                        <input
                            id="streaming-pin-input"
                            name="pin"
                            type="password"
                            inputmode="numeric"
                            maxlength="4"
                            x-model="pin"
                            required
                            x-ref="pinInput"
                            class="mx-auto flex h-14 w-full rounded-xl border-2 border-purple-500/30 bg-black/40 px-4 text-center text-2xl font-bold tracking-[0.6em] text-white outline-none focus:border-purple-400/70 focus:shadow-[0_0_20px_rgba(124,58,237,0.3)]"
                            placeholder="••••"
                            autocomplete="one-time-code"
                        >
                        <button type="submit" class="w-full rounded-xl py-3.5 text-sm font-bold uppercase tracking-[0.2em] text-white transition hover:brightness-110" style="background: linear-gradient(90deg,#7c3aed,#3b5bdb);">
                            Entrar
                        </button>
                    </form>

                    @if (session('status'))
                        <p class="mt-4 text-center text-sm text-green-300">{{ session('status') }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Footer logout --}}
        <div class="mt-16 text-center">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-white/40 hover:text-white/70 transition">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</body>
</html>
