<x-panel-layout title="Nuevo plan">
    <form method="POST" action="{{ route('admin.plans.store') }}" class="admin-card max-w-2xl space-y-6">
        @csrf
        <div>
            <label class="admin-label" for="plan_name">Nombre</label>
            <input id="plan_name" name="name" value="{{ old('name') }}" required class="admin-input">
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>
        <div>
            <label class="admin-label" for="duration_months">Duración (meses)</label>
            <input id="duration_months" type="number" name="duration_months" value="{{ old('duration_months') }}" min="1" max="120" required class="admin-input">
            <x-input-error :messages="$errors->get('duration_months')" class="mt-1" />
        </div>
        <div>
            <label class="admin-label" for="price">Precio</label>
            <input id="price" type="number" step="0.01" name="price" value="{{ old('price', 0) }}" required class="admin-input">
            <x-input-error :messages="$errors->get('price')" class="mt-1" />
        </div>
        <div>
            <label class="admin-label" for="description">Descripción</label>
            <textarea id="description" name="description" rows="4" class="admin-textarea">{{ old('description') }}</textarea>
        </div>
        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50">
            <input type="checkbox" name="is_active" value="1" checked class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600">
            <span class="text-base font-medium text-slate-800 dark:text-slate-200">Plan activo (visible al asignar clientes)</span>
        </label>
        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="admin-btn-primary">Guardar plan</button>
            <a href="{{ route('admin.plans.index') }}" class="admin-btn-secondary">Cancelar</a>
        </div>
    </form>
</x-panel-layout>
