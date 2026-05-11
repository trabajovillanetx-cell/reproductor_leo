<x-panel-layout title="Revendedor: {{ $reseller->name }}">
    @include('partials.flash')

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.resellers.index') }}" class="admin-btn-secondary">← Lista</a>
        <a href="{{ route('admin.resellers.edit', $reseller) }}" class="admin-btn-primary">Editar</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="admin-card space-y-3">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Resumen</h2>
            <p class="text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-slate-200">ID:</span> {{ $reseller->id }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-slate-200">Nombre:</span> {{ $reseller->name }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-slate-200">Email:</span> {{ $reseller->email }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-slate-200">Estado:</span> {{ $reseller->status->value }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-slate-200">Créditos:</span> {{ $reseller->resellerCredits?->credits ?? 0 }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-slate-200">Clientes asignados:</span> {{ $reseller->customers->count() }}</p>
        </div>

        <div class="admin-card">
            <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-white">Acciones</h2>
            <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">Para ajustar contraseña, créditos o eliminar cuenta, usa la pantalla de edición.</p>
            <a href="{{ route('admin.resellers.edit', $reseller) }}" class="admin-btn-primary inline-block">Ir a editar</a>
        </div>
    </div>
</x-panel-layout>
