<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#050814]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reproduciendo — {{ $content->title }}</title>
    @vite(['resources/css/app.css'])
    <link href="https://vjs.zencdn.net/8.16.1/video-js.css" rel="stylesheet" />
    <style>
        .video-js { width: 100%; height: 70vh; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .r-spin { animation: spin 0.9s linear infinite; }
    </style>
</head>
<body class="streaming-cyber-player min-h-full text-white antialiased">
    <div class="mx-auto max-w-6xl p-4">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <a href="{{ route('app.home') }}" class="text-sm font-semibold text-cyan-300 transition hover:text-cyan-200 hover:underline">&larr; Volver al catálogo</a>
            <span class="max-w-[min(100%,28rem)] truncate text-sm text-white/50">{{ $content->title }}</span>
        </div>
        <div id="reconnect-banner" class="mb-3 hidden rounded-lg border border-cyan-500/40 bg-cyan-950/50 px-4 py-2 text-center text-sm text-cyan-100">
            <span class="inline-block h-4 w-4 align-[-2px] rounded-full border-2 border-cyan-300 border-t-transparent r-spin" aria-hidden="true"></span>
            <span id="reconnect-msg" class="ms-2">Reconectando…</span>
        </div>
        @if ($ffmpegTranscodeAvailable)
            <div class="mb-3 flex justify-end">
                <button type="button" id="btn-transcode" class="rounded-lg border border-amber-400/50 bg-amber-500/15 px-3 py-1.5 text-xs font-semibold text-amber-100 hover:bg-amber-500/25">
                    Usar transcodificación (AAC)
                </button>
            </div>
        @endif
        <video
            id="player"
            class="video-js vjs-big-play-centered vjs-fluid rounded-xl ring-1 ring-cyan-500/20 shadow-[0_0_40px_-8px_rgba(34,211,238,0.15)]"
            controls
            preload="metadata"
            playsinline
            data-setup="{}"
        ></video>
        <p id="err" class="mt-4 hidden text-center text-red-400"></p>
    </div>
    <script src="https://vjs.zencdn.net/8.16.1/video.min.js"></script>
    <script>
        let src = @json($src);
        let isHls = @json($isHls);
        let type = @json($sourceMime);
        const isLivePlayback = @json($isLivePlayback ?? false);
        const transcodeSrc = @json($transcodeSrc ?? '');
        const ffmpegAvailable = @json($ffmpegTranscodeAvailable ?? false);

        const delays = [1000, 2000, 4000];
        let retryCount = 0;
        const maxRetries = 3;
        let usingTranscode = false;

        const player = videojs('player', {
            fluid: true,
            responsive: true,
            liveui: isLivePlayback && isHls,
            html5: {
                vhs: {
                    overrideNative: true,
                    maxPlaylistRetries: 30,
                    experimentalLLHLS: false,
                    // En vivo: arrancar por la variante más baja evita quedarse “cargando” si la CDN tarda en la HD.
                    enableLowInitialPlaylist: isLivePlayback,
                    maxBufferLength: isLivePlayback ? 30 : 60,
                    maxMaxBufferLength: isLivePlayback ? 60 : 120,
                    bandwidth: isLivePlayback ? 500000 : 8000000,
                    useBandwidthFromLocalStorage: false,
                    xhr: { timeout: 45000 },
                    liveRangeSafeTimeDelta: 4,
                    liveSyncDurationCount: 3,
                    handleManifestRedirects: true,
                },
            },
            sources: [{ src, type }],
        });

        function backoffDelay(code, attempt) {
            if (code === 3 && attempt === 1) return 1000;
            const i = Math.min(attempt - 1, delays.length - 1);
            return delays[Math.max(0, i)];
        }

        function showReconnect(show) {
            const b = document.getElementById('reconnect-banner');
            if (show) b.classList.remove('hidden');
            else b.classList.add('hidden');
        }

        function reloadSource() {
            player.error(null);
            player.src({ src, type });
            player.load();
            player.play().catch(function () {});
        }

        player.on('error', function () {
            const p = document.getElementById('err');
            const code = player.error()?.code ?? 0;

            if ((code === 2 || code === 3 || code === 4) && retryCount < maxRetries) {
                retryCount++;
                const wait = backoffDelay(code, retryCount);
                showReconnect(true);
                document.getElementById('reconnect-msg').textContent =
                    'Reconectando… intento ' + retryCount + ' de ' + maxRetries + ' (espera ' + (wait / 1000) + 's)';
                setTimeout(function () {
                    showReconnect(false);
                    reloadSource();
                }, wait);
                return;
            }

            showReconnect(false);
            p.classList.remove('hidden');
            if (retryCount >= maxRetries && ffmpegAvailable && !usingTranscode && transcodeSrc) {
                p.innerHTML = 'Varios reintentos fallaron. <button type="button" class="underline text-amber-300" id="err-use-transcode">Probar transcodificación AAC</button>';
                document.getElementById('err-use-transcode')?.addEventListener('click', function () {
                    usingTranscode = true;
                    isHls = false;
                    type = 'video/mp4';
                    src = transcodeSrc;
                    p.classList.add('hidden');
                    reloadSource();
                });
                return;
            }
            if (code === 2) {
                p.textContent = 'Error de red: el servidor no respondió a tiempo. Verifica que HLS_UPSTREAM_REFERER esté configurado en .env.';
            } else if (code === 4) {
                p.textContent = 'Formato no soportado o canal caído. Si la URL no termina en .m3u8, el canal puede requerir configuración adicional.';
            } else {
                p.textContent = 'No se pudo reproducir. Los canales HLS remotos se proxifican por tu servidor; si la fuente exige cabeceras especiales, VPN o no admite reproducción web, seguirá fallando.';
            }
            p.textContent += ' Código técnico: ' + code + '.';
        });

        document.getElementById('btn-transcode')?.addEventListener('click', function () {
            if (!transcodeSrc) return;
            usingTranscode = true;
            isHls = false;
            type = 'video/mp4';
            src = transcodeSrc;
            reloadSource();
        });

        if (isLivePlayback) {
            let stallTimer = null;
            player.on('waiting', function () {
                stallTimer = setTimeout(function () {
                    if (player.paused() || player.seeking()) return;
                    var currentTime = player.currentTime();
                    player.currentTime(currentTime + 1);
                }, 15000);
            });
            player.on('playing', function () {
                if (stallTimer) { clearTimeout(stallTimer); stallTimer = null; }
                retryCount = 0;
                showReconnect(false);
                document.getElementById('err').classList.add('hidden');
            });
        }
    </script>
</body>
</html>
