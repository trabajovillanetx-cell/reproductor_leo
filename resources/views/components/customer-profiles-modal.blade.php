@props([
    'customer',
    'profilesByOrder',
    'toggleRouteName',
    'streamingEditRouteName' => null,
    'triggerClass' => '',
])

@php
    /** @var \Illuminate\Support\Collection $profilesByOrder */
    $slotsPayload = [];
    for ($i = 0; $i < 5; $i++) {
        $prof = $profilesByOrder->get($i);
        if ($prof) {
            $slotsPayload[] = [
                'id' => $prof->id,
                'name' => $prof->name,
                'is_sold' => (bool) $prof->is_sold,
                'toggle_url' => route($toggleRouteName, [$customer, $prof]),
            ];
        } else {
            $slotsPayload[] = null;
        }
    }
@endphp

<div
    class="customer-profiles-modal flex justify-center"
    x-data="{
        profileModalOpen: false,
        profiles: {{ \Illuminate\Support\Js::from($slotsPayload) }},
        csrfToken: '{{ csrf_token() }}',
        loadingId: null,
        flashError: '',
        async toggleSlot(p) {
            if (!p || this.loadingId !== null) {
                return;
            }
            this.loadingId = p.id;
            this.flashError = '';
            try {
                const r = await fetch(p.toggle_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({ _token: this.csrfToken }),
                    credentials: 'same-origin',
                });
                const data = await r.json().catch(() => ({}));
                if (!r.ok) {
                    throw new Error(data.message || ('Error ' + r.status));
                }
                if (typeof data.is_sold === 'boolean') {
                    p.is_sold = data.is_sold;
                }
            } catch (e) {
                this.flashError = e.message || 'No se pudo actualizar.';
            } finally {
                this.loadingId = null;
            }
        },
    }"
>
    {{-- IMPORTANTE: no usar <template> dentro de <button> (HTML inválido / Alpine no pinta bien). --}}
    <div
        role="button"
        tabindex="0"
        title="Abrir perfiles"
        class="customer-profiles-modal-trigger inline-flex max-w-full cursor-pointer flex-nowrap items-center justify-center gap-2 rounded-xl px-2.5 py-2 outline-none focus-visible:ring-2 focus-visible:ring-neutral-400 sm:gap-2.5 sm:px-3 sm:py-2.5 {{ $triggerClass }}"
        x-on:click="profileModalOpen = true"
        x-on:keydown.enter.prevent="profileModalOpen = true"
        x-on:keydown.space.prevent="profileModalOpen = true"
    >
        <template x-for="(p, idx) in profiles" :key="idx">
            <span
                class="customer-profiles-modal-circle-num"
                :class="p === null
                    ? 'customer-profiles-modal-circle-empty'
                    : (p.is_sold ? 'customer-profiles-modal-circle-sold' : 'customer-profiles-modal-circle-available')"
                x-text="p === null ? '?' : idx + 1"
            ></span>
        </template>
    </div>

    <template x-teleport="body">
        <div
            x-show="profileModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-cloak
            class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-4"
            style="display: none;"
            x-on:keydown.escape.window="profileModalOpen = false"
        >
            <div class="absolute inset-0 bg-black/60" x-on:click="profileModalOpen = false" aria-hidden="true"></div>

            <div
                class="customer-profiles-modal-popup relative z-10 flex w-full max-w-lg flex-col overflow-hidden rounded-2xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="profiles-modal-title-{{ $customer->id }}"
                style="color-scheme: light;"
                x-on:click.stop
            >
                <div class="border-b-2 border-neutral-600 px-4 pb-3 pt-4 sm:px-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <h3 id="profiles-modal-title-{{ $customer->id }}" class="cpm-heading text-base font-bold sm:text-lg">Perfiles del cliente</h3>
                            <p class="cpm-text mt-1.5 text-sm leading-snug">
                                <span class="font-semibold">{{ $customer->name }}</span>
                                <span class="text-neutral-800">· {{ $customer->email }}</span>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="cpm-btn-close shrink-0 rounded-lg p-2"
                            x-on:click="profileModalOpen = false"
                            aria-label="Cerrar"
                        >
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <p class="cpm-text mt-3 text-center text-xs font-semibold leading-relaxed">
                        Tocá un perfil para alternar <span class="whitespace-nowrap text-green-800">verde = disponible</span> ·
                        <span class="whitespace-nowrap text-yellow-800">amarillo = vendido</span>
                    </p>
                </div>

                <p
                    class="border-b border-neutral-500 bg-red-200/90 px-4 py-2 text-center text-sm font-medium text-red-950"
                    x-show="flashError"
                    x-text="flashError"
                ></p>

                {{-- Una sola fila compacta de 5 columnas --}}
                <div class="px-2 py-4 sm:px-4">
                    <ul class="mx-auto grid max-w-full grid-cols-5 gap-1.5 sm:gap-3" role="list">
                        <template x-for="(p, idx) in profiles" :key="idx">
                            <li class="flex min-w-0 justify-center">
                                <div
                                    x-show="p === null"
                                    class="flex w-full min-w-0 flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-neutral-600 bg-neutral-400 py-3 text-center"
                                >
                                    <span class="text-lg font-black text-black" x-text="idx + 1"></span>
                                    <span class="px-0.5 text-[9px] font-bold uppercase leading-tight text-black">Vacío</span>
                                </div>
                                <button
                                    type="button"
                                    x-show="p !== null"
                                    class="cpm-slot flex w-full min-w-0 flex-col items-center gap-1.5 rounded-xl px-1 py-2.5 sm:gap-2 sm:py-3"
                                    :disabled="loadingId !== null"
                                    x-on:click.stop="toggleSlot(p)"
                                >
                                    <span
                                        class="cpm-big-circle"
                                        :class="p && p.is_sold ? 'customer-profiles-modal-circle-sold' : 'customer-profiles-modal-circle-available'"
                                        x-text="idx + 1"
                                    ></span>
                                    <span
                                        class="max-w-full truncate px-0.5 text-center text-[10px] font-bold leading-tight text-black sm:text-xs"
                                        x-text="p && p.name"
                                    ></span>
                                    <span
                                        class="text-[9px] font-black uppercase leading-none tracking-wide text-black sm:text-[10px]"
                                        x-text="p && p.is_sold ? 'Vendido' : 'Libre'"
                                    ></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>

                @if ($streamingEditRouteName)
                    <div class="border-t border-neutral-600 px-4 pb-2 pt-1">
                        <a
                            href="{{ route($streamingEditRouteName, $customer) }}"
                            class="cpm-text block text-center text-xs font-semibold underline decoration-2 underline-offset-2 sm:text-sm"
                            x-on:click="profileModalOpen = false"
                        >
                            Editar nombre, PIN y avatar →
                        </a>
                    </div>
                @endif

                <div class="cpm-footer p-3 sm:p-4">
                    <button
                        type="button"
                        class="cpm-btn-close w-full rounded-xl py-3 text-center text-sm font-bold sm:text-base"
                        x-on:click="profileModalOpen = false"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
