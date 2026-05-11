<x-panel-layout title="Editar cliente">
    @include('partials.flash')

    <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="admin-card max-w-2xl space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label class="admin-label" for="name">Nombre</label>
            <input id="name" name="name" value="{{ old('name', $customer->name) }}" required class="admin-input mt-1 w-full">
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>
        <div>
            <label class="admin-label" for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $customer->email) }}" required class="admin-input mt-1 w-full">
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>
        <div>
            <label class="admin-label" for="password">Nueva contraseña (opcional)</label>
            <input id="password" type="password" name="password" class="admin-input mt-1 w-full" autocomplete="new-password">
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>
        <div>
            <label class="admin-label" for="password_confirmation">Confirmar contraseña</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="admin-input mt-1 w-full" autocomplete="new-password">
        </div>
        <div>
            <label class="admin-label" for="parent_id">Revendedor asignado</label>
            <select id="parent_id" name="parent_id" class="admin-select mt-1 w-full">
                <option value="">— Ninguno —</option>
                @foreach ($resellers as $r)
                    <option value="{{ $r->id }}" @selected(old('parent_id', $customer->parent_id) == $r->id)>{{ $r->name }} ({{ $r->email }})</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('parent_id')" class="mt-1" />
        </div>
        <div>
            @php
                $expiresInput = old(
                    'expires_at',
                    $customer->expires_at
                        ? $customer->expires_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i')
                        : ''
                );
            @endphp
            <label class="admin-label" for="expires_at">Fecha y hora de vencimiento del plan</label>
            <input
                id="expires_at"
                name="expires_at"
                type="datetime-local"
                step="60"
                value="{{ $expiresInput }}"
                class="admin-input mt-1 w-full"
            >
            <p class="mt-1.5 text-xs leading-snug text-slate-600 dark:text-slate-400">
                Se guarda según la zona <code class="rounded bg-slate-200 px-1 py-0.5 text-[11px] dark:bg-slate-700">{{ config('app.timezone') }}</code>
                (ajústala en <code class="rounded bg-slate-200 px-1 py-0.5 text-[11px] dark:bg-slate-700">APP_TIMEZONE</code> en <code class="rounded bg-slate-200 px-1 py-0.5 text-[11px] dark:bg-slate-700">.env</code> si hace falta).
                Deja vacío solo si quieres quitar la fecha (el cliente no podrá entrar al catálogo hasta que asignes otra).
            </p>
            <x-input-error :messages="$errors->get('expires_at')" class="mt-1" />
        </div>
        <div>
            <label class="admin-label" for="status">Estado</label>
            <select id="status" name="status" class="admin-select mt-1 w-full">
                <option value="active" @selected(old('status', $customer->status->value) === 'active')>Activo</option>
                <option value="expired" @selected(old('status', $customer->status->value) === 'expired')>Vencido</option>
                <option value="suspended" @selected(old('status', $customer->status->value) === 'suspended')>Suspendido</option>
            </select>
            <x-input-error :messages="$errors->get('status')" class="mt-1" />
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="admin-btn-primary">Guardar cambios</button>
            <a href="{{ route('admin.customers.show', $customer) }}" class="admin-btn-secondary">Cancelar</a>
            <a href="{{ route('admin.customers.streaming-profiles.edit', $customer) }}" class="text-sm font-semibold text-indigo-600 underline decoration-indigo-400/50 underline-offset-4 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">Espacios de reproducción (PIN y avatares)</a>
        </div>
    </form>
</x-panel-layout>
