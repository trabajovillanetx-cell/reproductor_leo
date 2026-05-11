<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class LocalMediaService
{
    public const LOCAL_PREFIX = 'local:';

    /** Subcarpeta única bajo storage/app/public para vídeos subidos por el panel (no por mes). */
    public const UPLOADS_PUBLIC_SUBDIR = 'imports/uploads';

    private const DISK_STATS_MAX_FILES = 8000;

    private const DISK_STATS_MAX_SECONDS = 6.0;

    /** @var array{0: list<string>, 1: 'raidrive'|'rclone'|'none'}|null */
    private ?array $resolvedRootsCache = null;

    /**
     * Opción de .env: auto | raidrive | rclone.
     */
    public function localLibraryDriverOption(): string
    {
        $d = strtolower(trim((string) config('media.local_library_driver', 'auto')));

        return in_array($d, ['auto', 'raidrive', 'rclone'], true) ? $d : 'auto';
    }

    /**
     * Qué bloque de variables aportó las raíces activas (tras resolver "auto").
     */
    public function localLibraryRootsBackend(): string
    {
        return $this->resolvedRoots()[1];
    }

    /**
     * Rutas absolutas permitidas (RaiDrive en Windows o carpeta de `rclone mount` en VPS).
     *
     * @return list<string>
     */
    public function roots(): array
    {
        return $this->resolvedRoots()[0];
    }

    /**
     * @return array{0: list<string>, 1: 'raidrive'|'rclone'|'none'}
     */
    private function resolvedRoots(): array
    {
        if ($this->resolvedRootsCache !== null) {
            return $this->resolvedRootsCache;
        }

        $driver = $this->localLibraryDriverOption();
        if ($driver === 'raidrive') {
            $roots = $this->rootsFromPathsEnv('RAIDRIVE_LOCAL_PATHS', 'RAIDRIVE_LOCAL_PATH');
            $this->resolvedRootsCache = [$roots, $roots !== [] ? 'raidrive' : 'none'];

            return $this->resolvedRootsCache;
        }
        if ($driver === 'rclone') {
            $roots = $this->rootsFromPathsEnv('RCLONE_MOUNT_PATHS', 'RCLONE_MOUNT_PATH');
            $this->resolvedRootsCache = [$roots, $roots !== [] ? 'rclone' : 'none'];

            return $this->resolvedRootsCache;
        }

        // auto
        if (PHP_OS_FAMILY === 'Windows') {
            $raid = $this->rootsFromPathsEnv('RAIDRIVE_LOCAL_PATHS', 'RAIDRIVE_LOCAL_PATH');
            if ($raid !== []) {
                $this->resolvedRootsCache = [$raid, 'raidrive'];

                return $this->resolvedRootsCache;
            }
            $rc = $this->rootsFromPathsEnv('RCLONE_MOUNT_PATHS', 'RCLONE_MOUNT_PATH');
            $this->resolvedRootsCache = [$rc, $rc !== [] ? 'rclone' : 'none'];

            return $this->resolvedRootsCache;
        }

        $rc = $this->rootsFromPathsEnv('RCLONE_MOUNT_PATHS', 'RCLONE_MOUNT_PATH');
        if ($rc !== []) {
            $this->resolvedRootsCache = [$rc, 'rclone'];

            return $this->resolvedRootsCache;
        }
        $raid = $this->rootsFromPathsEnv('RAIDRIVE_LOCAL_PATHS', 'RAIDRIVE_LOCAL_PATH');
        $this->resolvedRootsCache = [$raid, $raid !== [] ? 'raidrive' : 'none'];

        return $this->resolvedRootsCache;
    }

    /**
     * @return list<string>
     */
    private function rootsFromPathsEnv(string $multiKey, string $singleKey): array
    {
        $multi = env($multiKey);
        $parts = [];
        if (is_string($multi) && trim($multi) !== '') {
            $parts = preg_split('/\s*,\s*/', trim($multi), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if ($parts === []) {
            $one = trim(str_replace('/', DIRECTORY_SEPARATOR, (string) env($singleKey, '')));
            if ($one !== '') {
                $parts = [$one];
            }
        }

        $out = [];
        foreach ($parts as $p) {
            $n = rtrim(str_replace('/', DIRECTORY_SEPARATOR, trim($p)), DIRECTORY_SEPARATOR);
            if ($n !== '') {
                $out[] = $n;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Primera raíz (compatibilidad con código que esperaba una sola).
     */
    public function root(): string
    {
        return $this->roots()[0] ?? '';
    }

    public function isConfigured(): bool
    {
        return $this->roots() !== [];
    }

    public function isLocalStream(string $streamUrl): bool
    {
        return str_starts_with(trim($streamUrl), self::LOCAL_PREFIX);
    }

    /**
     * Ruta absoluta del archivo o null si no es local / inválida.
     */
    public function absolutePathFromStreamUrl(string $streamUrl): ?string
    {
        if (! $this->isLocalStream($streamUrl)) {
            return null;
        }

        $path = trim(substr(trim($streamUrl), strlen(self::LOCAL_PREFIX)));
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

        if ($path === '') {
            return null;
        }

        return $path;
    }

    public function isAllowedReadableFile(string $absolutePath): bool
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return false;
        }

        $fileReal = @realpath($absolutePath);
        if ($fileReal === false) {
            return false;
        }

        $fileReal = str_replace('/', DIRECTORY_SEPARATOR, $fileReal);

        $publicRoot = @realpath(storage_path('app/public'));
        if ($publicRoot !== false) {
            $publicRoot = str_replace('/', DIRECTORY_SEPARATOR, $publicRoot);
            if (str_starts_with(strtolower($fileReal), strtolower($publicRoot))) {
                return true;
            }
        }

        if (! $this->isConfigured()) {
            return false;
        }

        foreach ($this->roots() as $root) {
            $rootReal = @realpath($root);
            if ($rootReal === false) {
                continue;
            }
            $rootReal = str_replace('/', DIRECTORY_SEPARATOR, $rootReal);
            if (str_starts_with(strtolower($fileReal), strtolower($rootReal))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extensiones de vídeo admitidas en importación y biblioteca local.
     *
     * @return list<string>
     */
    public static function videoExtensions(): array
    {
        return ['mp4', 'mkv', 'webm', 'avi', 'mov', 'm4v', 'mpeg', 'mpg', 'ts', 'flv', 'wmv', 'ogv', '3gp', 'm2ts', 'm3u8'];
    }

    /**
     * Rutas que no deben catalogarse como título principal (extras, fragmentos Blu-ray, muestras, etc.).
     * Usado en importación recursiva, importación por archivos sueltos y recuento en disco.
     */
    public function shouldSkipSupplementaryVideoPath(string $absolutePathname): bool
    {
        $path = str_replace('\\', '/', $absolutePathname);
        $lower = strtolower($path);

        if ((bool) config('media.raidrive_import_skip_bdmv_stream', true)) {
            if (str_contains($lower, '/bdmv/stream/')) {
                return true;
            }
        }

        $dir = strtolower(str_replace('\\', '/', dirname($path)));
        $segments = array_values(array_filter(explode('/', $dir), static fn (string $s): bool => $s !== ''));

        /** @var list<string> $blocked */
        $blocked = config('media.raidrive_import_skip_path_segments', []);
        foreach ($blocked as $token) {
            $t = strtolower(trim((string) $token));
            if ($t === '') {
                continue;
            }
            foreach ($segments as $seg) {
                if ($seg === $t) {
                    return true;
                }
            }
        }

        $stem = strtolower((string) pathinfo($path, PATHINFO_FILENAME));
        /** @var list<string> $prefixes */
        $prefixes = config('media.raidrive_import_skip_filename_prefixes', []);
        foreach ($prefixes as $prefix) {
            $p = strtolower(trim((string) $prefix));
            if ($p !== '' && str_starts_with($stem, $p)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vídeos ubicados directamente dentro de esta carpeta (sin bajar subdirectorios).
     *
     * @return list<string> rutas absolutas ordenadas
     */
    public function listImmediateVideoFilesInDirectory(string $absoluteDirectory): array
    {
        $dirReal = @realpath($absoluteDirectory);
        if ($dirReal === false || ! is_dir($dirReal)) {
            return [];
        }

        $dirRealNorm = str_replace('/', DIRECTORY_SEPARATOR, $dirReal);
        $videoExt = self::videoExtensions();
        $out = [];

        foreach (scandir($dirRealNorm) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $full = $dirRealNorm.DIRECTORY_SEPARATOR.$name;
            if (! is_file($full) || ! $this->isAllowedReadableFile($full)) {
                continue;
            }
            $e = strtolower(pathinfo($full, PATHINFO_EXTENSION));
            if (! in_array($e, $videoExt, true)) {
                continue;
            }
            $out[] = str_replace('/', DIRECTORY_SEPARATOR, $full);
        }

        sort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }

    /**
     * Lista carpetas y vídeos solo en el nivel actual (navegación tipo explorador).
     * Con varias raíces, la URL path vacía muestra una carpeta por unidad (#0, #1, …).
     *
     * @return array{dirs: list<array{name: string, path: string}>, files: list<array{name: string, absolute: string}>}
     */
    public function browse(string $relativeSubPath = ''): array
    {
        if (! $this->isConfigured()) {
            return ['dirs' => [], 'files' => []];
        }

        if (! $this->isSafeRelativePath($relativeSubPath)) {
            return ['dirs' => [], 'files' => []];
        }

        $ttl = max(0, (int) config('media.raidrive_browse_cache_ttl', 120));
        if ($ttl === 0) {
            return $this->browseFilesystem($relativeSubPath);
        }

        $epoch = (int) Cache::get('raidrive.cache_epoch', 0);
        $rootFinger = $this->rootsFingerprint();
        $normSub = strtolower(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($relativeSubPath, DIRECTORY_SEPARATOR)));
        $pathFinger = hash('sha256', $normSub);

        return Cache::remember(
            "raidrive:browse:{$epoch}:{$rootFinger}:{$pathFinger}",
            now()->addSeconds($ttl),
            fn () => $this->browseFilesystem($relativeSubPath)
        );
    }

    /**
     * Invalida listados y estadísticas cacheadas (p. ej. tras copiar archivos nuevos al disco).
     */
    public function bumpRaidriveCacheEpoch(): void
    {
        $v = (int) Cache::get('raidrive.cache_epoch', 0);
        Cache::put('raidrive.cache_epoch', $v + 1, now()->addYears(5));
    }

    public function isSafeRelativePath(string $relativeSubPath): bool
    {
        if (trim($relativeSubPath) === '') {
            return true;
        }

        $norm = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeSubPath), DIRECTORY_SEPARATOR);
        if ($norm === '') {
            return true;
        }

        $parts = explode(DIRECTORY_SEPARATOR, $norm);
        $rootCount = count($this->roots());

        foreach ($parts as $i => $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return false;
            }
            if ($i === 0 && $this->isMultiRoot()) {
                if (! ctype_digit($part)) {
                    return false;
                }
                $idx = (int) $part;
                if ($idx < 0 || $idx >= $rootCount) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function listVideoFiles(string $relativeSubPath = '', int $limit = 300): array
    {
        $roots = $this->roots();
        if ($roots === [] || ! $this->isSafeRelativePath($relativeSubPath)) {
            return [];
        }

        $base = $roots[0];

        return $this->listVideoFilesUnderBase($base, $relativeSubPath, $limit);
    }

    /**
     * @return list<string>
     */
    private function listVideoFilesUnderBase(string $base, string $relativeSubPath, int $limit): array
    {
        $sub = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeSubPath), DIRECTORY_SEPARATOR);
        $dir = $sub === '' ? $base : $base.DIRECTORY_SEPARATOR.$sub;

        if (! is_dir($dir) || ! is_readable($dir)) {
            return [];
        }

        $dirReal = realpath($dir);
        $rootReal = realpath($base);
        if ($dirReal === false || $rootReal === false) {
            return [];
        }

        if (! str_starts_with(strtolower($dirReal), strtolower($rootReal))) {
            return [];
        }

        $ext = self::videoExtensions();
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirReal, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (count($out) >= $limit) {
                break;
            }
            if (! $file->isFile()) {
                continue;
            }
            $e = strtolower($file->getExtension());
            if (! in_array($e, $ext, true)) {
                continue;
            }
            $pathname = str_replace('/', DIRECTORY_SEPARATOR, $file->getPathname());
            if ($this->shouldSkipSupplementaryVideoPath($pathname)) {
                continue;
            }
            $out[] = $pathname;
        }

        sort($out);

        return $out;
    }

    public function streamUrlForPath(string $absoluteFilePath): string
    {
        return self::LOCAL_PREFIX.$absoluteFilePath;
    }

    /**
     * Ruta real del directorio asociado al path del panel (raíz de unidad, subcarpeta, o "0/Pelis" en multi-raíz).
     */
    public function resolveDirectoryRealPathFromBrowsePath(string $browsePath): ?string
    {
        if (! $this->isConfigured() || ! $this->isSafeRelativePath($browsePath)) {
            return null;
        }

        if ($this->isMultiRoot()) {
            $norm = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $browsePath), DIRECTORY_SEPARATOR);
            if ($norm === '') {
                return null;
            }
            $parts = explode(DIRECTORY_SEPARATOR, $norm);
            $idx = (int) $parts[0];
            $roots = $this->roots();
            if (! isset($roots[$idx])) {
                return null;
            }
            $base = $roots[$idx];
            $rest = array_slice($parts, 1);
            $sub = implode(DIRECTORY_SEPARATOR, $rest);
        } else {
            $base = $this->roots()[0];
            $sub = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $browsePath), DIRECTORY_SEPARATOR);
        }

        $dir = $sub === '' ? $base : $base.DIRECTORY_SEPARATOR.$sub;
        $dirReal = @realpath($dir);
        $rootReal = @realpath($base);
        if ($dirReal === false || $rootReal === false || ! is_dir($dirReal)) {
            return null;
        }

        $dirReal = str_replace('/', DIRECTORY_SEPARATOR, $dirReal);
        $rootReal = str_replace('/', DIRECTORY_SEPARATOR, $rootReal);

        if (! str_starts_with(strtolower($dirReal), strtolower($rootReal))) {
            return null;
        }

        return $dirReal;
    }

    /**
     * Carpeta lógica para el catálogo (ruta relativa a la raíz de la unidad; en multi-raíz con prefijo "0/…").
     */
    public function libraryFolderForAbsoluteFile(string $absoluteFile): ?string
    {
        $path = str_replace('/', DIRECTORY_SEPARATOR, $absoluteFile);
        $fileReal = @realpath($path);
        if ($fileReal === false || ! is_file($fileReal)) {
            return null;
        }

        $fileReal = str_replace('/', DIRECTORY_SEPARATOR, $fileReal);

        foreach ($this->roots() as $idx => $root) {
            $rootReal = @realpath($root);
            if ($rootReal === false) {
                continue;
            }
            $rootReal = str_replace('/', DIRECTORY_SEPARATOR, $rootReal);
            $rootWithSep = rtrim($rootReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            $fileLower = strtolower($fileReal);
            $rootLower = strtolower($rootReal);
            $rootPrefLower = strtolower($rootWithSep);

            if ($fileLower === $rootLower) {
                continue;
            }
            if (! str_starts_with($fileLower, $rootPrefLower)) {
                continue;
            }

            $rel = substr($fileReal, strlen($rootWithSep));
            $rel = str_replace('\\', '/', $rel);
            $dir = dirname($rel);
            if ($dir === '.' || $dir === '') {
                return null;
            }
            $folder = str_replace('\\', '/', $dir);

            return $this->isMultiRoot() ? ((string) $idx).'/'.$folder : $folder;
        }

        return null;
    }

    /**
     * Lista vídeos bajo el directorio del path del panel (recursivo).
     *
     * @return list<array{absolute: string, library_folder: ?string, title: string}>
     */
    public function listVideosRecursiveForBrowsePath(string $browsePath, int $maxFiles = 2000): array
    {
        $ctx = $this->browseListRootContext($browsePath);
        if ($ctx === null) {
            return [];
        }

        $dirReal = $ctx['dirReal'];
        $rootReal = $ctx['rootReal'];
        $multiIndex = $ctx['multiIndex'];

        $maxFiles = max(1, min(10000, $maxFiles));
        $ext = self::videoExtensions();
        $out = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dirReal, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if (count($out) >= $maxFiles) {
                    break;
                }
                if (! $file->isFile()) {
                    continue;
                }
                $pathname = str_replace('/', DIRECTORY_SEPARATOR, $file->getPathname());
                if (! $this->isReadableFileUnderTrustedRoot($rootReal, $pathname)) {
                    continue;
                }
                $e = strtolower($file->getExtension());
                if (! in_array($e, $ext, true)) {
                    continue;
                }
                if ($this->shouldSkipSupplementaryVideoPath($pathname)) {
                    continue;
                }
                $title = pathinfo($pathname, PATHINFO_FILENAME);
                if ($title === '') {
                    $title = 'Sin título';
                }
                $out[] = [
                    'absolute' => $pathname,
                    'library_folder' => $this->libraryFolderFromKnownRoot($rootReal, $multiIndex, $pathname),
                    'title' => $title,
                ];
            }
        } catch (\Throwable) {
            return $out;
        }

        return $out;
    }

    /**
     * Contexto de raíz RaiDrive para listados/importación sin un {@see realpath()} por archivo (RaiDrive/red).
     *
     * @return array{dirReal: string, rootReal: string, multiIndex: int|null}|null
     */
    public function browseListRootContext(string $browsePath): ?array
    {
        if (! $this->isConfigured() || ! $this->isSafeRelativePath($browsePath)) {
            return null;
        }

        $dirReal = $this->resolveDirectoryRealPathFromBrowsePath($browsePath);
        if ($dirReal === null) {
            return null;
        }

        $dirReal = str_replace('/', DIRECTORY_SEPARATOR, $dirReal);

        if ($this->isMultiRoot()) {
            $norm = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $browsePath), DIRECTORY_SEPARATOR);
            $parts = explode(DIRECTORY_SEPARATOR, $norm);
            $idx = (int) $parts[0];
            $roots = $this->roots();
            if (! isset($roots[$idx])) {
                return null;
            }
            $rootReal = @realpath($roots[$idx]);
            if ($rootReal === false) {
                return null;
            }

            return [
                'dirReal' => $dirReal,
                'rootReal' => str_replace('/', DIRECTORY_SEPARATOR, $rootReal),
                'multiIndex' => $idx,
            ];
        }

        $rootReal = @realpath($this->roots()[0]);
        if ($rootReal === false) {
            return null;
        }

        return [
            'dirReal' => $dirReal,
            'rootReal' => str_replace('/', DIRECTORY_SEPARATOR, $rootReal),
            'multiIndex' => null,
        ];
    }

    /**
     * Igual que {@see libraryFolderForAbsoluteFile} pero sin {@see realpath()} por archivo (solo prefijo de raíz).
     */
    public function libraryFolderFromKnownRoot(string $rootReal, ?int $multiIndex, string $absolutePathname): ?string
    {
        $rootWithSep = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $rootReal), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $file = str_replace('/', DIRECTORY_SEPARATOR, $absolutePathname);
        $fileLower = strtolower($file);
        $rootPrefLower = strtolower($rootWithSep);
        $rootBareLower = strtolower(rtrim($rootWithSep, DIRECTORY_SEPARATOR));

        if ($fileLower === $rootBareLower) {
            return null;
        }
        if (! str_starts_with($fileLower, $rootPrefLower)) {
            return null;
        }

        $rel = substr($file, strlen($rootWithSep));
        $rel = str_replace('\\', '/', $rel);
        $dir = dirname($rel);
        if ($dir === '.' || $dir === '') {
            return null;
        }
        $folder = str_replace('\\', '/', $dir);

        return $multiIndex !== null ? ((string) $multiIndex).'/'.$folder : $folder;
    }

    public function isReadableFileUnderTrustedRoot(string $rootReal, string $absolutePathname): bool
    {
        $rootWithSep = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $rootReal), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $path = str_replace('/', DIRECTORY_SEPARATOR, $absolutePathname);
        if (! str_starts_with(strtolower($path), strtolower($rootWithSep))) {
            return false;
        }

        return is_file($path) && is_readable($path);
    }

    /**
     * Cuenta vídeos accesibles bajo todas las raíces (recursivo, con topes globales).
     *
     * @return array{count: int, capped: bool, timed_out: bool}
     */
    public function countVideoFilesUnderConfiguredRoot(): array
    {
        if (! $this->isConfigured()) {
            return ['count' => 0, 'capped' => false, 'timed_out' => false];
        }

        $ttl = max(0, (int) config('media.raidrive_disk_stats_cache_ttl', 300));
        if ($ttl === 0) {
            return $this->countVideoFilesUnderConfiguredRootFilesystem();
        }

        $epoch = (int) Cache::get('raidrive.cache_epoch', 0);
        $rootFinger = $this->rootsFingerprint();

        return Cache::remember(
            "raidrive:stats:{$epoch}:{$rootFinger}",
            now()->addSeconds($ttl),
            fn () => $this->countVideoFilesUnderConfiguredRootFilesystem()
        );
    }

    public function isMultiRoot(): bool
    {
        return count($this->roots()) > 1;
    }

    private function rootsFingerprint(): string
    {
        if ($this->roots() === []) {
            return '';
        }

        $norm = array_map(
            fn (string $r): string => strtolower(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $r)),
            $this->roots()
        );
        sort($norm);

        return hash('sha256', implode('|', $norm));
    }

    private function rootDisplayLabel(string $absoluteRoot): string
    {
        return rtrim(str_replace('/', DIRECTORY_SEPARATOR, $absoluteRoot), DIRECTORY_SEPARATOR);
    }

    /**
     * @return array{dirs: list<array{name: string, path: string}>, files: list<array{name: string, absolute: string}>}
     */
    private function browseFilesystem(string $relativeSubPath): array
    {
        if (! $this->isConfigured() || ! $this->isSafeRelativePath($relativeSubPath)) {
            return ['dirs' => [], 'files' => []];
        }

        if ($this->isMultiRoot()) {
            return $this->browseFilesystemMulti($relativeSubPath);
        }

        $sub = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeSubPath), DIRECTORY_SEPARATOR);

        return $this->browseUnderPhysicalRoot($this->roots()[0], $sub, null);
    }

    /**
     * @return array{dirs: list<array{name: string, path: string}>, files: list<array{name: string, absolute: string}>}
     */
    private function browseFilesystemMulti(string $relativeSubPath): array
    {
        $norm = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeSubPath), DIRECTORY_SEPARATOR);
        if ($norm === '') {
            return $this->syntheticDriveList();
        }

        $parts = explode(DIRECTORY_SEPARATOR, $norm);
        $idx = (int) $parts[0];
        $roots = $this->roots();
        if (! isset($roots[$idx])) {
            return ['dirs' => [], 'files' => []];
        }

        $rest = array_slice($parts, 1);
        $sub = implode(DIRECTORY_SEPARATOR, $rest);

        return $this->browseUnderPhysicalRoot($roots[$idx], $sub, $idx);
    }

    /**
     * @return array{dirs: list<array{name: string, path: string}>, files: list<array{name: string, absolute: string}>}
     */
    private function syntheticDriveList(): array
    {
        $dirs = [];
        foreach ($this->roots() as $i => $base) {
            $dirs[] = [
                'name' => $this->rootDisplayLabel($base).' (#'.(string) $i.')',
                'path' => (string) $i,
            ];
        }
        usort($dirs, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return ['dirs' => $dirs, 'files' => []];
    }

    /**
     * @param  int|null  $pathPrefixIndex  Si no es null, antepone "idx/" a cada path de carpeta (navegación multi-raíz).
     * @return array{dirs: list<array{name: string, path: string}>, files: list<array{name: string, absolute: string}>}
     */
    private function browseUnderPhysicalRoot(string $physicalRoot, string $subRelative, ?int $pathPrefixIndex): array
    {
        $dirs = [];
        $files = [];

        $base = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $physicalRoot), DIRECTORY_SEPARATOR);
        $sub = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $subRelative), DIRECTORY_SEPARATOR);
        $dir = $sub === '' ? $base : $base.DIRECTORY_SEPARATOR.$sub;

        if (! is_dir($dir) || ! is_readable($dir)) {
            return ['dirs' => $dirs, 'files' => $files];
        }

        $dirReal = realpath($dir);
        $rootReal = realpath($base);
        if ($dirReal === false || $rootReal === false) {
            return ['dirs' => $dirs, 'files' => $files];
        }

        if (! str_starts_with(strtolower($dirReal), strtolower($rootReal))) {
            return ['dirs' => $dirs, 'files' => $files];
        }

        $videoExt = self::videoExtensions();

        foreach (scandir($dirReal) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $full = $dirReal.DIRECTORY_SEPARATOR.$name;
            if (is_dir($full)) {
                $rel = $sub === '' ? $name : $sub.DIRECTORY_SEPARATOR.$name;
                $urlPath = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
                if ($pathPrefixIndex !== null) {
                    $urlPath = $pathPrefixIndex.'/'.$urlPath;
                }
                $dirs[] = ['name' => $name, 'path' => $urlPath];
            } elseif (is_file($full) && is_readable($full)) {
                $e = strtolower(pathinfo($full, PATHINFO_EXTENSION));
                if (in_array($e, $videoExt, true)) {
                    $files[] = ['name' => $name, 'absolute' => $full];
                }
            }
        }

        usort($dirs, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($files, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return ['dirs' => $dirs, 'files' => $files];
    }

    /**
     * @return array{count: int, capped: bool, timed_out: bool}
     */
    private function countVideoFilesUnderConfiguredRootFilesystem(): array
    {
        if (! $this->isConfigured()) {
            return ['count' => 0, 'capped' => false, 'timed_out' => false];
        }

        $count = 0;
        $capped = false;
        $timedOut = false;
        $start = microtime(true);
        $ext = self::videoExtensions();

        foreach ($this->roots() as $base) {
            $dirReal = @realpath($base);
            if ($dirReal === false || ! is_dir($dirReal)) {
                continue;
            }

            $dirReal = str_replace('/', DIRECTORY_SEPARATOR, $dirReal);

            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $dirReal,
                        \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME
                    ),
                    \RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $pathname) {
                    if ($count >= self::DISK_STATS_MAX_FILES) {
                        $capped = true;
                        break 2;
                    }
                    if ((microtime(true) - $start) >= self::DISK_STATS_MAX_SECONDS) {
                        $timedOut = true;
                        break 2;
                    }
                    if (! is_string($pathname) || ! is_file($pathname) || ! is_readable($pathname)) {
                        continue;
                    }
                    $e = strtolower(pathinfo($pathname, PATHINFO_EXTENSION));
                    if (! in_array($e, $ext, true)) {
                        continue;
                    }
                    $pathnameNorm = str_replace('/', DIRECTORY_SEPARATOR, $pathname);
                    if ($this->shouldSkipSupplementaryVideoPath($pathnameNorm)) {
                        continue;
                    }
                    $count++;
                }
            } catch (\Throwable) {
                return ['count' => $count, 'capped' => $capped, 'timed_out' => $timedOut];
            }
        }

        return ['count' => $count, 'capped' => $capped, 'timed_out' => $timedOut];
    }
}
