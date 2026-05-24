import './bootstrap';

import Alpine from 'alpinejs';

document.addEventListener('alpine:init', () => {
    Alpine.data('streamingHero', (slides, cfg = {}) => ({
        slides: Array.isArray(slides) ? slides : [],
        i: 0,
        timer: null,
        previewUrlTpl: typeof cfg.previewUrlTemplate === 'string' ? cfg.previewUrlTemplate : '',
        carouselIntervalMs: (() => {
            const raw = cfg.carouselIntervalMs;
            const n = Number(raw);
            if (Number.isFinite(n) && n >= 3000 && n <= 180000) {
                return Math.floor(n);
            }

            return 8000;
        })(),
        heroHls: null,
        previewShowing: false,
        _videoEndedLoop: null,

        init() {
            this.$watch('i', () => {
                this.refreshAutoplayTimer();
                this.teardownPreview();
                this.$nextTick(() => {
                    void this.mountPreviewForIndex(this.i);
                });
            });
            this.refreshAutoplayTimer();
            this.$nextTick(() => {
                void this.mountPreviewForIndex(this.i);
            });
        },

        destroy() {
            if (this.timer) {
                window.clearInterval(this.timer);
            }
            this.teardownPreview();
        },

        current() {
            return this.slides[this.i] ?? {};
        },

        next() {
            if (!this.slides.length) {
                return;
            }
            this.i = (this.i + 1) % this.slides.length;
        },

        prev() {
            if (!this.slides.length) {
                return;
            }
            this.i = (this.i - 1 + this.slides.length) % this.slides.length;
        },

        go(idx) {
            this.i = idx;
        },

        /** Mismo tiempo que el “fragmento”: ~40 s por título antes de rotar */
        refreshAutoplayTimer() {
            if (this.timer) {
                window.clearInterval(this.timer);
                this.timer = null;
            }
            if (!this.slides || this.slides.length <= 1) {
                return;
            }
            this.timer = window.setInterval(() => this.next(), this.carouselIntervalMs);
        },

        teardownPreview() {
            this.previewShowing = false;
            const v = this.$refs.heroPreviewVideo;
            if (this._videoEndedLoop && v) {
                v.removeEventListener('ended', this._videoEndedLoop);
            }
            this._videoEndedLoop = null;
            try {
                this.heroHls?.destroy();
            } catch {
                //
            }
            this.heroHls = null;
            if (v) {
                v.pause();
                v.removeAttribute('src');
                try {
                    v.load();
                } catch {
                    //
                }
            }
        },

        async mountPreviewForIndex(idx) {
            const slide = this.slides[idx];
            if (!slide?.preview || !slide?.streamUrl) {
                return;
            }

            const v = this.$refs.heroPreviewVideo;
            if (!v) {
                return;
            }

            const payload = {
                ok: true,
                stream_url: slide.streamUrl,
                is_hls: slide.isHls ?? false,
                mime: slide.isHls ? 'application/x-mpegURL' : 'video/mp4',
                clip_seconds: 40,
            };

            v.muted = true;
            v.loop = false;
            v.playsInline = true;
            v.preload = 'auto';
            v.setAttribute('playsinline', '');
            v.setAttribute('webkit-playsinline', '');
            v.removeAttribute('poster');

            /** Si el archivo es más corto que la ventana, se reinicia hasta que el carrusel avance solo */
            this._videoEndedLoop = () => {
                try {
                    v.currentTime = 0;
                    void v.play();
                } catch {
                    //
                }
            };
            v.addEventListener('ended', this._videoEndedLoop);

            const isHls =
                payload.is_hls === true ||
                payload.mime === 'application/x-mpegURL';

            const tryPlay = () => {
                v.play()
                    .then(() => {
                        this.previewShowing = true;
                    })
                    .catch(() => {
                        this.previewShowing = false;
                    });
            };

            if (isHls && v.canPlayType('application/vnd.apple.mpegurl')) {
                v.src = payload.stream_url;
                v.addEventListener('loadedmetadata', tryPlay, { once: true });
            } else if (isHls) {
                try {
                    const mod = await import('hls.js');
                    const Hls = mod.default;
                    if (!Hls.isSupported()) {
                        return;
                    }
                    this.heroHls = new Hls({
                        // Buffer
                        maxBufferLength: 60,
                        maxMaxBufferLength: 90,
                        maxBufferSize: 150 * 1000 * 1000,
                        maxBufferHole: 1.0,
                        // Live sync — latencia ~5 segmentos (5x4s = 20s, más estable)
                        liveSyncDurationCount: 5,
                        liveMaxLatencyDurationCount: 10,
                        liveDurationInfinity: true,
                        // Inicio y niveles
                        startLevel: -1,
                        capLevelToPlayerSize: true,
                        // Reconexión ante errores de red
                        fragLoadingMaxRetry: 6,
                        fragLoadingRetryDelay: 1000,
                        fragLoadingMaxRetryTimeout: 8000,
                        manifestLoadingMaxRetry: 4,
                        manifestLoadingRetryDelay: 1000,
                        levelLoadingMaxRetry: 4,
                        levelLoadingRetryDelay: 1000,
                        // ABR
                        abrEwmaDefaultEstimate: 3000000,
                        testBandwidth: true,
                    });
                    this.heroHls.loadSource(payload.stream_url);
                    this.heroHls.attachMedia(v);
                    this.heroHls.on(Hls.Events.MANIFEST_PARSED, () => tryPlay());
                    // Reconexión automática ante errores fatales
                    let _hlsRetries = 0;
                    const _hlsMaxRetries = 5;
                    this.heroHls.on(Hls.Events.ERROR, (event, data) => {
                        if (!data.fatal) return;
                        if (_hlsRetries >= _hlsMaxRetries) {
                            console.warn('HLS: máximo de reintentos alcanzado');
                            this.heroHls.destroy();
                            return;
                        }
                        _hlsRetries++;
                        const delay = Math.min(1000 * _hlsRetries, 8000);
                        console.warn('HLS error fatal:', data.type, '— reintentando en', delay, 'ms');
                        setTimeout(() => {
                            if (!this.heroHls) return;
                            if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                                this.heroHls.startLoad();
                            } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                                this.heroHls.recoverMediaError();
                            } else {
                                this.heroHls.destroy();
                                this.heroHls = new Hls();
                                this.heroHls.loadSource(payload.stream_url);
                                this.heroHls.attachMedia(v);
                            }
                        }, delay);
                    });
                } catch {
                    return;
                }
            } else {
                v.src = payload.stream_url;
                v.addEventListener('canplay', tryPlay, { once: true });
            }
        },
    }));

    /** Panel admin M3U — barrido async; debe vivir aquí para que Alpine resuelva el componente (no depender de globals). */
    Alpine.data('m3uCullPanel', () => ({
        cullScope: 'all',
        cullType: 'live',
        cullCategoryId: '',
        status: null,
        errorMsg: '',
        pollTimer: null,
        statusUrl: '',
        asyncUrl: '',

        init() {
            this.statusUrl = this.$el.dataset.statusUrl || '';
            this.asyncUrl = this.$el.dataset.asyncUrl || '';
            void this.fetchStatus();
        },

        destroy() {
            if (this.pollTimer) {
                window.clearTimeout(this.pollTimer);
                this.pollTimer = null;
            }
        },

        async fetchStatus() {
            if (!this.statusUrl) {
                return;
            }
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const r = await fetch(this.statusUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    credentials: 'same-origin',
                });
                const data = await r.json();
                this.status = data.status ?? null;
            } catch {
                //
            }

            if (this.pollTimer) {
                window.clearTimeout(this.pollTimer);
                this.pollTimer = null;
            }
            if (this.status?.running) {
                this.pollTimer = window.setTimeout(() => void this.fetchStatus(), 8000);
            }
        },

        async launch(dryRun) {
            this.errorMsg = '';
            if (this.cullScope === 'category' && !this.cullCategoryId) {
                this.errorMsg = 'Elige una categoría o cambia el ámbito a «todos» o «por tipo».';
                return;
            }

            if (this.cullScope === 'type' && !['vod', 'live', 'series'].includes(this.cullType)) {
                this.errorMsg = 'Elige un tipo válido (VOD, TV en vivo o series).';
                return;
            }

            if (!dryRun && !window.confirm('Se eliminarán del catálogo las filas cuya URL remota no responda. ¿Continuar?')) {
                return;
            }

            if (!this.asyncUrl) {
                this.errorMsg = 'No está configurada la URL del barrido.';
                return;
            }

            const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const body = new FormData();
            body.append('_token', token);
            body.append('cull_scope', this.cullScope);
            if (this.cullCategoryId) {
                body.append('cull_category_id', this.cullCategoryId);
            }
            if (this.cullScope === 'type') {
                body.append('cull_type', this.cullType);
            }
            body.append('dry_run', dryRun ? '1' : '0');

            try {
                const r = await fetch(this.asyncUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    credentials: 'same-origin',
                    body,
                });
                let data;
                try {
                    data = await r.json();
                } catch {
                    this.errorMsg = 'Respuesta inválida del servidor.';
                    return;
                }
                if (!data.ok) {
                    this.errorMsg = data.message ?? 'Error al iniciar el barrido.';
                    return;
                }
                window.setTimeout(() => void this.fetchStatus(), 1500);
            } catch {
                this.errorMsg = 'Error de red al iniciar el barrido.';
            }
        },
    }));

    /**
     * Escaneo síncrono en gestión M3U: un POST devuelve el informe completo (sin cola).
     */
    Alpine.data('m3uChannelScanPanel', () => ({
        scanUrl: '',
        deleteUrl: '',
        filterMode: 'all',
        scanning: false,
        deleting: false,
        errorMsg: '',
        successMsg: '',
        report: null,
        selectedIds: {},

        init() {
            this.scanUrl = this.$el.dataset.scanUrl || '';
            this.deleteUrl = this.$el.dataset.deleteUrl || '';
        },

        isSelected(id) {
            return !!this.selectedIds[String(id)];
        },

        toggleSelect(id) {
            const k = String(id);
            if (this.selectedIds[k]) {
                const next = { ...this.selectedIds };
                delete next[k];
                this.selectedIds = next;
            } else {
                this.selectedIds = { ...this.selectedIds, [k]: true };
            }
        },

        selectAllDeadListed() {
            const next = { ...this.selectedIds };
            for (const row of this.report?.dead ?? []) {
                next[String(row.id)] = true;
            }
            this.selectedIds = next;
        },

        clearSelection() {
            this.selectedIds = {};
        },

        selectedCount() {
            return Object.keys(this.selectedIds).length;
        },

        async runScan() {
            this.errorMsg = '';
            this.successMsg = '';
            if (!this.scanUrl) {
                this.errorMsg = 'Falta configurar la URL de escaneo.';
                return;
            }
            this.scanning = true;
            this.report = null;
            this.clearSelection();
            const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const body = new FormData();
            body.append('_token', token);
            if (this.filterMode === 'all') {
                body.append('scan_scope', 'all');
            } else {
                body.append('scan_scope', 'type');
                body.append('scan_type', this.filterMode);
            }
            try {
                const r = await fetch(this.scanUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    credentials: 'same-origin',
                    body,
                });
                let data;
                try {
                    data = await r.json();
                } catch {
                    this.errorMsg = 'Respuesta inválida del servidor.';
                    return;
                }
                if (!data.ok) {
                    this.errorMsg = data.message ?? 'No se pudo completar el escaneo.';
                    return;
                }
                this.report = data.report ?? null;
            } catch {
                this.errorMsg = 'Error de red o tiempo agotado. Si la lista es enorme, subí max_execution_time / tiempo de nginx.';
            } finally {
                this.scanning = false;
            }
        },

        async deleteSelected() {
            this.errorMsg = '';
            this.successMsg = '';
            const ids = Object.keys(this.selectedIds).map((k) => Number(k)).filter((n) => Number.isFinite(n));
            if (ids.length === 0) {
                this.errorMsg = 'Marcá al menos un canal caído en la tabla.';
                return;
            }
            if (!window.confirm(`¿Eliminar ${ids.length} ítem(es) del catálogo? Esta acción no se puede deshacer.`)) {
                return;
            }
            if (!this.deleteUrl) {
                this.errorMsg = 'Falta configurar la URL de borrado.';
                return;
            }
            this.deleting = true;
            const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const body = new FormData();
            body.append('_token', token);
            for (const id of ids) {
                body.append('ids[]', String(id));
            }
            try {
                const r = await fetch(this.deleteUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    credentials: 'same-origin',
                    body,
                });
                let data;
                try {
                    data = await r.json();
                } catch {
                    this.errorMsg = 'Respuesta inválida del servidor.';
                    return;
                }
                if (!data.ok) {
                    this.errorMsg = data.message ?? 'No se pudo eliminar.';
                    return;
                }
                this.successMsg = data.message ?? 'Listo.';
                const removed = new Set(ids);
                if (this.report?.dead) {
                    this.report = {
                        ...this.report,
                        dead: this.report.dead.filter((row) => !removed.has(row.id)),
                        rows_unreachable: Math.max(0, (this.report.rows_unreachable ?? 0) - (data.deleted ?? 0)),
                    };
                }
                this.clearSelection();
            } catch {
                this.errorMsg = 'Error de red al eliminar.';
            } finally {
                this.deleting = false;
            }
        },
    }));
});

window.Alpine = Alpine;

Alpine.start();
