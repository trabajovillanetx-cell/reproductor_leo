<x-panel-layout title="Nueva categoría / carpeta">
    <form method="POST" action="{{ route('admin.categories.store') }}" class="admin-card max-w-2xl space-y-6">
        @csrf
        <div>
            <label class="admin-label" for="cat_name">Nombre</label>
            <input id="cat_name" name="name" value="{{ old('name') }}" required placeholder="Ej. Películas 2026, TV en vivo Colombia" class="admin-input">
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>
        <div>
            <label class="admin-label" for="cat_type">Tipo de contenido</label>
            <select name="type" id="cat_type" class="admin-select">
                @foreach ($types as $t)
                    <option value="{{ $t->value }}" @selected(old('type')===$t->value)>
                        @if ($t->value === 'live') live — Canales en vivo
                        @elseif ($t->value === 'vod') vod — Películas / VOD
                        @else series — Series
                        @endif
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="admin-label" for="parent_id">Carpeta padre (opcional)</label>
            <select id="parent_id" name="parent_id" class="admin-select">
                <option value="">— Raíz (sin carpeta padre) —</option>
                @foreach ($parentOptions as $opt)
                    <option value="{{ $opt['id'] }}" @selected(old('parent_id')==$opt['id'])>{{ $opt['label'] }}</option>
                @endforeach
            </select>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Si eliges padre, debe ser del mismo tipo que seleccionas arriba.</p>
            <x-input-error :messages="$errors->get('parent_id')" class="mt-1" />
        </div>
        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50">
            <input type="checkbox" name="is_active" value="1" checked class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600">
            <span class="text-base font-medium text-slate-800 dark:text-slate-200">Categoría activa</span>
        </label>
        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="admin-btn-primary">Guardar</button>
            <a href="{{ route('admin.categories.index') }}" class="admin-btn-secondary">Volver</a>
        </div>
    </form>
</x-panel-layout>
