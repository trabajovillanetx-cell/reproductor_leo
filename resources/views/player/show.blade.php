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
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; }
        .video-js { width: 100%; height: 40vh; }
        @media (min-width: 768px) { .video-js { height: 70vh; } }
        @keyframes spin { to { transform: rotate(360deg); } }
        .r-spin { animation: spin 0.9s linear infinite; }
        .btn-catalogo {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 9999px;
            border: 1px solid rgba(6,182,212,0.4);
            background: rgba(8,47,73,0.7);
            color: #67e8f9;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            backdrop-filter: blur(4px);
            transition: background 0.2s, color 0.2s;
        }
        .btn-catalogo:hover { background: rgba(8,47,73,1); color: #e0f2fe; }
        .player-wrap {
            max-width: 900px;
            margin: 0 auto;
            padding: 12px 12px 0;
        }
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            gap: 8px;
        }
        .channel-name {
            font-size: 13px;
            color: rgba(255,255,255,0.45);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 55%;
        }
    </style>
</head>
<body class="streaming-cyber-player min-h-full text-white antialiased">
    <div class="player-wrap">
        <div class="top-bar">
            <a href="{{ route('app.home') }}" class="btn-catalogo">&#8592; Volver al cat&aacute;logo</a>
            <span class="channel-name">{{ $content->title }}</span>
        </div>
        <div id="reconnect-banner" class="mb-3 hidden rounded-lg border border-cyan-500/40 bg-cyan-950/50 px-4 py-2 text-center text-sm text-cyan-100">
            <span class="inline-block h-4 w-4 align-[-2px] rounded-full border-2 border-cyan-300 border-t-transparent r-spin" aria-hidden="true"></span>
            <span id="reconnect-msg" class="ms-2">Reconectando...</span>
        </div>
        <video
            id="player"
            class="video-js vjs-big-play-centered vjs-fluid rounded-xl ring-1 ring-cyan-500/20 shadow-[0_0_40px_-8px_rgba(34,211,238,0.15)]"
            controls
            preload="metadata"
            playsinline
            data-setup="{}"
        ></video>
        <p id="err" class="mt-4 hidden text-center text-red-400 text-sm px-2"></p>
    </div>
    <script src="https://vjs.zencdn.net/8.16.1/video.min.js"></script>
    <script>
        let src           = @json($src);
        let isHls         = @json($isHls);
        let type          = @json($sourceMime);
        const isLivePlayback  = @json($isLivePlayback ?? false);
        const transcodeSrc    = @json($transcodeSrc ?? '');
        const ffmpegAvailable = @json($ffmpegTranscodeAvailable ?? false);

        const delays   = [1000, 2000, 4000];
        let retryCount = 0;
        const maxRetries = 3;
        let usingTranscode = false;

        const needsAudioTranscode = @json($needsAudioTranscode ?? false);

        if (ffmpegAvailable && isLivePlayback && transcodeSrc) {
            usingTranscode = true;
            isHls = true;
            type  = 'application/x-mpegURL';
            src   = transcodeSrc;
        } else if (ffmpegAvailable && needsAudioTranscode && transcodeSrc) {
            usingTranscode = true;
            isHls = false;
            type  = 'video/mp4';
            src   = transcodeSrc;
        }

        // Habilitar fullscreen en WebView Android
        if (typeof document.documentElement.requestFullscreen === "undefined") {
            document.documentElement.requestFullscreen = function() {
                if (this.webkitRequestFullscreen) return this.webkitRequestFullscreen();
            };
        }
        const player = videojs('player', {
            fluid: true,
            responsive: true,
            liveui: isLivePlayback,
            seekBarBehavior: isLivePlayback ? "live" : "default",
            controls: true,
            preload: "auto",
            fullscreen: { options: { navigationUI: "hide" } },
            html5: {
                vhs: {
                    overrideNative: true,
                    maxPlaylistRetries: 30,
                    experimentalLLHLS: false,
                    enableLowInitialPlaylist: isLivePlayback,
                    maxBufferLength: isLivePlayback ? 20 : 60,
                    maxMaxBufferLength: isLivePlayback ? 40 : 120,
                    bandwidth: isLivePlayback ? 3000000 : 8000000,
                    useBandwidthFromLocalStorage: false,
                    xhr: { timeout: 30000 },
                    liveRangeSafeTimeDelta: 4,
                    liveSyncDurationCount: 3,
                    liveMaxLatencyDurationCount: 6,
                    allowSeeksWithinUnsafeLiveWindow: true,
                    handleManifestRedirects: true,
                },
            },
            sources: [{ src: src, type: type }],
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
            player.src({ src: src, type: type });
            player.load();
            player.play().catch(function () {});
        }

        player.on('error', function () {
            const p    = document.getElementById('err');
            const code = player.error()?.code ?? 0;

            if ((code === 2 || code === 3 || code === 4) && retryCount < maxRetries) {
                retryCount++;
                const wait = backoffDelay(code, retryCount);
                showReconnect(true);
                document.getElementById('reconnect-msg').textContent =
                    'Reconectando... intento ' + retryCount + ' de ' + maxRetries;
                setTimeout(function () {
                    showReconnect(false);
                    reloadSource();
                }, wait);
                return;
            }

            showReconnect(false);
            p.classList.remove('hidden');
            if (code === 2) {
                p.textContent = 'Error de red. Codigo: ' + code;
            } else if (code === 4) {
                p.textContent = 'Formato no soportado o canal caido. Codigo: ' + code;
            } else {
                p.textContent = 'No se pudo reproducir. Codigo: ' + code;
            }
        });

        if (isLivePlayback) {
            let stallTimer = null;
            player.on('waiting', function () {
                stallTimer = setTimeout(function () {
                    if (player.paused() || player.seeking() || window._adminPaused) return;
                    player.currentTime(player.currentTime() + 1);
                }, 10000);
            });
            player.on('playing', function () {
                if (stallTimer) { clearTimeout(stallTimer); stallTimer = null; }
                retryCount = 0;
                showReconnect(false);
                document.getElementById('err').classList.add('hidden');
            });
        }

        const heartbeatUrl = @json(route('player.heartbeat', ['content' => $content->id, 'token' => $token]));
        const csrfToken    = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        function sendHeartbeat(status, isInterval) {
            fetch(heartbeatUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ 
                    status: status, 
                    interval: isInterval ? 1 : 0,
                    position: Math.floor(player.currentTime() || 0),
                    duration: (function(){ var d = player.duration(); return (d && isFinite(d)) ? Math.floor(d) : 0; })()
                }),
            }).then(function(r) { return r.json(); }).then(function(data) {
                console.log('HB response:', JSON.stringify(data));
                if (!data.command) return;
                if (data.command === 'pause') {
                    window._adminPaused = true;
                    player.pause();
                    showAdminMessage('⏸ El administrador pausó la reproducción.');
                } else if (data.command === 'stop') {
                    window._adminPaused = true;
                    player.pause();
                    player.currentTime(0);
                    showAdminMessage('⏹ El administrador detuvo la reproducción.');
                } else if (data.command === 'kick') {
                    player.dispose();
                    document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;background:#050814;color:white;font-family:sans-serif;text-align:center;padding:2rem"><div><h2 style="font-size:1.5rem;margin-bottom:1rem">Sesión terminada</h2><p style="color:#94a3b8">El administrador cerró tu sesión de reproducción.</p></div></div>';
                } else if (data.command === 'message' && data.command_data) {
                    showAdminMessage(data.command_data);
                }
            }).catch(function () {});
        }

        function showAdminMessage(msg) {
            var el = document.getElementById('admin-msg');
            if (!el) {
                el = document.createElement('div');
                el.id = 'admin-msg';
                el.style.cssText = 'position:absolute;top:20px;left:50%;transform:translateX(-50%);background:rgba(15,23,42,0.92);color:white;border:1px solid #22d3ee;border-radius:12px;padding:14px 24px;font-size:14px;z-index:9999;box-shadow:0 0 20px rgba(34,211,238,0.4);max-width:90%;text-align:center;pointer-events:none;';
            }
            // Insertar dentro del contenedor del player para que aparezca en fullscreen
            var container = document.getElementById('player') || document.body;
            var parent = container.closest('.video-js') || container.parentElement || document.body;
            parent.style.position = 'relative';
            parent.appendChild(el);
            el.textContent = msg;
            el.style.display = 'block';
            setTimeout(function() { el.style.display = 'none'; }, 6000);
        }

        const heartbeatInterval = setInterval(function () {
            const status = player.paused() ? 'paused' : (player.seeking() ? 'buffering' : 'playing');
            sendHeartbeat(status, true);
        }, 15000);

        window.addEventListener('beforeunload', function () {
            clearInterval(heartbeatInterval);
            sendHeartbeat('ended');
        });

        player.on('pause',   function () { sendHeartbeat('paused'); });
        player.on('play',    function () { window._adminPaused = false; sendHeartbeat('playing'); });
        player.on('waiting', function () { sendHeartbeat('buffering'); });
    </script>
</body>
</html>