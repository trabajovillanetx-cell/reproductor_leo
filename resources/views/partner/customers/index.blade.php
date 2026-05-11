@php
    $customersIndexRoute = $routePrefix.'.customers.index';
    $customersCreateRoute = $routePrefix.'.customers.create';
    $streamingEditRoute = $routePrefix.'.customers.streaming-profiles.edit';
    $renewRoute = $routePrefix.'.customers.renew';
    $suspendRoute = $routePrefix.'.customers.suspend';
    $activateRoute = $routePrefix.'.customers.activate';
    $toggleSoldRoute = $routePrefix.'.customers.profiles.sold';
@endphp
<x-panel-layout title="Mis clientes">
    <div class="mb-4 flex flex-wrap items-end gap-2">
        <a href="{{ route($customersCreateRoute) }}" class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Nuevo cliente</a>
        <form method="GET" class="flex flex-wrap gap-2">
            <select name="status" class="rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800">
                <option value="">Estado</option>
                <option value="active" @selected(request('status')==='active')>Activo</option>
                <option value="expired" @selected(request('status')==='expired')>Vencido</option>
                <option value="suspended" @selected(request('status')==='suspended')>Suspendido</option>
            </select>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Buscar…" class="rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800">
            <button type="submit" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600">Filtrar</button>
        </form>
    </div>
    <p class="mb-4 max-w-4xl text-xs text-blue-100/85">
        <strong>Perfiles:</strong> tocá el bloque de números para abrir el panel y marcar cada espacio como vendido o disponible. La contraseña listada es la que definiste al crear el cliente (cifrada en base de datos); si el usuario la cambia desde su cuenta, dejamos de mostrarla.
    </p>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="min-w-[960px] divide-y divide-gray-200 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-3 py-3 text-left font-medium">Cliente / Correo</th>
                        <th class="px-3 py-3 text-left font-medium">Contraseña</th>
                        <th class="px-3 py-3 text-left font-medium">Creado</th>
                        <th class="px-3 py-3 text-left font-medium">Vence</th>
                        <th class="px-3 py-3 text-center font-medium">Perfiles (1–5)</th>
                        <th class="px-3 py-3 text-left font-medium">Estado</th>
                        <th class="px-3 py-3 text-left font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($customers as $c)
                        @php
                            $profilesByOrder = $c->streamingProfiles->keyBy(fn ($p) => (int) $p->sort_order);
                        @endphp
                        <tr>
                            <td class="px-3 py-3 align-top">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $c->name }}</div>
                                <div class="text-gray-500">{{ $c->email }}</div>
                            </td>
                            <td class="max-w-[140px] px-3 py-3 align-top">
                                @if (filled($c->provider_password))
                                    <span class="break-all font-mono text-[11px] text-gray-800 dark:text-gray-200">{{ $c->provider_password }}</span>
                                @else
                                    <span class="text-xs text-gray-500 dark:text-gray-400" title="Oculta si el cliente cambió su clave o no se guardó copia.">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 align-top text-gray-700 dark:text-gray-300">{{ $c->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 align-top text-gray-700 dark:text-gray-300">{{ $c->expires_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-3 py-3 align-top">
                                <x-customer-profiles-modal
                                    :customer="$c"
                                    :profiles-by-order="$profilesByOrder"
                                    :toggle-route-name="$toggleSoldRoute"
                                    :streaming-edit-route-name="$streamingEditRoute"
                                />
                            </td>
                            <td class="px-3 py-3 align-top">{{ $c->status->value }}</td>
                            <td class="min-w-[200px] px-3 py-3 align-top">
                                <a href="{{ route($streamingEditRoute, $c) }}" class="mb-2 inline-flex rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Editar espacios</a>
                                <form method="POST" action="{{ route($renewRoute, $c) }}" class="mb-2 flex flex-col gap-1 sm:flex-row sm:items-center">
                                    @csrf
                                    <select name="plan_id" class="rounded border-gray-300 text-xs dark:border-gray-700 dark:bg-gray-800">
                                        @foreach ($plans as $plan)
                                            <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->duration_months }}m)</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="rounded bg-gray-800 px-2 py-1 text-xs text-white dark:bg-gray-200 dark:text-gray-900">Renovar</button>
                                </form>
                                <div class="flex flex-wrap gap-2 text-xs">
                                    @if ($c->status->value !== 'suspended')
                                        <form method="POST" action="{{ route($suspendRoute, $c) }}">@csrf<button class="text-amber-600 hover:underline">Suspender</button></form>
                                    @else
                                        <form method="POST" action="{{ route($activateRoute, $c) }}">@csrf<button class="text-emerald-600 hover:underline">Activar</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-800">{{ $customers->links() }}</div>
    </div>
</x-panel-layout>
