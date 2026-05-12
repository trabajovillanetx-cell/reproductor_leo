@php
    $u = auth()->user();
    $navInactive = 'text-center text-white/85 hover:bg-white/10 hover:text-white';
    $navActive = 'text-center bg-white text-[#0a1744] shadow-lg shadow-black/25';
@endphp

<div class="lg:fixed lg:inset-y-0 lg:z-40 lg:flex lg:h-screen lg:w-64 lg:flex-col lg:overflow-hidden">
    <aside
        :class="open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="fixed inset-y-0 left-0 z-50 flex h-full w-[min(100vw,18rem)] max-w-[18rem] min-h-0 flex-col border-r border-white/10 bg-gradient-to-b from-[#050f2e] via-[#0a1f54] to-[#0e2a72] shadow-2xl shadow-black/40 transition-transform duration-200 ease-out lg:static lg:z-auto lg:h-full lg:w-64 lg:max-w-none lg:flex-1 lg:translate-x-0 lg:shadow-none"
        @click="if ($event.target.closest('a')) { open = false }"
    >
        <div class="flex shrink-0 items-center justify-between gap-2 border-b border-white/10 px-3 py-4 sm:px-4">
            <div class="min-w-0 flex-1 text-center lg:block">
                <a href="{{ route('dashboard') }}" class="block truncate text-lg font-bold leading-tight tracking-wide text-white drop-shadow-sm sm:text-xl" @click="open = false">{{ config('app.name') }}</a>
                <span class="mt-1 block text-[10px] font-semibold uppercase tracking-[0.35em] text-sky-300/80">Panel</span>
            </div>
            <button
                type="button"
                class="shrink-0 rounded-lg p-2 text-white/85 hover:bg-white/10 lg:hidden"
                @click="open = false"
                aria-label="Cerrar menú"
            >
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <nav class="min-h-0 flex-1 space-y-1.5 overflow-y-auto overflow-x-hidden overscroll-y-contain p-3 pb-8 text-sm font-semibold [scrollbar-gutter:stable]">
            @if ($u->role->value === 'admin')
                <p class="px-3 pb-2 pt-2 text-center text-[11px] font-bold uppercase tracking-wider text-blue-300/75">Administración</p>
                <a href="{{ route('admin.dashboard') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.dashboard') ? $navActive : $navInactive }}">Dashboard</a>
                <a href="{{ route('admin.plans.index') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.plans.*') ? $navActive : $navInactive }}">Planes</a>
                <a href="{{ route('admin.resellers.index') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.resellers.*') ? $navActive : $navInactive }}">Revendedores</a>
                <a href="{{ route('admin.vendors.index') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.vendors.*') ? $navActive : $navInactive }}">Vendedores</a>
                <a href="{{ route('admin.customers.index') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.customers.*') ? $navActive : $navInactive }}">Clientes</a>
                <a href="{{ route('admin.categories.index') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.categories.*') ? $navActive : $navInactive }}">Categorías</a>
                <a href="{{ route('admin.contents.index') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.contents.*') ? $navActive : $navInactive }}">Contenido</a>
                <a href="{{ route('admin.m3u.import') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.m3u.import') || request()->routeIs('admin.m3u.import.store') ? $navActive : $navInactive }}">Importar M3U</a>
                <a href="{{ route('admin.m3u.manage') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.m3u.manage') ? $navActive : $navInactive }}">Gestión listas M3U (borrar)</a>
                <a href="{{ route('admin.xtream.index') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.xtream.*') ? $navActive : $navInactive }}">Xtream Codes (API)</a>
                <a href="{{ route('admin.diagnostics.channels') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.diagnostics.channels*') ? $navActive : $navInactive }}">Diagnóstico streams</a>
                <a href="{{ route('admin.active-sessions.index') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.active-sessions.*') ? $navActive : $navInactive }}">Sesiones activas</a>
                <a href="{{ route('admin.library.raidrive') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.library.raidrive') ? $navActive : $navInactive }}">Biblioteca local</a>
                <a href="{{ route('admin.library.folders.index') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.library.folders.*') ? $navActive : $navInactive }}">Carpetas del catálogo</a>
                <a href="{{ route('admin.library.folder-posters.index') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.library.folder-posters.*') ? $navActive : $navInactive }}">Carátulas de carpetas</a>
                <a href="{{ route('admin.theme-assets.edit') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('admin.theme-assets.*') || request()->routeIs('admin.streaming-appearance.*') ? $navActive : $navInactive }}">Imágenes del sitio</a>
            @elseif ($u->role->value === 'reseller')
                <p class="px-3 pb-2 pt-2 text-center text-[11px] font-bold uppercase tracking-wider text-blue-300/75">Revendedor</p>
                <a href="{{ route('reseller.dashboard') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('reseller.dashboard') ? $navActive : $navInactive }}">Dashboard</a>
                <a href="{{ route('reseller.network.index') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('reseller.network.*') ? $navActive : $navInactive }}">Mi red</a>
                <a href="{{ route('reseller.customers.index') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('reseller.customers.*') ? $navActive : $navInactive }}">Mis clientes</a>
            @elseif ($u->role->value === 'vendor')
                <p class="px-3 pb-2 pt-2 text-center text-[11px] font-bold uppercase tracking-wider text-blue-300/75">Vendedor</p>
                <a href="{{ route('vendor.dashboard') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('vendor.dashboard') ? $navActive : $navInactive }}">Dashboard</a>
                <a href="{{ route('vendor.customers.index') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('vendor.customers.*') ? $navActive : $navInactive }}">Mis clientes</a>
            @else
                <p class="px-3 pb-2 pt-2 text-center text-[11px] font-bold uppercase tracking-wider text-blue-300/75">Cliente</p>
                <a href="{{ route('app.home') }}" class="block rounded-xl px-3 py-3 transition {{ request()->routeIs('app.home') ? $navActive : $navInactive }}">Inicio</a>
                <a href="{{ route('app.plan_expired') }}" class="block rounded-xl px-3 py-3 text-center text-white/60 transition hover:bg-white/10 hover:text-white/90">Estado del plan</a>
            @endif

            <div class="mt-6 border-t border-white/15 pt-4">
                <a href="{{ route('profile.edit') }}" class="{{ $navInactive }} block rounded-xl px-3 py-3">Perfil</a>
            </div>
        </nav>
    </aside>

    <div
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-black/60 backdrop-blur-[2px] lg:hidden"
        @click="open = false"
    ></div>
</div>
