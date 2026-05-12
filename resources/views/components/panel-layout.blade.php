@props([
    'title' => '',
    /** Fondo, cabecera y barra lateral al estilo cyber (unificado en todo el panel). */
    'cyber' => true,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['h-full', 'dark' => $cyber])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — ' : '' }}{{ config('app.name', 'Panel') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body @class([
    'min-h-full font-sans antialiased text-white',
    'bg-gradient-to-br from-[#0a1744] via-[#143d9e] to-[#0c184d]' => ! $cyber,
    'admin-cyber-panel-body' => $cyber,
])>
<div x-data="{ open: false }" class="flex min-h-screen flex-col overflow-x-hidden lg:flex-row">
    @include('partials.sidebar')

    <div class="flex min-h-0 min-w-0 w-full flex-1 flex-col lg:pl-64">
        <header @class([
            'sticky top-0 z-30 border-b px-3 py-3 shadow-lg backdrop-blur-md sm:px-6 sm:py-4 lg:px-8 lg:py-6',
            'border-white/15 bg-[#071231]/92 shadow-black/25' => ! $cyber,
            'admin-cyber-panel-header border-cyan-500/20 bg-[#060a14]/90 shadow-cyan-950/30' => $cyber,
        ])>
            <div class="mx-auto flex w-full max-w-[100vw] items-center gap-3 sm:gap-4">
                <button
                    type="button"
                    class="shrink-0 rounded-xl border border-white/20 bg-white/[0.07] p-2.5 text-white/90 shadow-sm transition hover:bg-white/15 lg:hidden"
                    @click="open = true"
                    aria-label="Abrir menú de navegación"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="min-w-0 flex-1 text-left sm:text-left">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-sky-300/95 sm:text-[11px] sm:tracking-[0.22em]">{{ config('app.name') }}</p>
                    <h1 class="mt-0.5 truncate text-lg font-bold tracking-tight text-white sm:mt-1.5 sm:text-xl lg:text-2xl">{{ $title }}</h1>
                </div>
                <div class="flex shrink-0 items-center gap-2 text-sm text-blue-100/90 sm:gap-3">
                    <span class="hidden max-w-[12rem] truncate rounded-lg bg-white/10 px-3 py-1.5 font-mono text-xs text-white ring-1 ring-white/15 md:inline">{{ auth()->user()->email }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-xl border-2 border-white/35 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/20 sm:px-4 sm:py-2 sm:text-sm">
                            Salir
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main @class([
            'admin-panel-main w-full max-w-[100vw] flex-1 overflow-x-hidden px-3 py-4 sm:px-6 sm:py-6 lg:px-8 lg:py-8',
            'admin-cyber-panel-main' => $cyber,
        ])>
            <div class="w-full min-w-0">
                @include('partials.flash')
                {{ $slot }}
            </div>
        </main>
    </div>
</div>
</body>
</html>
