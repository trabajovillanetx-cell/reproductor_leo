<x-panel-layout title="Panel revendedor">
    <div class="cyber-stack space-y-8 text-white">
        <div class="flex flex-col gap-4 border-b border-cyan-500/20 pb-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.35em] text-cyan-300/90">Revendedor / <span class="text-fuchsia-300/95">Resumen</span></p>
                <h2 class="mt-2 text-2xl font-black tracking-tight text-white drop-shadow-[0_0_18px_rgba(0,255,255,0.2)] sm:text-3xl">Tu operación</h2>
                <p class="mt-1 max-w-xl text-sm text-white/55">Clientes y créditos en un vistazo. Gestioná la red desde el menú lateral.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a
                    href="{{ route('reseller.network.index') }}"
                    class="rounded-lg border border-cyan-400/40 bg-cyan-500/15 px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-cyan-100 shadow-[0_0_20px_-4px_rgba(34,211,238,0.45)] transition hover:bg-cyan-400/25"
                >
                    Mi red (revendedores / vendedores)
                </a>
            </div>
        </div>

        <div>
            <p class="mb-3 text-[10px] font-bold uppercase tracking-[0.28em] text-white/40">Clientes y créditos</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="admin-cyber-card px-4 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-white/40">Total clientes</p>
                    <p class="mt-1 text-2xl font-black tabular-nums text-white">{{ number_format($totalClients) }}</p>
                </div>
                <div class="admin-cyber-card px-4 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-white/40">Activos</p>
                    <p class="mt-1 text-2xl font-black tabular-nums text-emerald-300">{{ number_format($activeClients) }}</p>
                </div>
                <div class="admin-cyber-card px-4 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-white/40">Vencidos</p>
                    <p class="mt-1 text-2xl font-black tabular-nums text-amber-300">{{ number_format($expiredClients) }}</p>
                </div>
                <div class="admin-cyber-card px-4 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-white/40">Créditos disponibles</p>
                    <p class="mt-1 text-2xl font-black tabular-nums text-cyan-300">{{ number_format($credits) }}</p>
                </div>
            </div>
        </div>
    </div>
</x-panel-layout>
