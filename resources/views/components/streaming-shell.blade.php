@props([
    'title' => '',
])

@php
    use App\Support\SiteTheme;
    $appBgRaw = trim((string) SiteTheme::appBackgroundUrl());
    $appBgSafe = $appBgRaw !== '' ? htmlspecialchars($appBgRaw, ENT_QUOTES, 'UTF-8') : '';
    // No enlazar la búsqueda al menú: si propagamos ?q, al cambiar de sección sigue aplicado el filtro.
    $navQuery = [];
    $currentSection = $streamingSection ?? 'todas';
    $navItems = [
        ['key' => 'todas', 'label' => 'Inicio', 'icon' => 'home'],
        ['key' => 'peliculas', 'label' => 'Películas', 'icon' => 'film'],
        ['key' => 'series', 'label' => 'Series', 'icon' => 'series'],
        ['key' => 'tv', 'label' => 'TV en vivo', 'icon' => 'live'],
    ];
    $searchHref = route('app.home', ['section' => $currentSection]).'#buscar-catalogo';
    $sbProf = $streamingProfileActive ?? null;
    $sbAvatar = $sbProf && trim((string) ($sbProf->avatar_url ?? '')) !== '' ? $sbProf->avatar_url : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title !== '' ? $title.' — ' : '' }}{{ config('app.name') }}</title>
    @include('partials.site-favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Outfit', ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="streaming-cyber-shell relative min-h-full antialiased text-white">
    @if ($appBgSafe !== '')
        <div
            class="streaming-shell-bg streaming-shell-bg--photo pointer-events-none fixed inset-0 bg-cover bg-center bg-no-repeat"
            style="background-image: linear-gradient(145deg, rgba(6, 8, 20, 0.45), rgba(12, 8, 28, 0.35)), url('{{ $appBgSafe }}');"
            aria-hidden="true"
        ></div>
        <div class="streaming-shell-bg streaming-shell-bg--photo-wash pointer-events-none fixed inset-0" aria-hidden="true"></div>
    @else
        <div class="streaming-shell-bg streaming-shell-bg--deep pointer-events-none fixed inset-0" aria-hidden="true"></div>
        <div class="streaming-shell-bg streaming-shell-bg--glow pointer-events-none fixed inset-0" aria-hidden="true"></div>
    @endif

    {{-- Sin z-10: menos stacking contexts raros con overlays. El contenido va después de los fondos fijos en el DOM. --}}
    <div class="relative flex min-h-screen w-full flex-col pb-[5rem] lg:flex-row lg:pb-0 lg:pl-0 lg:pt-0">
        {{-- Rail escritorio: perfil arriba + iconos --}}
        <aside class="streaming-cyber-rail sticky top-0 z-40 hidden h-screen w-[5.25rem] shrink-0 flex-col border-r border-cyan-500/15 bg-[#060910]/90 py-4 shadow-[inset_-1px_0_0_rgba(168,85,247,0.06)] backdrop-blur-2xl lg:flex">
            <div class="shrink-0 px-2">
                <form method="POST" action="{{ route('app.profiles.switch') }}" class="flex flex-col items-center gap-1.5">
                    @csrf
                    <button
                        type="submit"
                        class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-full ring-2 ring-cyan-500/40 transition hover:ring-cyan-400/70"
                        title="Cambiar perfil: {{ $sbProf?->name ?? 'Perfil' }}"
                    >
                        @if ($sbAvatar)
                            <img src="{{ $sbAvatar }}" alt="" class="h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer">
                        @else
                            <span class="flex h-full w-full items-center justify-center bg-gradient-to-br from-violet-500 to-blue-700 text-sm font-bold text-white">
                                {{ $streamingProfileLetter ?? '?' }}
                            </span>
                        @endif
                    </button>
                    <p class="w-full max-w-[4.9rem] break-words px-0.5 text-center text-[9px] font-semibold leading-snug tracking-tight text-white/65" title="{{ $sbProf?->name ?? 'Perfil' }}">{{ $sbProf?->name ?? 'Perfil' }}</p>
                </form>
            </div>

            {{-- Iconos centrados en la altura entre perfil y salir (evita hueco vacío) --}}
            <div class="flex min-h-0 flex-1 flex-col items-center justify-center px-1 py-3">
                <nav class="flex w-full flex-col items-center gap-2" aria-label="Catálogo">
                    @include('partials.streaming-rail-nav', [
                        'navItems' => $navItems,
                        'searchHref' => $searchHref,
                        'current' => $currentSection,
                        'navQuery' => $navQuery,
                        'variant' => 'vertical',
                    ])
                </nav>
            </div>

            <div class="shrink-0 flex flex-col items-center gap-2 border-t border-cyan-500/15 px-2 pt-3 pb-2">
                <form method="POST" action="{{ route('app.profiles.switch') }}">
                    @csrf
                    <button
                        type="submit"
                        class="flex h-11 w-11 items-center justify-center rounded-xl text-white/75 transition hover:bg-white/12 hover:text-white"
                        title="Volver a elegir perfil"
                    >
                        <x-streaming-icon name="switch-user" class="h-6 w-6" />
                        <span class="sr-only">Volver a elegir perfil</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="flex h-11 w-11 items-center justify-center rounded-xl text-rose-300/90 transition hover:bg-rose-500/15 hover:text-rose-100"
                        title="Cerrar sesión"
                    >
                        <x-streaming-icon name="power" class="h-6 w-6" />
                        <span class="sr-only">Cerrar sesión</span>
                    </button>
                </form>
            </div>
        </aside>

        {{-- Rail móvil inferior --}}
        <nav
            class="streaming-cyber-rail streaming-cyber-rail--mobile fixed bottom-0 left-0 right-0 z-50 flex h-[4.85rem] min-h-[4.85rem] items-center gap-1.5 border-t border-cyan-500/20 bg-[#05080f]/95 px-1 pb-[env(safe-area-inset-bottom,0)] pt-1 shadow-[0_-12px_40px_-8px_rgba(0,0,0,0.65)] backdrop-blur-xl sm:gap-2 sm:px-2 lg:hidden"
            aria-label="Catálogo"
        >
            <form method="POST" action="{{ route('app.profiles.switch') }}" class="shrink-0">
                @csrf
                <button type="submit" class="relative flex h-11 w-11 items-center justify-center overflow-hidden rounded-full ring-2 ring-cyan-500/35" title="Cambiar perfil">
                    @if ($sbAvatar)
                        <img src="{{ $sbAvatar }}" alt="" class="h-full w-full object-cover">
                    @else
                        <span class="flex h-full w-full items-center justify-center bg-gradient-to-br from-violet-500 to-blue-700 text-sm font-bold text-white">{{ $streamingProfileLetter ?? '?' }}</span>
                    @endif
                </button>
            </form>
            <div class="min-w-0 flex-1">
                @include('partials.streaming-rail-nav', [
                    'navItems' => $navItems,
                    'searchHref' => $searchHref,
                    'current' => $currentSection,
                    'navQuery' => $navQuery,
                    'variant' => 'horizontal',
                ])
            </div>
            <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                @csrf
                <button
                    type="submit"
                    class="flex h-11 w-11 items-center justify-center rounded-xl text-rose-300/90 transition hover:bg-rose-500/15 hover:text-rose-100"
                    title="Cerrar sesión"
                >
                    <x-streaming-icon name="power" class="h-6 w-6" />
                    <span class="sr-only">Cerrar sesión</span>
                </button>
            </form>
        </nav>

        {{-- Traslúcido + blur: deja ver el fondo del catálogo; antes bg-slate-950 opaco tapaba la foto por completo. --}}
        <div class="min-w-0 flex-1 bg-slate-950/45 backdrop-blur-2xl">
            @include('partials.flash')

            {{ $slot }}
        </div>
    </div>
</body>
</html>
