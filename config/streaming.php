<?php

return [
    /** Reescribe HLS remoto por Laravel (misma origen que el reproductor): evita errores VHS por CORS/mixed-content. */
    'hls_browser_proxy_enabled' => filter_var(env('HLS_BROWSER_PROXY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Por defecto se permiten playlists/segmentos en otros dominios públicos respecto del stream principal (CDN, keys AES).
     * true = mismo esquema+host+puesto que stream_url únicamente (más cerrado).
     *
     * @see \App\Services\RemoteHlsBrowserProxy Relay sigue obligando HTTPS/HTTP público y bloqueando redes privadas (SSRF básico).
     */
    'hls_proxy_same_origin_only' => filter_var(env('HLS_PROXY_SAME_ORIGIN_ONLY', false), FILTER_VALIDATE_BOOLEAN),

    /** User-Agent al descargar playlists/segmentos desde el panel IPTV (algunos bloquean clientes “rare”). */
    'hls_upstream_user_agent' => env('HLS_UPSTREAM_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36'),

    'hls_upstream_connect_timeout' => (int) env('HLS_UPSTREAM_CONNECT_TIMEOUT', 20),
    'hls_upstream_timeout' => (int) env('HLS_UPSTREAM_TIMEOUT', 120),

    /** Transcodificación FFmpeg (audio → AAC) para navegadores que no reproducen AC3/DTS. */
    'ffmpeg_enabled' => filter_var(env('FFMPEG_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'ffmpeg_bin' => env('FFMPEG_BIN', 'ffmpeg'),
    'ffmpeg_max_concurrent' => (int) env('FFMPEG_MAX_CONCURRENT', 3),

    /** false = útil en local si el origen usa certificados rotos (no uses en producción pública). */
    'hls_upstream_verify_ssl' => filter_var(env('HLS_UPSTREAM_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Referer/Origin hacia el IPTV: catalog = carpeta del stream_url del contenido (habitual anti-leech).
     * request = misma lógica pero según cada URL pedida (CDN). none = no enviar.
     * Si HLS_UPSTREAM_REFERER tiene valor, se usa siempre y estas políticas se ignoran.
     */
    'hls_upstream_referer_policy' => strtolower((string) env('HLS_UPSTREAM_REFERER_POLICY', 'catalog')),
    'hls_upstream_referer' => env('HLS_UPSTREAM_REFERER', ''),

    'rclone_base_url' => env('RCLONE_BASE_URL', ''),
    'rclone_auth_user' => env('RCLONE_AUTH_USER', ''),
    'rclone_auth_pass' => env('RCLONE_AUTH_PASS', ''),
    'playback_token_ttl_minutes' => (int) env('PLAYBACK_TOKEN_TTL_MINUTES', 5),

    /** Token corto sólo para vignette autoplay del carrusel inicio */
    'hero_preview_token_ttl_minutes' => (int) env('STREAMING_HERO_PREVIEW_TOKEN_TTL', 4),

    /** Segundos que se muestra cada destacado antes de pasar al siguiente (carrusel + vídeo mute). */
    'hero_preview_clip_seconds' => (int) env('STREAMING_HERO_PREVIEW_CLIP_SECONDS', 40),

    /** Imagen URL de fondo en /login (vacío = degradado por defecto). */
    'login_background_url' => env('LOGIN_BACKGROUND_URL', ''),
    /** Texto principal encima del formulario (ej. DIGITALVISION). */
    'login_brand_display' => env('STREAMING_LOGIN_BRAND', 'DIGITALVISION'),
    /** Opcional: fondo del área catálogo (perfil iniciado); vacío = degradado. */
    'app_background_url' => env('STREAMING_APP_BACKGROUND_URL', ''),

    /** Fondo pantalla elegir espacio — desde admin (tabla site_settings) o este .env. */
    'profiles_picker_background_url' => env('STREAMING_PROFILES_PICKER_BG_URL', ''),

    /**
     * Si no hay URL en admin ni en .env: imagen de fondo por defecto (sala/cine oscuro).
     * Puedes sustituirla por una imagen propia alojada en tu servidor.
     */
    'profiles_picker_default_background_url' => env(
        'STREAMING_PROFILES_PICKER_BG_DEFAULT',
        'https://images.unsplash.com/photo-1489599849927-2ee91cede0ba?auto=format&fit=crop&w=2000&q=75'
    ),

    /**
     * Si es true, el acceso sigue permitido hasta las 23:59:59 del día de vencimiento
     * (en APP_TIMEZONE). Evita que un usuario “pierda el día” solo por la hora guardada.
     */
    'subscription_expires_after_end_of_day' => filter_var(
        env('SUBSCRIPTION_EXPIRE_AFTER_END_OF_DAY', true),
        FILTER_VALIDATE_BOOLEAN
    ),
];
