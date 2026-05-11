<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Services\TmdbPosterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContentPosterEnrichmentController extends Controller
{
    public function __construct(
        private TmdbPosterService $tmdb
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Content::class);

        if (! $this->tmdb->isConfigured()) {
            return redirect()
                ->route('admin.contents.index')
                ->with('error', 'Definí TMDB_API_KEY en .env y ejecutá php artisan config:clear para buscar carátulas.');
        }

        $verify = $this->tmdb->verifyApiKey();
        if (! $verify['ok']) {
            $hint = $verify['status'] === 401
                ? ' La clave es inválida o fue revocada: creá una API Key (v3) nueva en themoviedb.org/settings/api (no uses el token de sólo lectura v4 como si fuera api_key).'
                : '';

            return redirect()
                ->route('admin.contents.index')
                ->with('error', 'TMDB rechazó la conexión (HTTP '.$verify['status'].').'.($verify['message'] !== '' ? ' '.$verify['message'] : '').$hint.' Después actualizá .env y ejecutá php artisan config:clear.');
        }

        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:80'],
        ]);

        $limit = (int) ($data['limit'] ?? 30);

        $candidates = Content::query()
            ->whereIn('type', [ContentType::Vod, ContentType::Series, ContentType::Live])
            ->where(function ($q): void {
                $q->whereNull('poster_url')->orWhere('poster_url', '');
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($candidates->isEmpty()) {
            return redirect()
                ->route('admin.contents.index')
                ->with('success', 'No hay películas, series o canales en vivo sin carátula para procesar.');
        }

        $ok = 0;
        foreach ($candidates as $content) {
            if ($this->tmdb->enrichContentPoster($content)) {
                $ok++;
                usleep((int) config('services.tmdb.delay_ms_between_requests', 200) * 1000);
            }
        }

        $skipped = $candidates->count() - $ok;

        $msg = "Carátulas TMDB: {$ok} actualizada(s). Sin coincidencia o sin póster en TMDB: {$skipped}.";
        if ($ok === 0 && $skipped > 0) {
            $msg .= ' Revisá que el título del contenido coincida con TMDB o asigná el poster a mano en Editar contenido.';
        }

        return redirect()
            ->route('admin.contents.index')
            ->with('success', $msg);
    }
}
