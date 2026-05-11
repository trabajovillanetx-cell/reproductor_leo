<x-panel-layout title="Nuevo contenido">
    <form method="POST" action="{{ route('admin.contents.store') }}" enctype="multipart/form-data" class="admin-card max-w-2xl space-y-6">
        @csrf
        <div>
            <label class="admin-label" for="category_id">Categoría / carpeta</label>
            <select id="category_id" name="category_id" required class="admin-select">
                @foreach ($categoryOptions as $opt)
                    <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="admin-label" for="title">Título</label>
            <input id="title" name="title" value="{{ old('title') }}" required class="admin-input">
            <x-input-error :messages="$errors->get('title')" class="mt-1" />
        </div>
        <div>
            <label class="admin-label" for="description">Descripción</label>
            <textarea id="description" name="description" rows="3" class="admin-textarea !min-h-[6rem]">{{ old('description') }}</textarea>
        </div>
        <div>
            <label class="admin-label" for="type">Tipo</label>
            <select id="type" name="type" class="admin-select">
                @foreach ($types as $t)
                    <option value="{{ $t->value }}">{{ $t->value }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="admin-label" for="stream_url">URL del stream (https)</label>
            <input id="stream_url" name="stream_url" value="{{ old('stream_url') }}" required class="admin-input font-mono text-sm">
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Puede ser URL https, o ruta local con prefijo <code class="rounded bg-slate-200 px-1.5 py-0.5 font-mono text-xs dark:bg-slate-700">local:</code> bajo las rutas de biblioteca local (<code class="rounded bg-slate-200 px-1 dark:bg-slate-700">RAIDRIVE_*</code> o <code class="rounded bg-slate-200 px-1 dark:bg-slate-700">RCLONE_MOUNT_*</code> según <code class="rounded bg-slate-200 px-1 dark:bg-slate-700">LOCAL_LIBRARY_DRIVER</code>) en <code class="rounded bg-slate-200 px-1 dark:bg-slate-700">.env</code> (o subidas en <code class="rounded bg-slate-200 px-1 dark:bg-slate-700">storage/app/public</code>).</p>
            <x-input-error :messages="$errors->get('stream_url')" class="mt-1" />
        </div>
        <div>
            <label class="admin-label" for="poster_url">Poster URL (opcional si subís imagen)</label>
            <input id="poster_url" name="poster_url" value="{{ old('poster_url') }}" class="admin-input font-mono text-sm" placeholder="https://…">
            <label class="admin-label mt-4" for="poster_file_create">O subir imagen de carátula</label>
            <input
                id="poster_file_create"
                name="poster_file"
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif"
                class="folder-poster-file-input mt-1 block w-full cursor-pointer rounded-xl border border-white/20 bg-black/35 px-3 py-2.5 text-sm text-slate-100 file:mr-4 file:cursor-pointer file:rounded-lg file:border-2 file:border-indigo-300/50 file:bg-indigo-600 file:px-4 file:py-2.5 file:text-sm file:font-bold file:text-white file:shadow file:shadow-indigo-900/40 hover:file:border-indigo-200/70 hover:file:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400/50"
            >
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">JPG, PNG, WebP o GIF (máx. ~12&nbsp;MB). Requiere <code class="rounded bg-slate-200 px-1 font-mono text-[11px] dark:bg-slate-700">php artisan storage:link</code> para que el cliente vea la imagen.</p>
            <x-input-error :messages="$errors->get('poster_file')" class="mt-1" />
        </div>
        <div>
            <label class="admin-label" for="duration">Duración (segundos, opcional)</label>
            <input id="duration" type="number" name="duration" value="{{ old('duration') }}" min="0" class="admin-input">
        </div>
        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50">
            <input type="checkbox" name="is_active" value="1" checked class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600">
            <span class="text-base font-medium text-slate-800 dark:text-slate-200">Contenido activo</span>
        </label>
        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="admin-btn-primary">Guardar</button>
            <a href="{{ route('admin.contents.index') }}" class="admin-btn-secondary">Volver</a>
        </div>
    </form>
</x-panel-layout>
