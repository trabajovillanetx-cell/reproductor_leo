<x-panel-layout title="Diagnóstico de canal / stream">
    <p class="mb-6 max-w-3xl text-sm text-gray-600 dark:text-gray-400">Probá una URL de manifiesto HLS o stream. Se mide latencia HTTP, se detecta si parece master/media playlist y se buscan codecs en <code class="rounded bg-gray-100 px-1 text-xs dark:bg-black/40">CODECS=</code>. AC3/DTS en el cuerpo muestra aviso de compatibilidad con navegador.</p>

    <form method="POST" action="{{ route('admin.diagnostics.channels.diagnose') }}" class="admin-card mb-8 max-w-3xl space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium">URL del stream o .m3u8</label>
            <input type="url" name="url" value="{{ old('url') }}" required placeholder="https://..." class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
            @error('url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="inline-flex rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-500">Diagnosticar</button>
    </form>

    @if (isset($result) && is_array($result))
        <div class="admin-card max-w-4xl space-y-3 text-sm">
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Resultado</h2>
            <p><span class="font-medium">Alcanzable:</span> {{ ! empty($result['reachable']) ? '✅ Sí' : '❌ No' }} @if ($result['http_status'] !== null) (HTTP {{ $result['http_status'] }}) @endif</p>
            @if ($result['latency_ms'] !== null)
                <p><span class="font-medium">Latencia (TTFB aprox.):</span> {{ number_format($result['latency_ms']) }} ms</p>
            @endif
            <p><span class="font-medium">Tipo manifiesto:</span> {{ $result['manifest_kind'] ?? '—' }}</p>
            @if (! empty($result['video_codecs']))
                <p><span class="font-medium">Video (CODECS):</span> {{ implode(', ', $result['video_codecs']) }}</p>
            @endif
            @if (! empty($result['audio_codecs']))
                <p><span class="font-medium">Audio (CODECS):</span> {{ implode(', ', $result['audio_codecs']) }}</p>
            @endif
            @if (! empty($result['ac3_warning']))
                <p class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">⚠ Posible pista AC3/DTS o audio poco compatible: en el navegador puede fallar el audio hasta activar <strong>FFMPEG_ENABLED</strong> y la ruta de transcodificación.</p>
            @endif
            @if (! empty($result['dynamic_path_hint']))
                <p class="text-amber-800 dark:text-amber-200">La URL contiene un segmento numérico largo típico de playlists que expiran; si falla al rato, el proxy ya refresca desde el catálogo.</p>
            @endif
            @if (! empty($result['error']))
                <p class="text-red-600 dark:text-red-400"><span class="font-medium">Error:</span> {{ $result['error'] }}</p>
            @endif
            @if (! empty($result['snippet']))
                <details class="mt-2">
                    <summary class="cursor-pointer font-medium text-indigo-600 dark:text-indigo-400">Vista previa del cuerpo</summary>
                    <pre class="mt-2 max-h-64 overflow-auto rounded bg-gray-100 p-3 text-xs dark:bg-black/40">{{ $result['snippet'] }}</pre>
                </details>
            @endif
        </div>
    @endif
</x-panel-layout>
