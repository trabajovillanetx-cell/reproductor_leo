<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Comprobación de canales en importación M3U
    |--------------------------------------------------------------------------
    |
    | Tras cada tanda de líneas procesadas el servidor solicita cada URL por
    | HTTP (solo https/http de la lista). Los que fallen no se registrarán.
    | Ajustá concurrencia y tiempos según PHP / red; listas enormes pueden
    | tardar varios minutos.
    |
    */

    'probe_streams_default' => env('M3U_PROBE_STREAMS_DEFAULT', true),

    'probe_buffer_rows' => max(1, (int) env('M3U_PROBE_BUFFER_ROWS', 72)),

    'probe_pool_size' => max(1, min(128, (int) env('M3U_PROBE_POOL_SIZE', 32))),

    'probe_connect_seconds' => (float) env('M3U_PROBE_CONNECT_SECONDS', 5.0),

    'probe_timeout_seconds' => (float) env('M3U_PROBE_TIMEOUT_SECONDS', 18.0),

    'probe_allow_insecure_tls' => filter_var(env('M3U_PROBE_ALLOW_INSECURE_TLS', false), FILTER_VALIDATE_BOOLEAN),

    'probe_user_agent' => env(
        'M3U_PROBE_USER_AGENT',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
    ),

    /*
    |--------------------------------------------------------------------------
    | Escaneo síncrono (admin → gestión M3U)
    |--------------------------------------------------------------------------
    |
    | El escaneo recorre todas las filas http(s) en la misma petición (sin cola).
    | Listas muy grandes pueden tardar varios minutos: subí timeouts de PHP/nginx.
    | Valores por defecto pensados para catálogos de hasta ~3000 canales remotos.
    |
    */

    /** Máx. filas caídas listadas por respuesta JSON (también tope al borrar por IDs en lote). */
    'scan_max_dead_listed' => max(500, min(30000, (int) env('M3U_SCAN_MAX_DEAD_LISTED', 8000))),

    /** Cuántos ítems que respondieron OK se incluyen como muestra en el informe. */
    'scan_alive_sample_size' => max(10, min(2000, (int) env('M3U_SCAN_ALIVE_SAMPLE_SIZE', 500))),

    /*
    |--------------------------------------------------------------------------
    | Simulación en segundo plano (dry-run del job de barrido)
    |--------------------------------------------------------------------------
    */

    /** Cuántos caídos incluir en `dead_samples` cuando dry_run=true (tabla en la UI). */
    'dry_run_dead_sample_limit' => max(50, min(10000, (int) env('M3U_DRY_RUN_DEAD_SAMPLE_LIMIT', 3500))),

];
