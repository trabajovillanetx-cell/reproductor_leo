<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Origen de la biblioteca local (RaiDrive vs rclone mount)
    |--------------------------------------------------------------------------
    |
    | auto   → Windows: RAIDRIVE_* si hay rutas; si no, RCLONE_MOUNT_*.
    |          Linux: RCLONE_MOUNT_* primero; si no, RAIDRIVE_* (compatibilidad).
    | raidrive → Solo RAIDRIVE_LOCAL_PATH / RAIDRIVE_LOCAL_PATHS (típico Windows).
    | rclone → Solo RCLONE_MOUNT_PATH / RCLONE_MOUNT_PATHS (típico VPS).
    |
    */
    'local_library_driver' => strtolower((string) env('LOCAL_LIBRARY_DRIVER', 'auto')),

    /*
    |--------------------------------------------------------------------------
    | RaiDrive / unidad local de medios (Windows)
    |--------------------------------------------------------------------------
    |
    | RaiDrive monta tu nube como carpeta o letra de unidad en Windows (ej. R:\).
    | Pon aquí la ruta ABSOLUTA a la carpeta donde están los vídeos. PHP (Apache)
    | debe poder leer esa ruta (mismo PC que Laragon; a veces hay que dar permiso
    | al servicio de Apache a la unidad montada).
    |
    | En "stream_url" del contenido usa el prefijo local: + ruta, por ejemplo:
    | local:R:\Peliculas\demo.mp4
    |
    | Varias unidades (hasta ~20 letras): definí RAIDRIVE_LOCAL_PATHS como lista
    | separada por comas (ej. "R:\,S:\,T:\"). Si RAIDRIVE_LOCAL_PATHS no está
    | vacío, se ignora RAIDRIVE_LOCAL_PATH.
    |
    */
    'raidrive_local_root' => env('RAIDRIVE_LOCAL_PATH', ''),

    /*
    |--------------------------------------------------------------------------
    | rclone mount en disco (VPS / Linux)
    |--------------------------------------------------------------------------
    |
    | Tras `rclone mount remoto:carpeta /var/www/cloud-media`, apuntá esa carpeta
    | con RCLONE_MOUNT_PATH o RCLONE_MOUNT_PATHS (misma semántica que RAIDRIVE_*).
    | El binario RCLONE_PATH se usa para `php artisan media:rclone-check`.
    |
    */
    'rclone_mount_root' => env('RCLONE_MOUNT_PATH', ''),

    'rclone_binary' => env('RCLONE_PATH', 'rclone'),

    /*
    |--------------------------------------------------------------------------
    | Caché de listados (RaiDrive / nube montada)
    |--------------------------------------------------------------------------
    |
    | Similar en idea al caché de metadatos de rclone: evita repetir scandir y
    | recorridos pesados en cada clic. 0 = desactivar esa caché.
    |
    */
    'raidrive_browse_cache_ttl' => (int) env('RAIDRIVE_BROWSE_CACHE_TTL', 120),

    'raidrive_disk_stats_cache_ttl' => (int) env('RAIDRIVE_DISK_STATS_CACHE_TTL', 300),

    /** Máximo de archivos por una importación recursiva desde el panel. */
    'raidrive_import_recursive_max' => (int) env('RAIDRIVE_IMPORT_RECURSIVE_MAX', 2500),

    /**
     * Carpetas cuyo nombre de segmento (cualquier nivel de la ruta) no se catalogan como película/serie
     * en importación recursiva ni en el recuento opcional de vídeos en disco. Evita inflar el catálogo con
     * extras, entrevistas, fragmentos HLS, subtítulos embebidos como mkv en /subs/, etc.
     *
     * @var list<string>
     */
    'raidrive_import_skip_path_segments' => array_values(array_unique(array_merge(
        [
            'extras', 'featurettes', 'interviews', 'scenes', 'deleted scenes',
            'behind the scenes', 'trailers', 'trailer', 'shorts', 'samples', 'sample',
            'subs', 'subtitles', 'segment', 'segments', 'hls',
        ],
        array_values(array_filter(array_map(
            static fn (string $s): string => strtolower(trim($s)),
            preg_split('/\s*,\s*/', (string) env('RAIDRIVE_IMPORT_EXTRA_SKIP_SEGMENTS', ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
        )))
    ))),

    /**
     * Si el nombre del archivo (sin extensión) empieza por alguno de estos prefijos, no se importa.
     *
     * @var list<string>
     */
    'raidrive_import_skip_filename_prefixes' => [
        'sample', 'trailer',
    ],

    /**
     * Ignorar vídeos bajo …/BDMV/STREAM/ (discos Blu-ray suelen tener decenas de .m2ts por una sola película).
     * Desactivar con RAIDRIVE_IMPORT_SKIP_BDMV_STREAM=false si solo tenés copias en bruto BDMV sin mkv.
     */
    'raidrive_import_skip_bdmv_stream' => filter_var(env('RAIDRIVE_IMPORT_SKIP_BDMV_STREAM', true), FILTER_VALIDATE_BOOL),

    /**
     * En importación recursiva, si TMDB_AUTO_POSTER_ON_IMPORT=true, por defecto NO se llama TMDB por cada
     * archivo (miles de peticiones HTTP = pantalla “cargando” eterna). true = sí enriquecer en recursivo.
     */
    'raidrive_import_recursive_enrich_tmdb' => filter_var(env('RAIDRIVE_IMPORT_RECURSIVE_ENRICH_TMDB', false), FILTER_VALIDATE_BOOL),

    /*
    | Recuento recursivo de vídeos en disco al abrir Biblioteca local. En RaiDrive
    | o nubes montadas puede tardar minutos o colgar PHP; por defecto está apagado.
    */
    'raidrive_index_disk_stats' => filter_var(env('RAIDRIVE_INDEX_DISK_STATS', false), FILTER_VALIDATE_BOOL),

];
