@php
    $loginFingerprintPng = public_path('images/login-fingerprint.png');
    $loginFingerprintUrl = is_file($loginFingerprintPng)
        ? asset('images/login-fingerprint.png?v='.(string) @filemtime($loginFingerprintPng).'.'.(string) @filesize($loginFingerprintPng))
        : null;
@endphp

@push('body')
    @if (session('screen_limit_popup'))
        <div
            id="screen-limit-overlay"
            class="fixed inset-0 z-[400] flex items-center justify-center bg-black/80 p-5 backdrop-blur-sm"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="screen-limit-title"
            aria-describedby="screen-limit-desc"
        >
            <div class="w-full max-w-md rounded-2xl border-2 border-red-500 bg-gradient-to-b from-red-950 to-[#1a0505] px-6 py-8 text-center shadow-[0_0_48px_rgba(239,68,68,0.35)] ring-1 ring-red-400/40">
                <p class="text-xs font-bold uppercase tracking-[0.35em] text-red-300/90">Límite de sesiones</p>
                <h2 id="screen-limit-title" class="mt-4 text-xl font-black leading-snug text-red-100 sm:text-2xl">
                    Excediste el límite de pantallas
                </h2>
                <p id="screen-limit-desc" class="mt-4 text-sm leading-relaxed text-red-200/90">
                    {{ session('screen_limit_popup') }}
                </p>
                <button
                    type="button"
                    id="screen-limit-dismiss"
                    class="mt-8 w-full rounded-xl border border-red-400/50 bg-red-600/90 px-4 py-3 text-sm font-bold uppercase tracking-wider text-white shadow-lg transition hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-400"
                >
                    Entendido
                </button>
            </div>
        </div>
        <script>
            (function () {
                var overlay = document.getElementById('screen-limit-overlay');
                var btn = document.getElementById('screen-limit-dismiss');
                if (!overlay) return;
                document.body.style.overflow = 'hidden';
                btn?.addEventListener('click', function () {
                    overlay.remove();
                    document.body.style.overflow = '';
                });
            })();
        </script>
    @endif

    <div
        id="login-fingerprint-overlay"
        class="fixed inset-0 z-[300] hidden flex-col items-center justify-center bg-slate-950/70 p-6 backdrop-blur-md"
        role="dialog"
        aria-modal="true"
        aria-labelledby="login-fp-title"
        aria-hidden="true"
    >
        <p class="mb-2 text-xs font-semibold uppercase tracking-[0.35em] text-amber-200/90">{{ config('streaming.login_brand_display', 'DIGITALVISION') }}</p>
        <h2 id="login-fp-title" class="mb-10 text-center text-lg font-semibold text-white">Verificando identidad…</h2>

        <div class="relative mx-auto flex min-h-[13.5rem] min-w-[13.5rem] items-center justify-center sm:min-h-[15.5rem] sm:min-w-[15.5rem]">
            <div class="pointer-events-none absolute inset-[-18%] rounded-full bg-[radial-gradient(ellipse_at_center,rgba(56,189,248,0.22)_0%,transparent_62%,rgba(167,139,250,0.18)_100%)] blur-2xl" aria-hidden="true"></div>

            @if ($loginFingerprintUrl)
                <div class="login-fingerprint-scan-clip relative z-[1] flex items-center justify-center">
                    <img
                        src="{{ $loginFingerprintUrl }}"
                        alt=""
                        width="280"
                        height="280"
                        decoding="async"
                        class="login-fingerprint-icon-pulse mix-blend-screen h-[11.5rem] w-[11.5rem] max-h-[min(72vw,17rem)] max-w-[min(72vw,17rem)] object-contain drop-shadow-[0_12px_40px_rgba(0,0,0,0.55)] sm:h-[13.5rem] sm:w-[13.5rem]"
                    />
                    <div class="pointer-events-none absolute inset-[6%] z-[2] overflow-hidden rounded-[2rem]">
                        <div class="login-fingerprint-scan-line absolute left-0 right-0 h-[3px] rounded-full bg-gradient-to-r from-amber-400 via-lime-300 to-sky-400 shadow-[0_0_16px_rgba(250,204,21,0.95)]" style="top: 10%"></div>
                    </div>
                </div>
            @else
                <div class="relative flex h-52 w-52 items-center justify-center overflow-hidden rounded-3xl border border-white/15 bg-white/5 shadow-[0_32px_60px_-15px_rgba(0,0,0,0.55)] sm:h-56 sm:w-56">
                    <svg class="login-fingerprint-icon-pulse relative z-[1] h-[7.25rem] w-[7.25rem] text-white sm:h-32 sm:w-32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <ellipse cx="12" cy="10.5" rx="5.2" ry="7" />
                        <path d="M12 3.5c-2.8 0-4.8 2.4-4.8 5.2v.8c0 .8.2 1.5.5 2.2" opacity=".45"/>
                        <path d="M8.2 12.3c.6 2 2.4 3.4 4.6 3.4h.4c2.4 0 4.3-1.8 4.6-4" opacity=".65"/>
                        <path d="M9.5 7.5c0 2 1.6 3.6 3.6 3.6s3.6-1.6 3.6-3.6" opacity=".5"/>
                        <path d="M10 16.2c.9 1.3 2.4 2.1 4 2.1s3.1-.8 4-2.1" opacity=".85"/>
                        <path d="M7 14.5v.3c0 2.8 2.3 5 5.1 5h.2c1.4 0 2.6-.6 3.4-1.5" />
                    </svg>
                    <div class="pointer-events-none absolute inset-x-5 top-0 bottom-0 z-[2] overflow-hidden rounded-2xl">
                        <div class="login-fingerprint-scan-line absolute left-0 right-0 h-[3px] rounded-full bg-gradient-to-r from-amber-400 via-lime-300 to-sky-400 shadow-[0_0_14px_rgba(250,204,21,0.95)]" style="top: 10%"></div>
                    </div>
                </div>
            @endif
        </div>

        <p class="mt-10 text-sm font-medium tracking-wide text-slate-300">Escaneando acceso seguro…</p>
    </div>

    <script>
        (function () {
            const form = document.getElementById('login-form');
            const overlay = document.getElementById('login-fingerprint-overlay');
            const btn = document.getElementById('login-submit-btn');
            if (!form || !overlay) return;

            const SCAN_MS = 2300;

            form.addEventListener('submit', function (e) {
                if (form.dataset.authSubmitting === '1') {
                    return;
                }
                if (!form.checkValidity()) {
                    return;
                }
                e.preventDefault();
                overlay.classList.remove('hidden');
                overlay.classList.add('flex');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                if (btn) btn.disabled = true;

                const line = overlay.querySelector('.login-fingerprint-scan-line');
                if (line) {
                    line.classList.remove('login-fingerprint-scan-line');
                    void line.offsetWidth;
                    line.classList.add('login-fingerprint-scan-line');
                }

                window.setTimeout(function () {
                    form.dataset.authSubmitting = '1';
                    HTMLFormElement.prototype.submit.call(form);
                }, SCAN_MS);
            });
        })();
    </script>
@endpush

<x-streaming-login-layout>
    <x-auth-session-status class="mb-6 text-center text-[13px] font-medium text-lime-300" :status="session('status')" />

    <form id="login-form" method="POST" action="{{ route('login') }}" class="space-y-6" novalidate>
        @csrf

        <div class="space-y-2">
            <label class="sr-only" for="email">{{ __('Email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="w-full rounded-2xl border-2 border-cyan-500/25 bg-black/40 px-4 py-3.5 text-[15px] font-medium text-white shadow-inner outline-none ring-0 placeholder:text-white/40 focus:border-cyan-400/60 focus:bg-black/55 focus:shadow-[0_0_18px_rgba(34,211,238,0.15)]"
                   placeholder="{{ __('Correo electrónico') }}">

            <x-input-error :messages="$errors->get('email')" class="text-center text-sm text-red-300" />
        </div>

        <div class="space-y-2">
            <label class="sr-only" for="password">{{ __('Password') }}</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="w-full rounded-2xl border-2 border-fuchsia-500/25 bg-black/40 px-4 py-3.5 text-[15px] font-medium text-white shadow-inner outline-none placeholder:text-white/40 focus:border-fuchsia-400/55 focus:bg-black/55 focus:shadow-[0_0_18px_rgba(217,70,239,0.12)]"
                   placeholder="{{ __('Contraseña') }}">

            <x-input-error :messages="$errors->get('password')" class="text-center text-sm text-red-300" />
        </div>

        <div class="hidden">
            <label for="remember_me" class="inline-flex cursor-pointer select-none items-center gap-2.5 font-medium">
                <input id="remember_me" type="checkbox" name="remember" class="h-4 w-4 rounded border-cyan-500/40 bg-black/30 text-cyan-500 focus:ring-cyan-400/50">
                {{ __('Remember me') }}
            </label>
            @if (Route::has('password.request'))
                <a class="font-semibold text-cyan-300 underline decoration-2 underline-offset-2 hover:text-cyan-200" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
            @endif
        </div>

        <button id="login-submit-btn" type="submit" class="w-full rounded-2xl border border-cyan-400/35 bg-gradient-to-r from-cyan-600/80 via-fuchsia-600/75 to-cyan-600/80 py-3.5 text-[13px] font-bold uppercase tracking-[0.28em] text-white shadow-[0_0_28px_rgba(34,211,238,0.25)] transition hover:brightness-110 hover:shadow-[0_0_36px_rgba(217,70,239,0.2)] focus:outline-none focus:ring-2 focus:ring-cyan-400/50 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60">
            {{ __('Entrar') }}
        </button>
    </form>
</x-streaming-login-layout>
