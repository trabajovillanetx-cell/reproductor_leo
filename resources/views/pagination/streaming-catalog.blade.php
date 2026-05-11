{{-- Catálogo cliente: misma paginación Tailwind que Laravel, sin texto "Showing X to Y of Z results". --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <p class="sr-only">
            {{ __('Pagination Navigation') }}.
            {{ __('Showing') }}
            @if ($paginator->firstItem())
                {{ $paginator->firstItem() }} {{ __('to') }} {{ $paginator->lastItem() }}
            @else
                {{ $paginator->count() }}
            @endif
            {{ __('of') }} {{ $paginator->total() }} {{ __('results') }}.
        </p>

        <div class="flex gap-2 items-center justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="inline-flex cursor-not-allowed items-center rounded-md border border-white/15 bg-white/5 px-4 py-2 text-sm font-medium leading-5 text-white/40">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center rounded-md border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium leading-5 text-white transition hover:bg-white/15">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center rounded-md border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium leading-5 text-white transition hover:bg-white/15">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="inline-flex cursor-not-allowed items-center rounded-md border border-white/15 bg-white/5 px-4 py-2 text-sm font-medium leading-5 text-white/40">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-center">
            <span class="inline-flex rtl:flex-row-reverse rounded-md shadow-sm">
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <span class="inline-flex cursor-not-allowed items-center rounded-l-md border border-white/15 bg-white/5 px-2 py-2 text-sm font-medium leading-5 text-white/40" aria-hidden="true">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center rounded-l-md border border-white/15 bg-white/10 px-2 py-2 text-sm font-medium leading-5 text-white/90 transition hover:bg-white/15" aria-label="{{ __('pagination.previous') }}">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-disabled="true">
                            <span class="-ml-px inline-flex cursor-default items-center border border-white/15 bg-white/5 px-4 py-2 text-sm font-medium leading-5 text-white/60">{{ $element }}</span>
                        </span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page">
                                    <span class="-ml-px inline-flex cursor-default items-center border border-violet-400/40 bg-violet-600/35 px-4 py-2 text-sm font-semibold leading-5 text-white">{{ $page }}</span>
                                </span>
                            @else
                                <a href="{{ $url }}" class="-ml-px inline-flex items-center border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium leading-5 text-white/90 transition hover:bg-white/18" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="-ml-px inline-flex items-center rounded-r-md border border-white/15 bg-white/10 px-2 py-2 text-sm font-medium leading-5 text-white/90 transition hover:bg-white/15" aria-label="{{ __('pagination.next') }}">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                        <span class="-ml-px inline-flex cursor-not-allowed items-center rounded-r-md border border-white/15 bg-white/5 px-2 py-2 text-sm font-medium leading-5 text-white/40" aria-hidden="true">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </span>
                @endif
            </span>
        </div>
    </nav>
@endif
