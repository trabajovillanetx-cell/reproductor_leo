{{-- Carrusel destacado: carátulas + vignette autoplay muted (≈tipo banderines, sin marca). --}}
@props(['slides' => [], 'heroPreviewUrlTemplate' => ''])

@if (count($slides) > 0)
    <section
        class="relative mb-10 min-h-[min(92vh,820px)] w-full max-w-none overflow-hidden rounded-none sm:mb-12 lg:mb-14 lg:min-h-[min(94vh,920px)] lg:rounded-2xl"
        x-data="streamingHero(@js($slides), @js([
            'previewUrlTemplate' => $heroPreviewUrlTemplate,
            'carouselIntervalMs' => max(3000, (int) config('streaming.hero_preview_clip_seconds', 8) * 1000),
        ]))"
        aria-roledescription="carrusel"
        aria-label="Destacados del catálogo"
    >
        <div class="absolute inset-0 bg-slate-950">
            <template x-for="(slide, idx) in slides" :key="'bg-'+idx">
                <div
                    x-show="i === idx"
                    x-transition.opacity.duration.500ms
                    class="absolute inset-0 overflow-hidden"
                    x-cloak
                >
                    {{-- Doble capa poster: blur + nítida con object-cover (sin bandas laterales; puede recortar arriba/abajo). --}}
                    <div x-show="slide.poster" class="absolute inset-0 overflow-hidden bg-slate-950">
                        {{-- Poster oculto: solo se muestra cuando no hay video --}}
                        <img
                            :src="slide.poster"
                            :alt="slide.title"
                            class="streaming-hero-sharp-img pointer-events-none absolute inset-0 z-[1] h-full w-full min-h-0 min-w-0 object-cover object-[center_22%] transition-opacity duration-700"
                            class="opacity-100"
                            loading="lazy"
                            decoding="async"
                            sizes="100vw"
                        >
                    </div>
                    <div
                        x-show="!slide.poster"
                        class="absolute inset-0 bg-[radial-gradient(circle_at_30%_22%,rgba(34,211,238,0.22),transparent_52%),radial-gradient(circle_at_78%_70%,rgba(192,38,211,0.18),transparent_48%),linear-gradient(145deg,#0a0e1a,#121a32,#050814)]"
                    ></div>
                </div>
            </template>
        </div>



        {{-- Degradado lectura a todo el ancho (sin max-w en vw): antes el to-transparent + caja estrecha dejaba ver el shell a la derecha. --}}
        <div
            class="pointer-events-none absolute inset-y-0 left-0 right-0 z-[7] bg-[linear-gradient(90deg,rgba(0,0,0,0.92)_0%,rgba(0,0,0,0.55)_38%,rgba(0,0,0,0.12)_58%,transparent_76%)]"
            aria-hidden="true"
        ></div>
        <div
            class="pointer-events-none absolute inset-x-0 bottom-0 z-[7] h-44 bg-gradient-to-t from-black via-black/50 to-transparent sm:h-48"
            aria-hidden="true"
        ></div>

        <div class="relative z-[11] flex min-h-[min(92vh,820px)] flex-col justify-end px-6 pb-10 pt-28 sm:px-12 sm:pb-12 lg:min-h-[min(94vh,920px)] lg:max-w-[58%] lg:justify-center lg:pb-16 lg:pt-24">
            <div class="pointer-events-auto">
                <p
                    class="mb-2 text-[10px] font-bold uppercase tracking-[0.38em] text-cyan-300/95 drop-shadow sm:text-[11px]"
                    x-text="current().typeLabel"
                ></p>
                <h2
                    class="text-balance text-3xl font-black leading-[1.05] tracking-tight text-white drop-shadow-xl sm:text-4xl md:text-5xl"
                    style="text-shadow: 0 8px 32px rgba(0,0,0,0.75);"
                    x-text="current().title"
                ></h2>
                <p
                    class="mt-4 line-clamp-3 text-sm leading-relaxed text-white/85 drop-shadow md:text-[15px]"
                    x-show="current().description"
                    x-text="current().description"
                ></p>
                <div class="mt-8">
                    <a
                        :href="current().playUrl"
                        class="inline-flex items-center gap-2 rounded-xl bg-white px-8 py-3.5 text-sm font-bold uppercase tracking-[0.15em] text-zinc-950 shadow-xl transition hover:bg-white/90"
                    >
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.5 5.653c0-1.427 1.529-2.33 2.779-1.643l11.54 6.347c1.29.712 1.29 2.582 0 3.295L7.28 19.99c-1.25.687-2.779-.217-2.779-1.643V5.653Z" clip-rule="evenodd" />
                        </svg>
                        Reproducir
                    </a>
                </div>
            </div>
        </div>

        <div class="pointer-events-auto absolute bottom-6 left-1/2 z-[12] flex -translate-x-1/2 gap-2 sm:bottom-8">
            <template x-for="(slide, idx) in slides" :key="'dot-'+idx">
                <button
                    type="button"
                    class="h-2 rounded-full transition-all"
                    :class="i === idx ? 'w-8 bg-white' : 'w-2 bg-white/35 hover:bg-white/55'"
                    @click="go(idx)"
                    :aria-label="'Ir al título ' + (idx + 1)"
                ></button>
            </template>
        </div>

        <button
            type="button"
            class="pointer-events-auto absolute left-3 top-1/2 z-[12] hidden -translate-y-1/2 rounded-full border border-white/20 bg-black/45 p-3 text-white backdrop-blur transition hover:bg-black/60 md:block"
            @click="prev()"
            aria-label="Anterior"
        >
            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
        </button>
        <button
            type="button"
            class="pointer-events-auto absolute right-3 top-1/2 z-[12] hidden -translate-y-1/2 rounded-full border border-white/20 bg-black/45 p-3 text-white backdrop-blur transition hover:bg-black/60 md:block"
            @click="next()"
            aria-label="Siguiente"
        >
            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
        </button>
    </section>
@endif
