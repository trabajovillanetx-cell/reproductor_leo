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
        <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Acceso IPTV / Playlist</h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">URLs para usar en apps IPTV como TiviMate, IPTV Smarters, VLC, etc.</p>
        <div class="space-y-4">
            <div>
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">M3U Playlist</p>
                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        readonly
                        value="{{ route('iptv.playlist', ['username' => $customer->email, 'password' => $customer->provider_password]) }}"
                        class="admin-input flex-1 font-mono text-xs"
                        id="url-m3u"
                        onclick="this.select()"
                    >
                    <button
                        type="button"
                        onclick="navigator.clipboard.writeText(document.getElementById('url-m3u').value).then(()=>this.textContent='✓ Copiado').catch(()=>{}); setTimeout(()=>this.textContent='Copiar',2000)"
                        class="admin-btn-secondary shrink-0 text-sm"
                    >Copiar</button>
                </div>
            </div>
            <div>
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Xtream Codes (TiviMate / Smarters)</p>
                <div class="grid gap-2 sm:grid-cols-3">
                    <div>
                        <p class="mb-1 text-xs text-slate-500">Host</p>
                        <input type="text" readonly value="{{ config('app.url') }}" class="admin-input w-full font-mono text-xs" onclick="this.select()">
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-slate-500">Usuario</p>
                        <input type="text" readonly value="{{ $customer->email }}" class="admin-input w-full font-mono text-xs" onclick="this.select()">
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-slate-500">Contraseña</p>
                        <input type="text" readonly value="{{ $customer->provider_password }}" class="admin-input w-full font-mono text-xs" onclick="this.select()">
                    </div>
                </div>
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
