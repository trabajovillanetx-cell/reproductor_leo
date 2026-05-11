<x-panel-layout title="Editar categoría / carpeta">
    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="admin-card max-w-2xl space-y-6">
        @csrf @method('PUT')
        <div>
            <label class="admin-label" for="name">Nombre</label>
            <input id="name" name="name" value="{{ old('name', $category->name) }}" required class="admin-input">
        </div>
        <div>
            <label class="admin-label" for="type">Tipo de contenido</label>
            <select id="type" name="type" class="admin-select">
                @foreach ($types as $t)
                    <option value="{{ $t->value }}" @selected(old('type', $category->type->value)===$t->value)>
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
                <option value="">— Raíz —</option>
                @foreach ($parentOptions as $opt)
                    <option value="{{ $opt['id'] }}" @selected(old('parent_id', $category->parent_id)==$opt['id'])>{{ $opt['label'] }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('parent_id')" class="mt-1" />
        </div>
        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active)) class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600">
            <span class="text-base font-medium text-slate-800 dark:text-slate-200">Categoría activa</span>
        </label>
        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="admin-btn-primary">Actualizar</button>
            <a href="{{ route('admin.categories.index') }}" class="admin-btn-secondary">Volver</a>
        </div>
    </form>
</x-panel-layout>
