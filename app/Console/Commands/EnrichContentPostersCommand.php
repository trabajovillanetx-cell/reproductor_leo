<?php

namespace App\Console\Commands;

use App\Enums\ContentType;
use App\Models\Content;
use App\Services\TmdbPosterService;
use Illuminate\Console\Command;

class EnrichContentPostersCommand extends Command
{
    protected $signature = 'content:enrich-posters
                            {--limit=100 : Máximo de ítems a procesar}
                            {--dry-run : Solo mostrar qué haría, sin guardar}
                            {--no-progress : No mostrar barra de progreso}';

    protected $description = 'Busca carátulas en TMDB para VOD/Series/canales en vivo sin poster_url';

    public function handle(TmdbPosterService $tmdb): int
    {
        if (! $tmdb->isConfigured()) {
            $this->error('Falta TMDB_API_KEY en .env.');

            return self::FAILURE;
        }

        $verify = $tmdb->verifyApiKey();
        if (! $verify['ok']) {
            $this->error('TMDB no aceptó tu API key (HTTP '.$verify['status'].').'.($verify['message'] !== '' ? ' Detalle: '.$verify['message'] : ''));
            if ($verify['status'] === 401) {
                $this->line('Solución: en https://www.themoviedb.org/settings/api copiá la clave "API Key" (v3), pegala en TMDB_API_KEY= en .env, guardá y ejecutá: php artisan config:clear');
                $this->line('No confundas con el "Access Token" de sólo lectura v4: la app usa el parámetro api_key de la API v3.');
            }

            return self::FAILURE;
        }

        $limit = max(1, min(5000, (int) $this->option('limit')));
        $dry = (bool) $this->option('dry-run');

        $candidates = Content::query()
            ->whereIn('type', [ContentType::Vod, ContentType::Series])
            ->where(function ($q): void {
                $q->whereNull('poster_url')->orWhere('poster_url', '');
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No hay candidatos sin carátula.');

            return self::SUCCESS;
        }

        $total = $candidates->count();
        $this->info("Candidatos sin carátula en esta pasada: {$total} (tope --limit={$limit}).");

        $showProgress = ! $dry && ! (bool) $this->option('no-progress') && ! $this->output->isQuiet();
        $bar = $showProgress ? $this->output->createProgressBar($total) : null;
        $bar?->start();

        $ok = 0;
        $delayUs = max(0, (int) config('services.tmdb.delay_ms_between_requests', 200)) * 1000;

        foreach ($candidates as $content) {
            $title = (string) $content->title;
            if ($dry) {
                $this->line("[dry-run] #{$content->id} {$title}");
                $bar?->advance();

                continue;
            }
            if ($tmdb->enrichContentPoster($content)) {
                $ok++;
                if ($delayUs > 0) {
                    usleep($delayUs);
                }
            }
            $bar?->advance();
        }

        $bar?->finish();
        if ($bar !== null) {
            $this->newLine();
        }

        if (! $dry) {
            $skipped = $total - $ok;
            $this->info("Listo: {$ok} carátula(s) guardada(s), {$skipped} sin coincidencia o sin póster en TMDB.");
        }

        return self::SUCCESS;
    }
}
