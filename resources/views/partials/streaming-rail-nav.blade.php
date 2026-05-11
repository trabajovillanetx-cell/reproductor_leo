@php
    $isVertical = ($variant ?? 'vertical') === 'vertical';
    $wrapperClass = $isVertical
        ? 'streaming-rail-stack flex flex-col items-center gap-2.5 px-1'
        : 'streaming-rail-stack--horizontal flex min-w-0 flex-1 flex-nowrap items-center justify-center gap-1 overflow-x-auto overflow-y-visible [-ms-overflow-style:none] py-1 [scrollbar-width:none] sm:gap-1.5 [&::-webkit-scrollbar]:hidden';
    $navQuery = $navQuery ?? [];

    $tileInactive = $isVertical
        ? 'border-white/[0.14] bg-gradient-to-b from-white/[0.24] to-white/[0.08] text-white/95 hover:border-cyan-400/35 hover:from-white/[0.30] hover:to-white/[0.12]'
        : 'border-white/[0.12] bg-gradient-to-b from-white/[0.20] to-white/[0.07] text-white/90 hover:border-cyan-400/30 hover:from-white/[0.26] hover:to-white/[0.10]';

    $tileActive = $isVertical
        ? 'streaming-rail-tile-active border-cyan-400/50 bg-gradient-to-b from-cyan-500/[0.32] to-violet-900/[0.22] ring-2 ring-cyan-400/55 text-white'
        : 'streaming-rail-tile-active border-cyan-400/45 bg-gradient-to-b from-cyan-500/[0.28] to-violet-900/[0.2] ring-2 ring-cyan-400/50 text-white';

    $tileBase = 'streaming-rail-tile isolate flex shrink-0 items-center justify-center rounded-2xl border shadow-[inset_0_1px_0_rgba(255,255,255,0.16)] backdrop-blur-sm transition duration-200 ease-out';
    $tileSize = $isVertical ? 'size-[3.875rem]' : 'size-11 shrink-0 sm:size-12';
    $icClass = $isVertical ? 'h-10 w-10' : 'h-9 w-9 sm:h-10 sm:w-10';
@endphp

<div class="{{ $wrapperClass }}">
    @foreach ($navItems as $row)
        @php
            $active = $current === $row['key'];
            $href = route('app.home', array_merge($navQuery, ['section' => $row['key']]));
            $classes = $tileBase.' '.$tileSize.' '.($active ? $tileActive : $tileInactive);
        @endphp
        <a href="{{ $href }}" class="{{ $classes }}" title="{{ $row['label'] }}" aria-current="{{ $active ? 'page' : 'false' }}">
            <x-streaming-icon :name="$row['icon']" class="{{ $icClass }}" />
            <span class="sr-only">{{ $row['label'] }}</span>
        </a>
    @endforeach

    @php
        $sClass = $tileBase.' '.$tileSize.' '.$tileInactive;
    @endphp
    <a href="{{ $searchHref }}" class="{{ $sClass }}" title="Buscar en el catálogo">
        <x-streaming-icon name="search" class="{{ $icClass }}" />
        <span class="sr-only">Buscar</span>
    </a>
</div>
