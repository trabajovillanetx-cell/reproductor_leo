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
<div class="flex min-h-screen">
    @include('partials.sidebar')

    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
        <header @class([
            'sticky top-0 z-30 border-b px-4 py-5 shadow-lg backdrop-blur-md sm:px-8 sm:py-7',
            'border-white/15 bg-[#071231]/92 shadow-black/25' => ! $cyber,
            'admin-cyber-panel-header border-cyan-500/20 bg-[#060a14]/90 shadow-cyan-950/30' => $cyber,
        ])>
            <div class="mx-auto flex w-full flex-wrap items-center justify-between gap-4">
                <div class="min-w-0 flex-1 text-center sm:text-left">
                    <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-sky-300/95">{{ config('app.name') }}</p>
                    <h1 class="mt-1.5 truncate text-xl font-bold tracking-tight text-white sm:text-2xl">{{ $title }}</h1>
                </div>
                <div class="flex w-full shrink-0 flex-wrap items-center justify-center gap-3 text-sm text-blue-100/90 sm:w-auto sm:justify-end">
                    <span class="hidden rounded-lg bg-white/10 px-3 py-1.5 font-mono text-xs text-white ring-1 ring-white/15 sm:inline">{{ auth()->user()->email }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-xl border-2 border-white/35 bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/20">
                            Salir
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main @class([
            'admin-panel-main flex-1 p-5 sm:p-7 lg:p-8',
            'admin-cyber-panel-main' => $cyber,
        ])>
            <div class="w-full">
                @include('partials.flash')
                {{ $slot }}
            </div>
        </main>
    </div>
</div>
</body>
</html>
