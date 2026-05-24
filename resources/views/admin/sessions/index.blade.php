<x-panel-layout title="Sesiones activas">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-white">Sesiones activas</h1>
            <p class="mt-1 max-w-2xl text-sm text-white/70">Clientes con reproducción en curso (heartbeat en los últimos 2 minutos). Se actualiza solo en esta página.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-sm text-white/80">
            <span>Última actualización: <strong id="updated-at" class="text-cyan-200">—</strong></span>
            <button type="button" id="btn-refresh" class="rounded-lg border border-cyan-500/40 bg-cyan-500/15 px-4 py-2 text-xs font-semibold text-cyan-100 hover:bg-cyan-500/25">
                Actualizar ahora
            </button>
        </div>
    </div>

    <div class="mb-8 grid gap-4 sm:grid-cols-3">
        <div class="admin-card border border-white/10 p-4 text-center">
            <p class="text-[11px] font-bold uppercase tracking-wider text-white/50">Conectados ahora</p>
            <p id="stat-total" class="mt-2 text-3xl font-bold text-white">{{ $stats['total_active'] }}</p>
        </div>
        <div class="admin-card border border-white/10 p-4 text-center">
            <p class="text-[11px] font-bold uppercase tracking-wider text-white/50">TV en vivo</p>
            <p id="stat-live" class="mt-2 text-3xl font-bold text-rose-200">{{ $stats['watching_live'] }}</p>
        </div>
        <div class="admin-card border border-white/10 p-4 text-center">
            <p class="text-[11px] font-bold uppercase tracking-wider text-white/50">Películas / series</p>
            <p id="stat-vod" class="mt-2 text-3xl font-bold text-indigo-200">{{ $stats['watching_vod'] }}</p>
        </div>
    </div>

    <div class="admin-card overflow-hidden border border-white/10">
        <div class="border-b border-white/10 px-4 py-3 text-sm text-white/70">
            Reproducciones activas: <strong id="total-count" class="text-white">{{ $stats['total_active'] }}</strong>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                <thead class="bg-white/[0.04] text-[11px] font-bold uppercase tracking-wider text-white/55">
                    <tr>
                        <th class="px-3 py-3">Poster</th>
                        <th class="px-3 py-3">Usuario</th>
                        <th class="px-3 py-3">Perfil</th>
                        <th class="px-3 py-3">Contenido</th>
                        <th class="px-3 py-3">Tipo</th>
                        <th class="px-3 py-3">Estado</th>
                        <th class="px-3 py-3">IP</th>
                        <th class="px-3 py-3">Dispositivo</th>
                        <th class="px-3 py-3">Navegador</th>
                        <th class="px-3 py-3">SO</th>
                        <th class="px-3 py-3">Hace</th>
                        <th class="px-3 py-3">Progreso</th>
                    </tr>
                </thead>
                <tbody id="sessions-body" class="divide-y divide-white/10 text-white/90">
                    <tr id="sessions-empty" class="{{ $stats['total_active'] > 0 ? 'hidden' : '' }}">
                        <td colspan="11" class="px-4 py-8 text-center text-white/55">Nadie reproduciendo en este momento.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        (function () {
            const dataUrl = @json(route('admin.active-sessions.data'));
            const tbody = document.getElementById('sessions-body');
            const emptyRow = document.getElementById('sessions-empty');

            function formatProgress(pos, dur) {
                function fmt(s) {
                    s = Math.floor(s || 0);
                    var h = Math.floor(s/3600), m = Math.floor((s%3600)/60), ss = s%60;
                    if (h > 0) return h + ":" + String(m).padStart(2,"0") + ":" + String(ss).padStart(2,"0");
                    return m + ":" + String(ss).padStart(2,"0");
                }
                if (!pos && !dur) return "—";
                if (dur > 0) return fmt(pos) + " / " + fmt(dur);
                return fmt(pos);
            }
            function statusBadge(status) {
                const s = (status || 'playing').toLowerCase();
                let label = 'Playing';
                let icon = '▶';
                let cls = 'border-emerald-500/50 bg-emerald-500/15 text-emerald-100';
                if (s === 'paused') {
                    label = 'Paused';
                    icon = '⏸';
                    cls = 'border-amber-500/50 bg-amber-500/15 text-amber-100';
                } else if (s === 'buffering') {
                    label = 'Buffering';
                    icon = '⏳';
                    cls = 'border-sky-500/50 bg-sky-500/15 text-sky-100';
                }
                return '<span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold ' + cls + '">' + icon + ' ' + label + '</span>';
            }

            function typeCell(row) {
                const t = row.content_type || '';
                let badge = '';
                if (t === 'live') {
                    badge = '<span class="ms-2 inline-flex rounded bg-red-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">En vivo</span>';
                }
                return '<span class="uppercase text-white/70">' + (t || '—') + '</span>' + badge;
            }

            function posterCell(url, title) {
                if (!url) {
                    return '<span class="inline-block h-12 w-9 rounded bg-white/10 ring-1 ring-white/15" title="' + (title || '') + '"></span>';
                }
                return '<img src="' + url.replace(/"/g, '&quot;') + '" alt="" class="h-12 w-9 rounded object-cover ring-1 ring-white/15" loading="lazy" referrerpolicy="no-referrer">';
            }

            function renderTable(sessions) {
                const keepEmpty = document.getElementById('sessions-empty');
                Array.from(tbody.querySelectorAll('tr[data-session]')).forEach(function (tr) {
                    tr.remove();
                });
                if (!sessions.length) {
                    keepEmpty.classList.remove('hidden');
                    return;
                }
                keepEmpty.classList.add('hidden');
                sessions.forEach(function (row) {
                    const tr = document.createElement('tr');
                    tr.setAttribute('data-session', String(row.id));
                    tr.className = 'hover:bg-white/[0.03]';
                    tr.innerHTML =
                        '<td class="px-3 py-2 align-middle">' + posterCell(row.content_poster, row.content_title) + '</td>' +
                        '<td class="px-3 py-2 align-middle"><div class="font-medium text-white">' + escapeHtml(row.user_name) + '</div>' +
                        '<div class="text-xs text-white/45">' + escapeHtml(row.user_email) + '</div></td>' +
                        '<td class="px-3 py-2 align-middle text-white/85">' + escapeHtml(row.profile_name) + '</td>' +
                        '<td class="px-3 py-2 align-middle max-w-[14rem]"><span class="line-clamp-2" title="' + escapeAttr(row.content_title) + '">' + escapeHtml(row.content_title) + '</span></td>' +
                        '<td class="px-3 py-2 align-middle whitespace-nowrap">' + typeCell(row) + '</td>' +
                        '<td class="px-3 py-2 align-middle">' + statusBadge(row.status) + '</td>' +
                        '<td class="px-3 py-2 align-middle font-mono text-xs text-white/80">' + escapeHtml(row.ip_address) + '</td>' +
                        '<td class="px-3 py-2 align-middle text-white/80">' + escapeHtml(row.device) + '</td>' +
                        '<td class="px-3 py-2 align-middle text-white/80">' + escapeHtml(row.browser) + '</td>' +
                        '<td class="px-3 py-2 align-middle text-white/80">' + escapeHtml(row.os) + '</td>' +
                        '<td class="px-3 py-2 align-middle whitespace-nowrap text-white/65" title="' + escapeAttr(row.started_at) + '">' + escapeHtml(row.last_seen) + '</td>' +
                        '<td class="px-3 py-2 align-middle whitespace-nowrap text-xs text-white/60">' + formatProgress(row.position_seconds, row.duration_seconds) + '</td>' +
                        '<td class="px-3 py-2 align-middle">' + actionButtons(row.id) + '</td>';
                    tbody.appendChild(tr);
                });
            }

            function actionButtons(tokenId) {
                var menuId = "menu-" + tokenId;
                return "<div class=\"relative inline-block\">" +
                    "<button onclick=\"toggleMenu(" + tokenId + ")\" class=\"rounded-lg px-3 py-1.5 text-xs font-semibold bg-white/10 text-white/80 hover:bg-white/20\">⚙ Acciones ▾</button>" +
                    "<div id=\"" + menuId + "\" class=\"hidden absolute right-0 mt-1 w-36 rounded-xl border border-white/10 bg-slate-800 shadow-xl z-50 overflow-hidden\">" +
                    "<button onclick=\"sendCmd(" + tokenId + ",'pause',0);toggleMenu(" + tokenId + ")\" class=\"w-full text-left px-4 py-2 text-xs text-amber-300 hover:bg-white/10\">⏸ Pausar</button>" +
                    "<button onclick=\"sendCmd(" + tokenId + ",'stop',0);toggleMenu(" + tokenId + ")\" class=\"w-full text-left px-4 py-2 text-xs text-red-300 hover:bg-white/10\">⏹ Detener</button>" +
                    "<button onclick=\"sendCmd(" + tokenId + ",'kick',0);toggleMenu(" + tokenId + ")\" class=\"w-full text-left px-4 py-2 text-xs text-red-200 hover:bg-white/10\">🚫 Kickear</button>" +
                    "<button onclick=\"sendCmd(" + tokenId + ",'message',1);toggleMenu(" + tokenId + ")\" class=\"w-full text-left px-4 py-2 text-xs text-cyan-300 hover:bg-white/10\">💬 Mensaje</button>" +
                    "</div></div>";
            }

            window.toggleMenu = function(tokenId) {
                var menu = document.getElementById("menu-" + tokenId);
                if (!menu) return;
                var isHidden = menu.classList.contains("hidden");
                document.querySelectorAll("[id^=menu-]").forEach(function(m) { m.classList.add("hidden"); });
                if (isHidden) menu.classList.remove("hidden");
                setTimeout(function() {
                    document.addEventListener("click", function handler(e) {
                        if (!e.target.closest("[id^=menu-]") && !e.target.closest("button")) {
                            document.querySelectorAll("[id^=menu-]").forEach(function(m) { m.classList.add("hidden"); });
                        }
                        document.removeEventListener("click", handler);
                    });
                }, 10);
            };

            window.sendCmd = function(tokenId, cmd, needsMsg) {
                var msg = null;
                if (needsMsg) {
                    msg = prompt('Escribe el mensaje para el cliente:');
                    if (!msg) return;
                }
                var csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                fetch('/admin/sessions/' + tokenId + '/command', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ command: cmd, message: msg })
                }).then(function(r) { return r.json(); }).then(function(d) {
                    if (d.ok) {
                        var labels = { pause: '⏸ Pausar', stop: '⏹ Detener', kick: '🚫 Kickear', message: '💬 Mensaje' };
                        var n = document.createElement('div');
                        n.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#1e293b;color:#67e8f9;border:1px solid #22d3ee;border-radius:10px;padding:10px 18px;font-size:13px;z-index:9999;';
                        n.textContent = 'Comando enviado: ' + (labels[cmd] || cmd) + ' (llega en max 15s)';
                        document.body.appendChild(n);
                        setTimeout(function() { n.remove(); }, 4000);
                    }
                }).catch(function() {
                    alert('Error al enviar comando.');
                });
            };

            function escapeHtml(s) {
                const d = document.createElement('div');
                d.textContent = s == null ? '' : String(s);
                return d.innerHTML;
            }

            function escapeAttr(s) {
                return escapeHtml(s).replace(/"/g, '&quot;');
            }

            async function loadSessions() {
                try {
                    const res = await fetch(dataUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                    const data = await res.json();
                    document.getElementById('total-count').textContent = data.total;
                    document.getElementById('updated-at').textContent = data.updated_at;
                    if (data.stats) {
                        document.getElementById('stat-total').textContent = data.stats.total_active;
                        document.getElementById('stat-live').textContent = data.stats.watching_live;
                        document.getElementById('stat-vod').textContent = data.stats.watching_vod;
                    }
                    renderTable(data.sessions || []);
                } catch (e) {
                    document.getElementById('updated-at').textContent = 'Error';
                }
            }

            document.getElementById('btn-refresh').addEventListener('click', loadSessions);
            setInterval(loadSessions, 10000);
            loadSessions();
        })();
    </script>
</x-panel-layout>
