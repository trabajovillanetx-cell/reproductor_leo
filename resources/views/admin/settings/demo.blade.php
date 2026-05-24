<x-panel-layout title="Configuración de Demos">
<div class="space-y-8">
<div class="max-w-xl space-y-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
@if(session('success'))
<div class="rounded-lg bg-green-100 p-3 text-sm text-green-800 dark:bg-green-900 dark:text-green-200">{{ session('success') }}</div>
@endif
<h2 class="text-lg font-semibold">⏱ Duración de cuentas demo</h2>
<p class="text-sm text-gray-500">Define cuántas horas dura una cuenta demo. Zona horaria <strong>Bogotá (UTC-5)</strong>.</p>
<form method="POST" action="{{ route('admin.settings.demo.update') }}" class="space-y-4">
@csrf
@method('PUT')
<div>
<label class="block text-sm font-medium">Horas de duración del demo</label>
<input type="number" name="demo_duration_hours" value="{{ old('demo_duration_hours', $demoDurationHours) }}" min="1" max="720" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
<p class="mt-1 text-xs text-gray-400">Ejemplo: 1 = caduca 1 hora después de creado. Máximo 720 h (30 días).</p>
<x-input-error :messages="$errors->get('demo_duration_hours')" class="mt-1" />
</div>
<x-primary-button type="submit">Guardar configuración</x-primary-button>
</form>
</div>
<div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
<div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
<h2 class="text-lg font-semibold">🎬 Cuentas demo creadas</h2>
<p class="text-sm text-gray-500">Total: {{ $demos->count() }}</p>
</div>
@if($demos->isEmpty())
<div class="px-6 py-8 text-center text-sm text-gray-400">No hay cuentas demo creadas aún.</div>
@else
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
<tr>
<th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Vendedor / Reseller</th>
<th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Nombre demo</th>
<th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Email demo</th>
<th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Contraseña</th>
<th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Creado</th>
<th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Expira</th>
<th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Estado</th>
</tr>
</thead>
<tbody class="divide-y divide-gray-100 dark:divide-gray-800">
@foreach($demos as $demo)
@php
$now = now('America/Bogota');
$expires = $demo->expires_at?->setTimezone('America/Bogota');
$vencido = $expires && $expires->lt($now);
@endphp
<tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
<td class="px-4 py-3">
@if($demo->parent)
<div class="font-medium">{{ $demo->parent->name }}</div>
<div class="text-xs text-gray-400">{{ $demo->parent->email }}</div>
<span class="mt-0.5 inline-block rounded px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $demo->parent->isReseller() ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300' }}">
{{ $demo->parent->isReseller() ? 'Reseller' : 'Vendor' }}
</span>
@else
<span class="text-gray-400">—</span>
@endif
</td>
<td class="px-4 py-3 font-medium">{{ $demo->name }}</td>
<td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $demo->email }}</td>
<td class="px-4 py-3"><code class="rounded bg-gray-100 px-2 py-0.5 text-xs dark:bg-gray-800">{{ $demo->provider_password ?? '••••••' }}</code></td>
<td class="px-4 py-3 text-xs text-gray-500">{{ $demo->created_at->setTimezone('America/Bogota')->format('d/m/Y H:i') }}</td>
<td class="px-4 py-3 text-xs font-semibold {{ $vencido ? 'text-red-500' : 'text-green-600 dark:text-green-400' }}">{{ $expires ? $expires->format('d/m/Y H:i') : '—' }}</td>
<td class="px-4 py-3">
@if($vencido)
<span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-400">Vencido</span>
@else
<span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-400">Activo</span>
@endif
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
@endif
</div>
</div>
</x-panel-layout>
