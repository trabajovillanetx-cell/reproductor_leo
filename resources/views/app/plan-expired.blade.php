<x-panel-layout title="Estado del plan">

    <div class="cyber-stack mx-auto max-w-lg text-center text-white">

        <div class="admin-cyber-card p-8 sm:p-10">

            <p class="text-[10px] font-bold uppercase tracking-[0.35em] text-cyan-300/90">Plan</p>

            <h2 class="mt-3 text-xl font-bold tracking-tight text-white">Tu plan ha vencido. Contacta a tu proveedor.</h2>

            @if (auth()->user()->expires_at)

                <p class="mt-4 text-sm leading-relaxed text-amber-200/90">Fecha de vencimiento registrada: {{ auth()->user()->expires_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} <span class="mt-1 block text-xs text-white/45">(día/mes/año, zona {{ config('app.timezone') }})</span></p>

            @endif

            <p class="mt-4 text-sm leading-relaxed text-white/55">Si acabas de renovar y el estado en el panel del administrador es activo, prueba <strong class="text-cyan-200/95">cerrar sesión y volver a entrar</strong>. Si el problema continúa, contacta a tu administrador o revendedor.</p>

            <form method="POST" action="{{ route('logout') }}" class="mt-8">

                @csrf

                <button

                    type="submit"

                    class="inline-flex w-full items-center justify-center rounded-xl border-2 border-cyan-400/40 bg-cyan-500/15 px-5 py-3 text-sm font-bold uppercase tracking-wide text-cyan-100 shadow-[0_0_22px_rgba(34,211,238,0.2)] transition hover:bg-cyan-400/25"

                >

                    Cerrar sesión

                </button>

            </form>

        </div>

    </div>

</x-panel-layout>

