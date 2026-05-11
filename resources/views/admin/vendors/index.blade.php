<x-panel-layout title="Vendedores">
    @include('partials.flash')

    <div class="mb-6 flex flex-wrap items-end gap-3">
        <a href="{{ route('admin.vendors.create') }}" class="admin-btn-primary">Nuevo vendedor</a>

        <form method="POST" id="bulk-delete-vendors" action="{{ route('admin.vendors.bulk_destroy') }}" class="flex items-end" onsubmit="return confirm('¿Eliminar los vendedores seleccionados? Los clientes asociados quedarán sin padre (parent_id nulo).');">
            @csrf
            <button type="submit" class="admin-btn-secondary border-red-400/50 text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/40">Eliminar seleccionados</button>
        </form>

        <form method="GET" class="flex flex-wrap items-center gap-3">
            <select name="status" class="admin-select !w-auto min-w-[11rem]">
                <option value="">Todos los estados</option>
                <option value="active" @selected(request('status')==='active')>Activo</option>
                <option value="suspended" @selected(request('status')==='suspended')>Suspendido</option>
            </select>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Nombre, email o ID…" class="admin-input !w-auto min-w-[16rem] max-w-md flex-1">
            <button type="submit" class="admin-btn-secondary !py-3">Filtrar</button>
        </form>
    </div>

    <div class="admin-table-wrap">
        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" id="select-all-vendors" aria-label="Seleccionar todos" class="h-4 w-4 rounded border-slate-400">
                        </th>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Estado</th>
                        <th>Créditos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vendors as $r)
                        <tr class="hover:bg-indigo-50/40 dark:hover:bg-slate-800/50">
                            <td>
                                <input
                                    type="checkbox"
                                    name="ids[]"
                                    value="{{ $r->id }}"
                                    form="bulk-delete-vendors"
                                    class="vendor-check h-4 w-4 rounded border-slate-400"
                                >
                            </td>
                            <td class="font-mono text-xs text-slate-600 dark:text-slate-300">{{ $r->id }}</td>
                            <td class="font-medium">{{ $r->name }}</td>
                            <td>{{ $r->email }}</td>
                            <td>
                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $r->status->value }}</span>
                            </td>
                            <td class="font-mono">{{ $r->resellerCredits?->credits ?? 0 }}</td>
                            <td>
                                <div class="flex flex-wrap gap-2 text-sm font-semibold">
                                    <a href="{{ route('admin.vendors.show', $r) }}" class="text-sky-600 hover:underline dark:text-sky-400">Ver</a>
                                    <a href="{{ route('admin.vendors.edit', $r) }}" class="text-indigo-600 hover:underline dark:text-indigo-400">Editar</a>
                                    <form method="POST" action="{{ route('admin.vendors.destroy', $r) }}" class="inline" onsubmit="return confirm('¿Eliminar este vendedor?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline dark:text-red-400">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $vendors->links() }}</div>
    </div>

    <script>
        document.getElementById('select-all-vendors')?.addEventListener('change', function () {
            document.querySelectorAll('.vendor-check').forEach(function (cb) {
                cb.checked = this.checked;
            }, this);
        });
        document.getElementById('bulk-delete-vendors')?.addEventListener('submit', function (e) {
            if (!document.querySelectorAll('.vendor-check:checked').length) {
                e.preventDefault();
                alert('Selecciona al menos un vendedor.');
            }
        });
    </script>
</x-panel-layout>
