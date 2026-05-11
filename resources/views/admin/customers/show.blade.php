<x-panel-layout title="Cliente: {{ $customer->name }}">
    @include('partials.flash')

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.customers.index') }}" class="admin-btn-secondary">← Lista</a>
        <a href="{{ route('admin.customers.edit', $customer) }}" class="admin-btn-primary">Editar cuenta</a>
        <a href="{{ route('admin.customers.streaming-profiles.edit', $customer) }}" class="admin-btn-primary bg-indigo-600 hover:bg-indigo-500 dark:bg-indigo-600 dark:hover:bg-indigo-500">Espacios de reproducción</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="admin-card space-y-3">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Datos de cuenta</h2>
            <p class="text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-slate-200">ID:</span> {{ $customer->id }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-slate-200">Nombre:</span> {{ $customer->name }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-slate-200">Email:</span> {{ $customer->email }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-slate-200">Estado:</span> {{ $customer->status->value }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-slate-200">Vence:</span> {{ $customer->expires_at?->format('d/m/Y H:i') ?? '—' }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-slate-200">Revendedor:</span> {{ $customer->parent?->email ?? '—' }}</p>
        </div>

        <div class="admin-card space-y-4">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Renovar plan</h2>
            <form method="POST" action="{{ route('admin.customers.renew', $customer) }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                @csrf
                <div class="min-w-[12rem] flex-1">
                    <label class="admin-label">Plan</label>
                    <select name="plan_id" class="admin-select mt-1 w-full">
                        @foreach (\App\Models\Plan::query()->where('is_active', true)->orderBy('duration_months')->get() as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->duration_months }}m)</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="admin-btn-primary w-full sm:w-auto">Renovar</button>
            </form>
            <div class="flex flex-wrap gap-4 border-t border-slate-200 pt-4 text-sm dark:border-slate-700">
                @if ($customer->status->value !== 'suspended')
                    <form method="POST" action="{{ route('admin.customers.suspend', $customer) }}">@csrf<button type="submit" class="text-amber-600 hover:underline dark:text-amber-400">Suspender</button></form>
                @else
                    <form method="POST" action="{{ route('admin.customers.activate', $customer) }}">@csrf<button type="submit" class="text-emerald-600 hover:underline dark:text-emerald-400">Activar</button></form>
                @endif
                <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" onsubmit="return confirm('¿Eliminar este cliente?');">@csrf @method('DELETE')<button type="submit" class="text-red-600 hover:underline dark:text-red-400">Eliminar</button></form>
            </div>
        </div>
    </div>

    <div class="admin-card mt-6">
        <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Historial de suscripciones (planes)</h2>
        @if ($customer->subscriptions->isEmpty())
            <p class="text-sm text-slate-500">Sin registros de suscripción todavía.</p>
        @else
            <div class="overflow-x-auto">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Desde</th>
                            <th>Hasta</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customer->subscriptions->sortByDesc('id') as $sub)
                            <tr>
                                <td>{{ $sub->plan?->name ?? '—' }} @if($sub->plan)<span class="text-xs text-slate-500">(ID plan {{ $sub->plan_id }})</span>@endif</td>
                                <td class="font-mono text-sm">{{ $sub->starts_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="font-mono text-sm">{{ $sub->expires_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>{{ $sub->status->value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-panel-layout>
