<x-panel-layout title="Mi red">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-white/70">Tus créditos: <strong class="font-bold text-cyan-200 tabular-nums">{{ $balance }}</strong> · 1 crédito = 1 mes de plan para un cliente final.</p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reseller.network.resellers.create') }}" class="admin-btn-primary">Nuevo revendedor</a>
            <a href="{{ route('reseller.network.vendors.create') }}" class="admin-btn-secondary">Nuevo vendedor</a>
        </div>
    </div>

    <div class="admin-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Créditos</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($children as $c)
                        <tr>
                            <td>
                                @if ($c->isReseller())
                                    <span class="rounded-full bg-sky-500/20 px-2 py-0.5 text-xs font-semibold text-sky-200">Revendedor</span>
                                @else
                                    <span class="rounded-full bg-violet-500/20 px-2 py-0.5 text-xs font-semibold text-violet-200">Vendedor</span>
                                @endif
                            </td>
                            <td class="font-medium">{{ $c->name }}</td>
                            <td>{{ $c->email }}</td>
                            <td class="font-mono">{{ $c->resellerCredits?->credits ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-white/45">Todavía no creaste revendedores ni vendedores.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-panel-layout>
