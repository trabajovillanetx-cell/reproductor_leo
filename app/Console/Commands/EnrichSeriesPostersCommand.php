<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Services\JellyfinPosterService;
use App\Services\TmdbPosterService;
use App\Enums\ContentType;
use App\Support\PosterTitleSanitizer;
use Illuminate\Console\Command;

class EnrichSeriesPostersCommand extends Command
{
    protected $signature = 'content:enrich-series-posters
                            {--limit=200      : Máximo de series distintas a procesar}
                            {--dry-run        : Solo mostrar qué haría, sin guardar}
                            {--no-jellyfin    : Saltar Jellyfin, usar solo TMDB}
                            {--no-tmdb        : Saltar TMDB fallback}
                            {--no-progress    : No mostrar barra de progreso}
                            {--series=        : Procesar solo una serie por nombre}';

    protected $description = 'Enriquece poster_url de episodios agrupando por serie (Jellyfin → TMDB fallback)';

    public function handle(JellyfinPosterService $jellyfin, TmdbPosterService $tmdb): int
    {
        $dry        = (bool) $this->option('dry-run');
        $noJellyfin = (bool) $this->option('no-jellyfin');
        $noTmdb     = (bool) $this->option('no-tmdb');
        $limit      = max(1, min(5000, (int) $this->option('limit')));
        $onlySeries = $this->option('series');

        $useJellyfin = ! $noJellyfin && $jellyfin->isConfigured();
        $useTmdb     = ! $noTmdb && $tmdb->isConfigured();

        if (! $useJellyfin && ! $useTmdb) {
            $this->error('No hay ninguna fuente configurada. Necesitás JELLYFIN_URL+JELLYFIN_API_KEY o TMDB_API_KEY en .env');
            return self::FAILURE;
        }

        if ($useJellyfin) {
            $check = $jellyfin->verify();
            if (! $check['ok']) {
                $this->warn("Jellyfin no disponible: {$check['message']}. " . ($useTmdb ? 'Usando solo TMDB.' : 'Abortando.'));
                if (! $useTmdb) return self::FAILURE;
                $useJellyfin = false;
            } else {
                $this->info("✔ Jellyfin: {$check['message']}");
            }
        }

        if ($useTmdb) {
            $verify = $tmdb->verifyApiKey();
            if (! $verify['ok']) {
                $this->warn("TMDB no disponible (HTTP {$verify['status']}). " . ($useJellyfin ? 'Usando solo Jellyfin.' : 'Abortando.'));
                if (! $useJellyfin) return self::FAILURE;
                $useTmdb = false;
            } else {
                $this->info('✔ TMDB: API key válida');
            }
        }

        $query = Content::query()
            ->where('type', ContentType::Series)
            ->where(function ($q) { $q->whereNull('poster_url')->orWhere('poster_url', ''); })
            ->whereNotNull('library_folder')
            ->where('library_folder', '!=', '');

        if ($onlySeries) {
            $query->where('library_folder', 'like', '%/' . $onlySeries . '/%');
        }

        $episodes = $query->select(['id', 'title', 'library_folder'])->get();

        if ($episodes->isEmpty()) {
            $this->info('No hay episodios sin carátula.');
            return self::SUCCESS;
        }

        $grouped = $episodes->groupBy(fn($ep) => $this->extractSeriesName($ep->library_folder))
                            ->filter(fn($g, $name) => $name !== '');

        $totalSeries = $grouped->count();
        $totalEp     = $episodes->count();
        $this->info("Series sin carátula: {$totalSeries} series ({$totalEp} episodios). Tope: {$limit} series.");

        if ($dry) {
            $grouped->take($limit)->each(fn($eps, $name) => $this->line("[dry-run] \"{$name}\" → {$eps->count()} ep"));
            return self::SUCCESS;
        }

        $showProgress = ! (bool) $this->option('no-progress') && ! $this->output->isQuiet();
        $bar = $showProgress ? $this->output->createProgressBar(min($totalSeries, $limit)) : null;
        $bar?->start();

        $seriesOk = $seriesSkip = $episodesUpdated = $processed = 0;

        foreach ($grouped->take($limit) as $seriesName => $eps) {
            $processed++;
            $posterUrl = null;
            $source    = '';

            if ($useJellyfin) {
                $posterUrl = $jellyfin->posterUrlForSeries($seriesName);
                if ($posterUrl) $source = 'Jellyfin';
            }

            if ($posterUrl === null && $useTmdb) {
                $clean = PosterTitleSanitizer::forSearch($seriesName);
                if ($clean !== '') {
                    $posterUrl = $tmdb->posterUrlForTitle($clean, ContentType::Series);
                    if ($posterUrl) $source = 'TMDB';
                }
            }

            if ($posterUrl === null) {
                $seriesSkip++;
                $bar?->advance();
                continue;
            }

            $ids     = $eps->pluck('id')->toArray();
            $updated = Content::whereIn('id', $ids)->update(['poster_url' => $posterUrl]);
            $episodesUpdated += $updated;
            $seriesOk++;
            $bar?->advance();
            usleep(150_000);
        }

        $bar?->finish();
        if ($bar) $this->newLine();

        $this->info("✔ {$seriesOk} series con carátula ({$episodesUpdated} episodios), {$seriesSkip} sin resultado.");

        if ($processed < $totalSeries) {
            $this->line('Quedan ' . ($totalSeries - $processed) . ' series. Volvé a correr para continuar.');
        }

        return self::SUCCESS;
    }

    private function extractSeriesName(string $libraryFolder): string
    {
        $parts = array_values(array_filter(
            explode('/', str_replace('\\', '/', $libraryFolder)),
            fn($p) => trim($p) !== ''
        ));

        // "Series/CANAL/NombreSerie/Season XX" → índice 2
        // "Series/Novelas/Colombia/NombreSerie/..." → índice 3
        if (count($parts) >= 4 && strtolower($parts[1]) === 'novelas') return trim($parts[3]);
        if (count($parts) >= 3) return trim($parts[2]);
        if (count($parts) >= 2) return trim($parts[count($parts) - 1]);
        return '';
    }
}
