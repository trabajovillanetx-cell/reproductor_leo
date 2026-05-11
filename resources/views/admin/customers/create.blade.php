<x-panel-layout title="Nuevo cliente">
    <div class="admin-hint mb-8 max-w-3xl">
        <p class="font-semibold text-indigo-900 dark:text-indigo-100">Cómo funciona el acceso y el corte automático</p>
        <ul class="mt-3 list-inside list-disc space-y-2 text-indigo-950/95 dark:text-indigo-50/95">
            <li><strong>Plan elegido:</strong> al guardar, el sistema calcula la fecha de vencimiento sumando los <strong>meses del plan</strong> a hoy (o, si el cliente ya tenía tiempo activo, sumando desde esa fecha).</li>
            <li><strong>Estado “activo”:</strong> el cliente puede entrar al catálogo mientras la fecha de vencimiento sea futura y no esté suspendido.</li>
            <li><strong>Streaming por perfiles:</strong> cada cliente recibe <strong>cinco espacios</strong>. Después de crear la cuenta, abrí <strong>Espacios de reproducción</strong> en la ficha del cliente para poner nombre, avatar (URL) y PIN de 4 dígitos; solo el administrador puede editarlos. El usuario del catálogo solo elige su espacio e ingresa el PIN que vos definas.</li>
            <li><strong>Corte por vencimiento:</strong> cada hora se ejecuta la tarea <code class="rounded bg-black/10 px-1.5 py-0.5 font-mono text-xs">php artisan users:expire</code> (programada en el servidor): pasa a <strong>vencido</strong> a los clientes cuya fecha ya pasó. Además, si intentan usar la app con el plan vencido, el middleware los redirige a la pantalla de plan vencido hasta que renueves.</li>
            <li><strong>En producción</strong> debe estar activo el programador de Laravel (<code class="rounded bg-black/10 px-1 font-mono text-xs">schedule:run</code> cada minuto vía cron o el equivalente en tu hosting).</li>
        </ul>
    </div>

    <form method="POST" action="{{ route('admin.customers.store') }}" class="admin-card max-w-2xl space-y-6">
        @csrf
        <div>
            <label class="admin-label" for="name">Nombre</label>
            <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name" class="admin-input">
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>
        <div>
            <label class="admin-label" for="email">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="admin-input">
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>
        <div>
            <label class="admin-label" for="password">Contraseña</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" class="admin-input">
        </div>
        <div>
            <label class="admin-label" for="password_confirmation">Confirmar contraseña</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="admin-input">
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>
        <div>
            <label class="admin-label" for="plan_id">Plan inicial</label>
            <select id="plan_id" name="plan_id" required class="admin-select">
                @foreach ($plans as $p)
                    <option value="{{ $p->id }}" @selected(old('plan_id')==$p->id)>{{ $p->name }} — {{ $p->duration_months }} mes(es)</option>
                @endforeach
            </select>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">La duración del plan define cuántos meses dura el acceso antes del siguiente vencimiento.</p>
            <x-input-error :messages="$errors->get('plan_id')" class="mt-1" />
        </div>
        <div>
            <label class="admin-label" for="parent_id">Revendedor (opcional)</label>
            <select id="parent_id" name="parent_id" class="admin-select">
                <option value="">— Ninguno —</option>
                @foreach ($resellers as $r)
                    <option value="{{ $r->id }}" @selected(old('parent_id')==$r->id)>{{ $r->name }} ({{ $r->email }})</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="admin-btn-primary">Crear cliente</button>
            <a href="{{ route('admin.customers.index') }}" class="admin-btn-secondary">Volver al listado</a>
        </div>
    </form>
</x-panel-layout>
