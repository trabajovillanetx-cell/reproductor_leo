<?php
namespace App\Console\Commands;
use App\Models\Content;
use App\Services\TmdbPosterService;
use App\Services\JellyfinPosterService;
use App\Support\PosterTitleSanitizer;
use App\Enums\ContentType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncLocalLibraryCommand extends Command
{
    protected $signature = 'library:sync {--no-posters : Omitir búsqueda de carátulas al importar}';
    protected $description = 'Sincroniza archivos locales nuevos con la biblioteca web';

    private array $mappings = [
        ['/var/www/media/pelis1/PELICULAS/AMC+',        'Peliculas/AS/AMC+',        77, 'vod'],
        ['/var/www/media/pelis1/PELICULAS/APPLE TV',    'Peliculas/AS/APPLE TV',    77, 'vod'],
        ['/var/www/media/pelis1/PELICULAS/CINEFULL',    'Peliculas/AS/CINEFULL',    77, 'vod'],
        ['/var/www/media/pelis1/PELICULAS/CRUNCHYROLL', 'Peliculas/AS/CRUNCHYROLL', 77, 'vod'],
        ['/var/www/media/pelis1/PELICULAS/DISNEY+',     'Peliculas/AS/DISNEY+',     77, 'vod'],
        ['/var/www/media/pelis1/PELICULAS/Estrenos',    'Peliculas/AS/Estrenos',    77, 'vod'],
        ['/var/www/media/pelis1/PELICULAS/MAX',         'Peliculas/AS/MAX',         77, 'vod'],
        ['/var/www/media/pelis1/PELICULAS/MGM+',        'Peliculas/AS/MGM+',        77, 'vod'],
        ['/var/www/media/pelis1/PELICULAS/NETFLIX',     'Peliculas/AS/NETFLIX',     77, 'vod'],
        ['/var/www/media/pelis1/PELICULAS/PARAMOUNT+',  'Peliculas/AS/PARAMOUNT+',  77, 'vod'],
        ['/var/www/media/pelis1/PELICULAS/PRIME VIDEO', 'Peliculas/AS/PRIME VIDEO', 77, 'vod'],
        ['/var/www/media/pelis1/PELICULAS/Semana Santa','Peliculas/AS/Semana Santa', 77, 'vod'],
        ['/var/www/media/pelis1/PELICULAS/UNIVERSAL+',  'Peliculas/AS/UNIVERSAL+',  77, 'vod'],
        ['/var/www/media/pelis1/PELICULAS/VIX+',        'Peliculas/AS/VIX+',        77, 'vod'],
        ['/var/www/media/series1/Series/APPLE TV',      'Series/APPLE TV',          78, 'series'],
        ['/var/www/media/series1/Series/CRUNCHYROLL',   'Series/CRUNCHYROLL',       78, 'series'],
        ['/var/www/media/series1/Series/DISNEY+',       'Series/DISNEY+',           78, 'series'],
        ['/var/www/media/series1/Series/MAX',           'Series/MAX',               78, 'series'],
        ['/var/www/media/series1/Series/MGM+',          'Series/MGM+',              78, 'series'],
        ['/var/www/media/series1/Series/NETFLIXX',      'Series/NETFLIXX',          78, 'series'],
        ['/var/www/media/series1/Series/PARAMOUNT',     'Series/PARAMOUNT',         78, 'series'],
        ['/var/www/media/series1/Series/PRIME VIDEO',   'Series/PRIME VIDEO',       78, 'series'],
        ['/var/www/media/series1/Series/UNIVERSAL+',    'Series/UNIVERSAL+',        78, 'series'],
        ['/var/www/media/series1/Series/VIX+',          'Series/VIX+',              78, 'series'],
    ];

    // Cache de posters por nombre de serie para no llamar TMDB N veces por serie
    private array $seriesPosterCache = [];
    private ?TmdbPosterService $tmdb = null;
    private ?JellyfinPosterService $jellyfin = null;
    private bool $enrichPosters = true;

    public function handle(): int
    {
        $this->enrichPosters = ! (bool) $this->option('no-posters');

        if ($this->enrichPosters) {
            $this->tmdb     = app(TmdbPosterService::class);
            $this->jellyfin = app(JellyfinPosterService::class);

            $tmdbOk     = $this->tmdb->isConfigured() && $this->tmdb->verifyApiKey()['ok'];
            $jellyfinOk = $this->jellyfin->isConfigured() && $this->jellyfin->verify()['ok'];

            if (! $tmdbOk && ! $jellyfinOk) {
                $this->warn('Sin fuentes de carátulas disponibles. Importando sin posters.');
                $this->enrichPosters = false;
            }
        }

        $imported = 0;
        $skipped  = 0;

        foreach ($this->mappings as [$diskPath, $libFolder, $catId, $type]) {
            if (! is_dir($diskPath)) continue;

            foreach (scandir($diskPath) as $item) {
                if ($item === '.' || $item === '..') continue;
                $full = $diskPath . '/' . $item;

                if (is_file($full) && preg_match('/\.(mkv|mp4|avi|mov)$/i', $item)) {
                    $streamUrl = 'local:' . $full;
                    if (Content::where('stream_url', $streamUrl)->exists()) { $skipped++; continue; }
                    $title     = preg_replace('/\s*\(\d{4}\)\s*$/', '', pathinfo($item, PATHINFO_FILENAME));
                    $libPath   = $libFolder . '/' . trim($title);
                    $posterUrl = $this->findPoster(trim($title), $libPath, $type);
                    Content::create([
                        'category_id'    => $catId,
                        'title'          => trim($title),
                        'type'           => $type,
                        'stream_url'     => $streamUrl,
                        'poster_url'     => $posterUrl,
                        'is_active'      => true,
                        'library_folder' => $libPath,
                    ]);
                    $imported++;
                }

                if (is_dir($full)) {
                    $this->importDir($full, $libFolder . '/' . $item, $catId, $type, $imported, $skipped);
                }
            }
        }

        $this->info("Importados: {$imported} | Omitidos: {$skipped}");
        return self::SUCCESS;
    }

    private function importDir(string $dir, string $libFolder, int $catId, string $type, int &$imported, int &$skipped): void
    {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $full = $dir . '/' . $item;
            if (is_file($full) && preg_match('/\.(mkv|mp4|avi|mov)$/i', $item)) {
                $streamUrl = 'local:' . $full;
                if (Content::where('stream_url', $streamUrl)->exists()) { $skipped++; continue; }
                $title     = preg_replace('/\s*\(\d{4}\)\s*$/', '', pathinfo($item, PATHINFO_FILENAME));
                $posterUrl = $this->findPoster(trim($title), $libFolder, $type);
                Content::create([
                    'category_id'    => $catId,
                    'title'          => trim($title),
                    'type'           => $type,
                    'stream_url'     => $streamUrl,
                    'poster_url'     => $posterUrl,
                    'is_active'      => true,
                    'library_folder' => $libFolder,
                ]);
                $imported++;
            } elseif (is_dir($full)) {
                $this->importDir($full, $libFolder, $catId, $type, $imported, $skipped);
            }
        }
    }

    /**
     * Busca poster para un contenido nuevo.
     * Para series: primero revisa si ya hay un episodio de la misma serie con poster en BD,
     * luego cache en memoria, luego Jellyfin, luego TMDB.
     * Para VOD: Jellyfin → TMDB directamente.
     */
    private function findPoster(string $title, string $libFolder, string $type): ?string
    {
        if (! $this->enrichPosters) return null;

        if ($type === 'series') {
            $seriesName = $this->extractSeriesName($libFolder);
            if ($seriesName === '') return null;

            // 1. Cache en memoria (mismo sync)
            if (isset($this->seriesPosterCache[$seriesName])) {
                return $this->seriesPosterCache[$seriesName];
            }

            // 2. Ya existe en BD otro episodio de esta serie con poster
            $existing = Content::where('type', 'series')
                ->where('library_folder', 'like', '%/' . $seriesName . '/%')
                ->whereNotNull('poster_url')
                ->where('poster_url', '!=', '')
                ->value('poster_url');

            if ($existing) {
                $this->seriesPosterCache[$seriesName] = $existing;
                return $existing;
            }

            // 3. Jellyfin
            $url = $this->jellyfin?->posterUrlForSeries($seriesName);

            // 4. TMDB fallback
            if (! $url && $this->tmdb) {
                $clean = PosterTitleSanitizer::forSearch($seriesName);
                if ($clean !== '') {
                    $url = $this->tmdb->posterUrlForTitle($clean, ContentType::Series);
                }
            }

            $this->seriesPosterCache[$seriesName] = $url;
            return $url;
        }

        // VOD — usar última parte del library_folder como título de búsqueda
        $parts       = array_values(array_filter(explode('/', str_replace('\\', '/', $libFolder)), fn($p) => trim($p) !== ''));
        $folderTitle = count($parts) >= 1 ? trim($parts[count($parts) - 1]) : $title;
        $clean       = PosterTitleSanitizer::forSearch($folderTitle);
        if ($clean === '') $clean = PosterTitleSanitizer::forSearch($title);
        if ($clean === '') return null;

        $year = null;
        if (preg_match('/\b((?:19|20)\d{2})\b/', $folderTitle, $m)) $year = (int) $m[1];

        // Jellyfin primero para VOD también
        // (Jellyfin tiene Items de Movies, no solo Series)
        $url = null;
        if ($this->tmdb) {
            $url = $this->tmdb->posterUrlForTitle($clean, ContentType::Vod, $year);
        }

        return $url;
    }

    private function extractSeriesName(string $libFolder): string
    {
        $parts = array_values(array_filter(
            explode('/', str_replace('\\', '/', $libFolder)),
            fn($p) => trim($p) !== ''
        ));
        if (count($parts) >= 3) return trim($parts[2]);
        if (count($parts) >= 2) return trim($parts[count($parts) - 1]);
        return '';
    }
}
