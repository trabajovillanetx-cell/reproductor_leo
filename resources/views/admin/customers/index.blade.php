<x-panel-layout title="Clientes finales">
    @include('partials.flash')

    <div class="mb-6 flex flex-wrap items-end gap-3">
        <a href="{{ route('admin.customers.create') }}" class="admin-btn-primary">Nuevo cliente</a>

        <form method="POST" id="bulk-delete-customers" action="{{ route('admin.customers.bulk_destroy') }}" class="flex items-end" onsubmit="return confirm('¿Eliminar los clientes seleccionados? Esta acción no se puede deshacer.');">
            @csrf
            <button type="submit" class="admin-btn-secondary border-red-400/50 text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/40">Eliminar seleccionados</button>
        </form>

        <form method="GET" class="flex flex-wrap items-center gap-3">
            <select name="status" class="admin-select !w-auto min-w-[11rem]">
                <option value="">Todos los estados</option>
                <option value="active" @selected(request('status')==='active')>Activo</option>
                <option value="expired" @selected(request('status')==='expired')>Vencido</option>
                <option value="suspended" @selected(request('status')==='suspended')>Suspendido</option>
            </select>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Nombre, email, ID o nombre/ID de plan…" class="admin-input !w-auto min-w-[16rem] max-w-md flex-1">
            <button type="submit" class="admin-btn-secondary !py-3">Filtrar</button>
        </form>
    </div>

    <p class="admin-hint mb-6 hidden max-w-4xl text-sm" aria-hidden="true">
        <strong>Perfiles (1–5):</strong> hacé clic en el bloque de números para abrir el panel y marcar cada espacio como vendido o disponible. La contraseña mostrada es la copia de referencia al crear o editar el cliente (cifrada en BD). Para nombre, PIN y avatar usá <strong>Espacios</strong> o el enlace dentro del panel.
    </p>

    <div class="admin-table-wrap">
        <div class="overflow-x-auto">
            <table class="admin-table min-w-[1100px] text-sm [&_tbody_td]:py-2 [&_thead_th]:py-2.5">
                <thead>
                    <tr>
                        <th class="w-8">
                            <input type="checkbox" id="select-all-customers" aria-label="Seleccionar todos" class="h-4 w-4 rounded border-slate-400">
                        </th>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Revendedor / Vendedor</th>
                        <th>Contraseña</th>
                        <th>Creado</th>
                        <th>Vence</th>
                        <th class="text-center">Perfiles</th>
                        <th>Estado</th>
                        <th class="w-px whitespace-nowrap">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $c)
                        @php
                            $profilesByOrder = $c->streamingProfiles->keyBy(fn ($p) => (int) $p->sort_order);
                        @endphp
                        <tr class="hover:bg-indigo-50/40 dark:hover:bg-slate-800/50">
                            <td>
                                <input
                                    type="checkbox"
                                    name="ids[]"
                                    value="{{ $c->id }}"
                                    form="bulk-delete-customers"
                                    class="customer-check h-4 w-4 rounded border-slate-400"
                                >
                            </td>
                            <td class="font-mono text-xs text-slate-600 dark:text-slate-300">{{ $c->id }}</td>
                            <td class="max-w-[14rem] leading-tight">
                                <div class="truncate font-semibold text-slate-900 dark:text-white">{{ $c->name }}</div>
                                <div class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $c->email }}</div>
                            </td>
                            <td class="max-w-[10rem] text-sm break-all text-slate-700 dark:text-slate-300">{{ $c->parent?->email ?? '—' }}</td>
                            <td class="max-w-[9rem] align-top">
                                @if (filled($c->provider_password))
                                    <span class="break-all font-mono text-[11px] text-slate-800 dark:text-slate-200">{{ $c->provider_password }}</span>
                                @else
                                    <span class="text-xs text-slate-500 dark:text-slate-400" title="Sin copia o el usuario cambió la clave.">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap font-mono text-xs text-slate-700 dark:text-slate-300">{{ $c->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="font-mono text-sm">{{ $c->expires_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="align-middle">
                                <x-customer-profiles-modal
                                    :customer="$c"
                                    :profiles-by-order="$profilesByOrder"
                                    toggle-route-name="admin.customers.profiles.sold"
                                    streaming-edit-route-name="admin.customers.streaming-profiles.edit"
                                />
                            </td>
                            <td class="align-middle">
                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $c->status->value }}</span>
                            </td>
                            <td class="align-middle">
                                <div class="relative inline-block text-left" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                                    <button
                                        type="button"
                                        x-on:click="open = !open"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
                                        x-bind:aria-expanded="open"
                                    >
                                        Acciones
                                        <svg class="h-3.5 w-3.5 opacity-60" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                    <div
                                        x-show="open"
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="transform opacity-0 scale-95"
                                        x-transition:enter-end="transform opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="transform opacity-100 scale-100"
                                        x-transition:leave-end="transform opacity-0 scale-95"
                                        x-cloak
                                        class="absolute right-0 z-50 mt-1 w-56 origin-top-right rounded-lg border border-slate-200 bg-white py-1 shadow-lg ring-1 ring-black/5 dark:border-slate-600 dark:bg-slate-900 dark:ring-white/10"
                                        style="display: none;"
                                    >
                                        <a href="{{ route('admin.customers.show', $c) }}" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800" x-on:click="open = false">Ver</a>
                                        <a href="{{ route('admin.customers.edit', $c) }}" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800" x-on:click="open = false">Editar</a>
                                        <a href="{{ route('admin.customers.streaming-profiles.edit', $c) }}" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800" x-on:click="open = false">Espacios</a>
                                        <div class="my-1 border-t border-slate-100 dark:border-slate-700"></div>
                                        <form method="POST" action="{{ route('admin.customers.renew', $c) }}" class="px-3 py-2" x-on:submit="open = false">
                                            @csrf
                                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Renovar plan</p>
                                            <div class="flex gap-1">
                                                <select name="plan_id" class="admin-select min-w-0 flex-1 !py-1.5 !text-xs">
                                                    @foreach ($plans as $p)
                                                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->duration_months }}m)</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="shrink-0 rounded-md bg-indigo-600 px-2 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Renovar</button>
                                            </div>
                                        </form>
                                        <div class="my-1 border-t border-slate-100 dark:border-slate-700"></div>
                                        @if ($c->status->value !== 'suspended')
                                            <form method="POST" action="{{ route('admin.customers.suspend', $c) }}" x-on:submit="open = false">
                                                @csrf
                                                <button type="submit" class="w-full px-3 py-2 text-left text-sm text-amber-700 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-950/30">Suspender</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.customers.activate', $c) }}" x-on:submit="open = false">
                                                @csrf
                                                <button type="submit" class="w-full px-3 py-2 text-left text-sm text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950/30">Activar</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.customers.destroy', $c) }}" onsubmit="return confirm('¿Eliminar este cliente?');" x-on:submit="open = false">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40">Eliminar</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $customers->links() }}</div>
    </div>

    <script>
        document.getElementById('select-all-customers')?.addEventListener('change', function () {
            document.querySelectorAll('.customer-check').forEach(function (cb) {
                cb.checked = this.checked;
            }, this);
        });
        document.getElementById('bulk-delete-customers')?.addEventListener('submit', function (e) {
            if (!document.querySelectorAll('.customer-check:checked').length) {
                e.preventDefault();
                alert('Selecciona al menos un cliente.');
            }
        });
    </script>
</x-panel-layout>
