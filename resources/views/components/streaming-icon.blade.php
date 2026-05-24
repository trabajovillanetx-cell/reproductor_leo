@props([
    'name',
    'class' => 'h-6 w-6 shrink-0',
])

@php
    /** @var string|false|null */
    $customRel = config("streaming_rail_icons.{$name}");
    $customRel = \is_string($customRel) && $customRel !== '' ? $customRel : null;
    $customAbs = $customRel !== null ? public_path($customRel) : null;
    $customOk = $customAbs !== null && is_file($customAbs);
@endphp

@if ($customOk)
    <img
        {{ $attributes->merge(['class' => $class.' streaming-rail-icon-img max-h-full max-w-full object-contain bg-transparent']) }}
        src="{{ asset($customRel) }}"
        alt=""
        loading="lazy"
        decoding="async"
        draggable="false"
        aria-hidden="true"
    />
@else
    @switch($name)
        @case('home')
            <svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
            </svg>
            @break
        @case('film')
            <svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="2" y="6" width="20" height="14" rx="2" ry="2"/>
                <path d="M2 10h20"/>
                <path d="M7 6V3"/>
                <path d="M12 6V3"/>
                <path d="M17 6V3"/>
                <path d="M7 10v1"/>
                <path d="M12 10v1"/>
                <path d="M17 10v1"/>
            </svg>
            @break
        @case('series')
            <svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.252v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.252v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.252m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.252v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.252" />
            </svg>
            @break
        @case('live')
            <svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
            </svg>
            @break
        @case('search')
            <svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            @break
        @case('user')
            <svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
            </svg>
            @break
        @case('star')
            <svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.681.345 1.049l-4.22 4.493a.563.563 0 0 0-.154.488l1.287 5.385a.562.562 0 0 1-.84.588l-4.8-2.528a.562.562 0 0 0-.576 0l-4.8 2.528a.562.562 0 0 1-.84-.588l1.287-5.385a.562.562 0 0 0-.154-.488L3.185 10.445a.562.562 0 0 1 .345-1.049l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
            </svg>
            @break
        @case('switch-user')
            <svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            </svg>
            @break
        @case('power')
            <svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v10" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.4 6.6a9 9 0 1 1-12.77.04" />
            </svg>
            @break
        @case('logout')
            <svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
            </svg>
            @break
        @default
            <span class="{{ $class }} block rounded bg-white/20" aria-hidden="true"></span>
    @endswitch
@endif
