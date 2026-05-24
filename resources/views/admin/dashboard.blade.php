@php
    $now = now()->locale('es');
    $monthStart = $now->copy()->startOfMonth();
    $daysInMonth = $monthStart->daysInMonth;
    $firstPad = (int) $monthStart->format('w');
@endphp

<x-panel-layout title="Dashboard">
    <div class="cyber-stack admin-cyber-dashboard space-y-6 text-white sm:space-y-8">
        {{-- Cabecera estilo consola --}}
        <div class="flex flex-col gap-4 border-b border-cyan-500/20 pb-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0 text-center lg:text-left">
                <p class="text-[11px] font-bold uppercase tracking-[0.35em] text-cyan-300/90">Panel / <span class="text-fuchsia-300/95">Dashboard</span></p>
                <h2 class="mt-2 text-xl font-black tracking-tight text-white drop-shadow-[0_0_18px_rgba(0,255,255,0.25)] sm:text-2xl sm:text-3xl">Resumen operativo</h2>
                <p class="mt-1 max-w-xl text-sm text-white/55">Biblioteca, clientes y actividad reciente en un solo vistazo.</p>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end">
                <a href="{{ route('admin.contents.index') }}" class="rounded-lg border border-cyan-400/40 bg-cyan-500/15 px-3 py-2.5 text-center text-[11px] font-bold uppercase tracking-wide text-cyan-100 shadow-[0_0_20px_-4px_rgba(34,211,238,0.45)] transition hover:bg-cyan-400/25 sm:px-4 sm:text-xs">Contenido</a>
                <a href="{{ route('admin.m3u.import') }}" class="rounded-lg border border-fuchsia-400/35 bg-fuchsia-500/10 px-3 py-2.5 text-center text-[11px] font-bold uppercase tracking-wide text-fuchsia-100 transition hover:bg-fuchsia-500/20 sm:px-4 sm:text-xs">Importar M3U</a>
                <a href="{{ route('admin.library.raidrive') }}" class="col-span-2 rounded-lg border border-lime-400/30 bg-lime-500/10 px-3 py-2.5 text-center text-[11px] font-bold uppercase tracking-wide text-lime-100 transition hover:bg-lime-500/20 sm:col-span-1 sm:px-4 sm:text-xs">Biblioteca local</a>
                <form method="POST" action="{{ route('admin.library.sync') }}" class="col-span-2 sm:col-span-1">
                    @csrf
                    <button type="submit" class="w-full rounded-lg border border-emerald-400/40 bg-emerald-500/15 px-3 py-2.5 text-center text-[11px] font-bold uppercase tracking-wide text-emerald-100 shadow-[0_0_20px_-4px_rgba(52,211,153,0.45)] transition hover:bg-emerald-400/25 sm:px-4 sm:text-xs">⟳ Sincronizar biblioteca</button>
                </form>
            </div>
        </div>

        {{-- Menú rápido solo móvil / tablet (complementa el hamburguesa del lateral) --}}
        <details class="admin-dashboard-mobile-menu rounded-2xl border border-cyan-500/20 bg-[#070d1c]/95 shadow-[0_0_24px_-8px_rgba(34,211,238,0.2)] lg:hidden">
            <summary class="cursor-pointer select-none list-none rounded-2xl px-4 py-3.5 text-center text-sm font-bold text-cyan-100 transition hover:bg-white/[0.06]">
                Menú rápido del panel
            </summary>
            <div class="grid grid-cols-2 gap-2 border-t border-white/10 p-3 sm:grid-cols-3">
                <a href="{{ route('admin.customers.index') }}" class="rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-center text-[11px] font-semibold text-white/90 transition hover:bg-white/10">Clientes</a>
                <a href="{{ route('admin.categories.index') }}" class="rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-center text-[11px] font-semibold text-white/90 transition hover:bg-white/10">Categorías</a>
                <a href="{{ route('admin.plans.index') }}" class="rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-center text-[11px] font-semibold text-white/90 transition hover:bg-white/10">Planes</a>
                <a href="{{ route('admin.resellers.index') }}" class="rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-center text-[11px] font-semibold text-white/90 transition hover:bg-white/10">Revendedores</a>
                <a href="{{ route('admin.xtream.index') }}" class="rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-center text-[11px] font-semibold text-white/90 transition hover:bg-white/10">Xtream</a>
                <a href="{{ route('admin.diagnostics.channels') }}" class="rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-center text-[11px] font-semibold text-white/90 transition hover:bg-white/10">Diagnóstico</a>
                <a href="{{ route('admin.library.folders.index') }}" class="col-span-2 rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-center text-[11px] font-semibold text-white/90 transition hover:bg-white/10 sm:col-span-1">Carpetas catálogo</a>
                <a href="{{ route('admin.m3u.manage') }}" class="col-span-2 rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-center text-[11px] font-semibold text-white/90 transition hover:bg-white/10 sm:col-span-2">Gestión M3U</a>
            </div>
        </details>

        {{-- Resumen: donuts siempre ancho completo (evita 3 columnas en media fila con sidebar); debajo clientes + biblioteca --}}
        <div class="flex min-w-0 flex-col gap-4 lg:gap-5">
            @php
                $rings = [
                    [
                        'label' => 'Contenido total',
                        'value' => $totalContent,
                        'pct' => min(100, max(0, (int) $pctContentVod)),
                        'arc' => 'VOD (archivos) / ítems',
                        'from' => '#22d3ee',
                        'to' => '#c026d3',
                    ],
                    [
                        'label' => 'Clientes activos',
                        'value' => $activeCustomers,
                        'pct' => min(100, max(0, (int) $pctCustomersActive)),
                        'arc' => 'Activos del pool',
                        'from' => '#4ade80',
                        'to' => '#06b6d4',
                    ],
                    [
                        'label' => 'Contenido activo',
                        'value' => $contentActive,
                        'pct' => min(100, max(0, (int) $pctContentActive)),
                        'arc' => 'Publicado activo',
                        'from' => '#fbbf24',
                        'to' => '#fb7185',
                    ],
                ];
            @endphp
            <div class="grid min-w-0 grid-cols-1 gap-3 min-[420px]:grid-cols-3 sm:gap-4">
            @foreach ($rings as $r)
                <div class="admin-cyber-card flex min-w-0 flex-col items-center justify-center gap-2 p-4 sm:p-5">
                    <div
                        class="admin-cyber-donut admin-cyber-donut--dashboard-sm"
                        style="--p: {{ $r['pct'] }}; --from: {{ $r['from'] }}; --to: {{ $r['to'] }};"
                        role="img"
                        aria-label="{{ $r['label'] }}: {{ number_format($r['value']) }}, indicador {{ $r['pct'] }} por ciento"
                    >
                        <div class="admin-cyber-donut-inner">
                            <span class="admin-cyber-donut-value">{{ number_format($r['value']) }}</span>
                            <span class="admin-cyber-donut-sublabel">{{ $r['label'] }}</span>
                        </div>
                    </div>
                    <p class="max-w-full break-words px-1 text-center text-[9px] font-bold uppercase leading-tight tracking-[0.12em] text-cyan-200/50">{{ $r['arc'] }} · <span class="tabular-nums text-fuchsia-200/80">{{ $r['pct'] }}%</span></p>
                </div>
            @endforeach
            </div>

            <div class="grid min-w-0 grid-cols-1 gap-4 lg:grid-cols-12 lg:gap-5">
                <div class="admin-cyber-card flex min-w-0 flex-col justify-center p-5 sm:p-6 lg:col-span-4 xl:col-span-3">
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-fuchsia-300/80">Clientes</p>
                    <p class="mt-2 text-4xl font-black tabular-nums leading-none text-cyan-300" style="text-shadow: 0 0 24px rgba(34, 211, 238, 0.35);">{{ $pctCustomersActive }}%</p>
                    <p class="mt-3 text-xs leading-relaxed text-white/50">Porcentaje de clientes <span class="text-white/75">activos</span> sobre activos + vencidos + suspendidos.</p>
                    <div class="mt-4 grid grid-cols-3 gap-2 text-center text-[10px]">
                        <div class="rounded border border-white/10 bg-black/30 py-2">
                            <span class="block font-bold text-emerald-300">{{ $activeCustomers }}</span>
                            <span class="text-white/40">Act.</span>
                        </div>
                        <div class="rounded border border-white/10 bg-black/30 py-2">
                            <span class="block font-bold text-amber-300">{{ $expiredCustomers }}</span>
                            <span class="text-white/40">Venc.</span>
                        </div>
                        <div class="rounded border border-white/10 bg-black/30 py-2">
                            <span class="block font-bold text-rose-300">{{ $suspendedCustomers }}</span>
                            <span class="text-white/40">Susp.</span>
                        </div>
                    </div>
                </div>

                <div class="admin-cyber-card min-w-0 p-5 sm:p-6 lg:col-span-8 xl:col-span-9">
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-cyan-300/80">Biblioteca por tipo</p>
                    <p class="mt-1 text-xs text-white/45">Cada métrica usa su unidad: <span class="text-white/60">archivos</span> (VOD), <span class="text-white/60">canales</span> (TV), <span class="text-white/60">episodios</span> (filas de serie). El % es sobre el total de filas en catálogo ({{ number_format($totalContent) }}).</p>
                    <div class="mt-5 space-y-4 sm:space-y-3">
                        @foreach ([
                            ['l' => 'VOD — archivos (local + remotos)', 'n' => $contentVodTotal, 'p' => $pctContentVod, 'u' => 'archivos', 'c' => 'from-cyan-400 to-sky-500', 'g' => 'admin-cyber-bar-fill--cyan', 'extra' => null],
                            ['l' => 'TV en vivo — canales', 'n' => $contentLive, 'p' => $pctContentLive, 'u' => 'canales', 'c' => 'from-lime-400 to-emerald-500', 'g' => 'admin-cyber-bar-fill--lime', 'extra' => null],
                            ['l' => 'Series — episodios (filas)', 'n' => $contentSeries, 'p' => $pctContentSeries, 'u' => 'episodios', 'c' => 'from-fuchsia-500 to-violet-600', 'g' => 'admin-cyber-bar-fill--magenta', 'extra' => $contentSeriesDistinctFolders > 0 ? '≈ '.number_format($contentSeriesDistinctFolders).' series (carpetas distintas con import local)' : null],
                            ['l' => 'Activos en catálogo — filas', 'n' => $contentActive, 'p' => $pctContentActive, 'u' => 'filas activas', 'c' => 'from-amber-400 to-orange-500', 'g' => 'admin-cyber-bar-fill--amber', 'extra' => null],
                        ] as $bar)
                            <div>
                                <div class="mb-1 flex flex-col gap-1 text-[11px] font-semibold text-white/70 sm:flex-row sm:items-start sm:justify-between sm:gap-2">
                                    <span class="min-w-0 leading-snug">{{ $bar['l'] }}</span>
                                    <span class="shrink-0 tabular-nums sm:text-right">
                                        <span class="text-white/90">{{ number_format($bar['n']) }}</span>
                                        <span class="text-white/50"> {{ $bar['u'] }}</span>
                                        <span class="text-white/35"> / {{ number_format($totalContent) }}</span>
                                        <span class="ml-1.5 text-cyan-200/90">({{ $bar['p'] }}%)</span>
                                    </span>
                                </div>
                                @if (! empty($bar['extra']))
                                    <p class="mb-1 text-[10px] leading-snug text-fuchsia-200/55">{{ $bar['extra'] }}</p>
                                @endif
                                <div class="admin-cyber-bar-track">
                                    <div class="admin-cyber-bar-fill {{ $bar['g'] }} h-full rounded-full bg-gradient-to-r {{ $bar['c'] }}" style="width: {{ min(100, $bar['p']) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Fila 2: métricas secundarias en tarjetas compactas --}}
        <div>
            <p class="mb-3 text-[10px] font-bold uppercase tracking-[0.28em] text-white/40">Inventario y cuentas</p>
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-6">
                @foreach ([
                    [
                        't' => 'Archivos VOD (local)',
                        'v' => $contentVodLocal,
                        'unit' => 'archivos',
                        'ac' => 'text-cyan-200',
                        'hint' => $contentVodRemote > 0
                            ? 'Otras '.number_format($contentVodRemote).' filas VOD usan URL remota (listas M3U, etc.). Acá: solo local: (un archivo = una fila; extras/BDMV no se suman en import recursiva nueva).'
                            : 'Un archivo importado = una fila. Import recursiva ignora extras y BDMV/STREAM.',
                    ],
                    [
                        't' => 'Canales TV en vivo',
                        'v' => $contentLive,
                        'unit' => 'canales',
                        'ac' => 'text-emerald-300',
                        'hint' => 'Cada fila del catálogo tipo en vivo es un canal (URL o stream).',
                    ],
                    [
                        't' => 'Episodios (series)',
                        'v' => $contentSeries,
                        'unit' => 'episodios',
                        'ac' => 'text-fuchsia-300',
                        'hint' => $contentSeriesDistinctFolders > 0
                            ? '≈ '.number_format($contentSeriesDistinctFolders).' agrupaciones por carpeta (series estimadas en biblioteca local). Listas M3U sin carpeta: cada fila sigue siendo un episodio/ítem.'
                            : 'Cada fila es un episodio o ítem de serie. Si importás series por RaiDrive con subcarpetas, también verás “series” por carpeta en la barra de Biblioteca.',
                    ],
                    ['t' => 'Inactivos', 'v' => $contentInactive, 'unit' => 'filas', 'ac' => 'text-white/50', 'hint' => null],
                    ['t' => 'Usuarios', 'v' => $totalUsers, 'unit' => 'cuentas', 'ac' => 'text-white', 'hint' => null],
                    ['t' => 'Revendedores', 'v' => $totalResellers, 'unit' => 'cuentas', 'ac' => 'text-sky-300', 'hint' => null],
                    ['t' => 'Vendedores', 'v' => $totalVendors, 'unit' => 'cuentas', 'ac' => 'text-violet-300', 'hint' => null],
                    ['t' => 'Carátulas carpeta', 'v' => $folderPosterOverrides, 'unit' => 'registros', 'ac' => 'text-lime-300', 'hint' => null],
                ] as $cell)
                    <div class="admin-cyber-card px-3 py-3 sm:px-4 sm:py-4">
                        <p class="text-[9px] font-bold uppercase leading-tight tracking-wider text-white/40 sm:text-[10px]">{{ $cell['t'] }}</p>
                        <p class="mt-1 break-words text-xl font-black tabular-nums sm:text-2xl {{ $cell['ac'] }}">{{ number_format($cell['v']) }} <span class="text-sm font-bold text-white/35 sm:text-base">{{ $cell['unit'] ?? '' }}</span></p>
                        @if (! empty($cell['hint']))
                            <p class="mt-2 text-[10px] leading-snug text-white/35">{{ $cell['hint'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Fila 3: actividad + calendario + mini timeline de ratios --}}
        <div class="grid gap-4 sm:gap-6 lg:grid-cols-12">
            <div class="admin-cyber-card overflow-hidden lg:col-span-5">
                <div class="border-b border-white/10 bg-gradient-to-r from-fuchsia-900/30 to-transparent px-5 py-3">
                    <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-fuchsia-200/90">Cuentas por vencer</h3>
                    <p class="mt-1 text-[10px] leading-snug text-white/40">Clientes con suscripción que vence en los próximos <span class="font-semibold text-fuchsia-100/80">3 días</span>. Fecha límite según zona <span class="font-mono text-fuchsia-100/70">{{ config('app.timezone') }}</span> y la regla de fin de día de la app.</p>
                </div>
                <ul class="max-h-[22rem] divide-y divide-white/10 overflow-y-auto text-sm">
                    @forelse ($customersNearExpiry as $cu)
                        @php
                            $endBoundary = \App\Support\SubscriptionTime::inclusiveEndBoundary($cu->expires_at);
                            $endEs = $endBoundary?->copy()->locale('es');
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 transition hover:bg-white/[0.04]">
                            <div class="min-w-0 flex-1">
                                <span class="font-semibold text-white/95">{{ $cu->email }}</span>
                                @if (filled($cu->name))
                                    <span class="mt-0.5 block truncate text-[11px] text-white/55">{{ $cu->name }}</span>
                                @endif
                                <span class="mt-0.5 block text-[11px] text-white/45">
                                    <span class="font-medium text-amber-200/90">{{ $cu->status === \App\Enums\UserStatus::Suspended ? 'Suspendido' : 'Activo' }}</span>
                                    @if ($endEs)
                                        <span class="text-white/35"> · </span>
                                        <span class="text-white/55">Vence el {{ $endEs->translatedFormat('d \d\e F \d\e Y, H:i') }}</span>
                                        <span class="text-white/35"> ({{ $endEs->diffForHumans(now(\App\Support\SubscriptionTime::timezone())) }})</span>
                                    @endif
                                </span>
                                @if ($cu->parent)
                                    <span class="mt-0.5 block text-[10px] text-white/30">Revendedor / padre: {{ $cu->parent->email }}</span>
                                @endif
                            </div>
                            <a href="{{ route('admin.customers.edit', $cu) }}" class="shrink-0 self-center rounded border border-cyan-400/30 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-cyan-200 hover:bg-cyan-400/15">Renovar</a>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-white/45">No hay cuentas que venzan en los próximos 3 días.</li>
                    @endforelse
                </ul>
            </div>

            <div class="admin-cyber-card p-5 lg:col-span-4">
                <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-cyan-200/90">{{ $now->translatedFormat('F Y') }}</h3>
                <div class="mt-4 grid grid-cols-7 gap-1 text-center text-[10px] font-bold uppercase text-white/35">
                    @foreach (['L', 'M', 'X', 'J', 'V', 'S', 'D'] as $wd)
                        <span>{{ $wd }}</span>
                    @endforeach
                </div>
                <div class="mt-2 grid grid-cols-7 gap-1 text-center text-xs">
                    @for ($i = 0; $i < $firstPad; $i++)
                        <span></span>
                    @endfor
                    @for ($d = 1; $d <= $daysInMonth; $d++)
                        @php $isToday = $now->day === $d; @endphp
                        <span class="{{ $isToday ? 'rounded-md bg-cyan-500/30 font-bold text-cyan-100 ring-1 ring-cyan-400/50' : 'rounded-md py-1 text-white/55' }}">{{ $d }}</span>
                    @endfor
                </div>
            </div>

            <div class="admin-cyber-card flex flex-col justify-between p-4 sm:p-5 lg:col-span-3">
                <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-lime-200/90">Ratios rápidos</h3>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-3 sm:justify-between sm:gap-3">
                    @foreach ([['v' => min(100, max(0, (int) $pctContentVod)), 'from' => '#22d3ee', 'to' => '#6366f1'], ['v' => min(100, max(0, (int) $pctCustomersActive)), 'from' => '#e879f9', 'to' => '#a855f7'], ['v' => min(100, max(0, (int) $pctContentActive)), 'from' => '#fbbf24', 'to' => '#f97316'], ['v' => min(100, $totalUsers > 0 ? (int) round(100 * $totalResellers / max(1, $totalUsers)) : 0), 'from' => '#4ade80', 'to' => '#14b8a6'], ['v' => min(100, $totalContent > 0 ? (int) round(100 * $contentInactive / max(1, $totalContent)) : 0), 'from' => '#fb7185', 'to' => '#be123c']] as $mini)
                        <div class="flex flex-col items-center">
                            <div
                                class="admin-cyber-donut admin-cyber-donut--sm"
                                style="--p: {{ $mini['v'] }}; --from: {{ $mini['from'] }}; --to: {{ $mini['to'] }};"
                                role="img"
                                aria-label="{{ $mini['v'] }} por ciento"
                            >
                                <div class="admin-cyber-donut-inner">
                                    <span class="admin-cyber-donut-pct">{{ $mini['v'] }}%</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 text-[10px] leading-relaxed text-white/35">VOD · Clientes OK · Catálogo activo · Rev./users · Inactivos</p>
            </div>
        </div>

        {{-- Accesos recientes --}}
        <div class="admin-cyber-card overflow-hidden">
            <div class="border-b border-white/10 bg-gradient-to-r from-cyan-900/25 to-transparent px-5 py-3">
                <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-cyan-200/90">Accesos recientes</h3>
            </div>
            <div class="overflow-x-auto rounded-b-2xl">
                <table class="min-w-[520px] w-full divide-y divide-white/10 text-xs sm:min-w-full sm:text-sm">
                    <thead class="bg-black/30 text-left text-[9px] font-bold uppercase tracking-wider text-white/45 sm:text-[10px]">
                        <tr>
                            <th class="px-2 py-2.5 sm:px-4 sm:py-3">Usuario</th>
                            <th class="px-2 py-2.5 sm:px-4 sm:py-3">Contenido / acción</th>
                            <th class="px-2 py-2.5 sm:px-4 sm:py-3">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse ($recentAccess as $log)
                            <tr class="transition hover:bg-white/[0.03]">
                                <td class="max-w-[9rem] truncate px-2 py-2 text-white/85 sm:max-w-none sm:px-4 sm:py-2.5">{{ $log->user?->email ?? '—' }}</td>
                                <td class="max-w-[10rem] truncate px-2 py-2 text-white/70 sm:max-w-none sm:px-4 sm:py-2.5">{{ $log->content?->title ?? $log->action }}</td>
                                <td class="whitespace-nowrap px-2 py-2 font-mono text-[10px] text-white/45 sm:px-4 sm:py-2.5 sm:text-xs">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-white/45">Sin registros aún.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-panel-layout>
