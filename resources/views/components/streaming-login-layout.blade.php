@props([
    'title' => 'Iniciar sesión',
])

@php
    use App\Support\SiteTheme;
    $bgUrl = trim((string) SiteTheme::loginBackgroundUrl());
    $bgSafe = $bgUrl !== '' ? htmlspecialchars($bgUrl, ENT_QUOTES, 'UTF-8') : '';
    $brandDisplay = (string) config('streaming.login_brand_display', 'DIGITALVISION');
    $logoUrl = SiteTheme::loginLogoUrl();
    $logoSafe = $logoUrl !== null && $logoUrl !== '' ? htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') : '';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800|poppins:600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Outfit', ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="streaming-cyber-login relative min-h-screen min-h-[100dvh] antialiased">
    <div class="streaming-cyber-login-bg pointer-events-none fixed inset-0" aria-hidden="true"></div>
    <div class="streaming-cyber-login-wash pointer-events-none fixed inset-0" aria-hidden="true"></div>
    @if ($bgSafe !== '')
        <div
            class="pointer-events-none fixed inset-0 bg-cover bg-center bg-no-repeat opacity-[0.75] sm:opacity-[0.85]"
            style="background-image: url('{{ $bgSafe }}');"
            aria-hidden="true"
        ></div>
        <div class="pointer-events-none fixed inset-0 bg-gradient-to-t from-[#050814]/60 via-[#050814]/10 to-cyan-500/5" aria-hidden="true"></div>
    @endif

    <div class="relative z-10 flex min-h-screen min-h-[100dvh] flex-col items-center justify-center px-4 py-10 sm:px-6">
        <div class="flex w-full max-w-md flex-col items-center justify-center">
            <div class="cyber-stack w-full">
                <div class="admin-cyber-card rounded-[1.75rem] p-7 backdrop-blur-md sm:p-9">
                    @if ($logoSafe !== '')
                        <div class="mb-6 flex justify-center border-b border-white/10 pb-6 sm:mb-7 sm:pb-7">
                            <img src="{{ $logoSafe }}" alt="{{ $brandDisplay }}" class="max-h-16 w-auto max-w-[min(100%,260px)] object-contain drop-shadow-[0_4px_20px_rgba(0,0,0,0.4)] sm:max-h-20">
                        </div>
                    @else
                        <h1 class="streaming-login-brand mb-6 border-b border-white/10 pb-6 text-center font-['Poppins',ui-sans-serif,system-ui,sans-serif] text-2xl font-extrabold uppercase tracking-[0.18em] text-white drop-shadow-[0_2px_18px_rgba(15,23,42,0.5)] sm:mb-7 sm:pb-7 sm:text-3xl md:text-[2rem]">
                            {{ $brandDisplay }}
                        </h1>
                    @endif
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>

    @stack('body')
</body>
</html>
