<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Models\Category;
use App\Models\Content;
use Illuminate\Support\Str;

class RemoteUnreachableStreamsCuller
{
    public function __construct(
        private StreamReachabilityProbe $probe
    ) {}

    /**
     * Recorre contenido http(s), prueba cada URL única por lote y elimina ítems cuya URL no pase la misma validación que en la importación M3U.
     *
     * @return array{
     *     removed: int,
     *     rows_scanned: int,
     *     distinct_unreachable_urls: int,
     *     dry_run: bool,
     *     dead_samples: list<array{id: int, title: string, stream_url: string}>
     * }
     */
    /**
     * @param  'vod'|'live'|'series'|null  $restrictType  Filtra por columna `contents.type` (además del filtro por categoría, si aplica).
     */
    public function cull(?int $restrictCategoryId = null, bool $dryRun = false, int $deadSampleLimit = 0, ?string $restrictType = null): array
    {
        $this->probe->resetMemo();

        $removed = 0;
        $rowsScanned = 0;
        $deadUrlKeys = [];
        /** @var list<array{id: int, title: string, stream_url: string}> */
        $deadSamples = [];

        $query = Content::query()->whereRemoteStreamUrl()->orderBy('id');

        if ($restrictCategoryId !== null) {
            $category = Category::query()->findOrFail($restrictCategoryId);
            $query->whereIn('category_id', $category->descendantIdsIncludingSelf());
        }

        if ($restrictType !== null && $restrictType !== '') {
            $query->where('type', $restrictType);
        }

        $query->chunkById(400, function ($contents) use (&$removed, &$rowsScanned, &$deadUrlKeys, &$deadSamples, $dryRun, $deadSampleLimit): void {
            $rowsScanned += $contents->count();

            $urls = $contents->pluck('stream_url')->unique()->filter(static function ($u): bool {
                return is_string($u) && trim($u) !== '';
            })->values()->all();
            if ($urls === []) {
                return;
            }

            $aliveMap = $this->probe->evaluateManyDistinct($urls);

            $idsToRemove = [];
            foreach ($contents as $content) {
                $streamUrl = trim((string) $content->stream_url);
                if ($streamUrl === '') {
                    continue;
                }

                if (! ($aliveMap[$streamUrl] ?? false)) {
                    $idsToRemove[] = $content->id;
                    $deadUrlKeys[$streamUrl] = true;
                    if ($deadSampleLimit > 0 && count($deadSamples) < $deadSampleLimit) {
                        $deadSamples[] = [
                            'id' => (int) $content->id,
                            'title' => Str::limit((string) $content->title, 160),
                            'stream_url' => Str::limit($streamUrl, 220),
                        ];
                    }
                }
            }

            if ($idsToRemove !== [] && ! $dryRun) {
                Content::query()->whereIn('id', $idsToRemove)->delete();
            }

            $removed += count($idsToRemove);
        });

        return [
            'removed' => $removed,
            'rows_scanned' => $rowsScanned,
            'distinct_unreachable_urls' => count($deadUrlKeys),
            'dry_run' => $dryRun,
            'dead_samples' => $deadSamples,
        ];
    }

    /**
     * Escaneo completo en una sola petición: no borra nada. Devuelve conteos y listas para la UI del admin.
     *
     * @param  'vod'|'live'|'series'|null  $restrictType
     * @return array{
     *     rows_scanned: int,
     *     rows_reachable: int,
     *     rows_unreachable: int,
     *     distinct_urls_unreachable: int,
     *     dead: list<array{id:int,title:string,stream_url:string,type:string}>,
     *     dead_list_truncated: bool,
     *     alive_sample: list<array{id:int,title:string,type:string}>,
     *     seconds_elapsed: float
     * }
     */
    public function scanCatalog(
        ?int $restrictCategoryId = null,
        ?string $restrictType = null,
    ): array {
        $t0 = microtime(true);
        $this->probe->resetMemo();

        $rowsScanned = 0;
        $rowsReachable = 0;
        $rowsUnreachable = 0;
        $deadUrlKeys = [];
        $maxDeadListed = (int) config('m3u.scan_max_dead_listed');
        $maxAliveSample = (int) config('m3u.scan_alive_sample_size');

        /** @var list<array{id:int,title:string,stream_url:string,type:string}> */
        $dead = [];
        /** @var list<array{id:int,title:string,type:string}> */
        $aliveSample = [];

        $query = Content::query()->whereRemoteStreamUrl()->orderBy('id');

        if ($restrictCategoryId !== null) {
            $category = Category::query()->findOrFail($restrictCategoryId);
            $query->whereIn('category_id', $category->descendantIdsIncludingSelf());
        }

        if ($restrictType !== null && $restrictType !== '') {
            $query->where('type', $restrictType);
        }

        $query->chunkById(400, function ($contents) use (
            &$rowsScanned,
            &$rowsReachable,
            &$rowsUnreachable,
            &$deadUrlKeys,
            &$dead,
            &$aliveSample,
            $maxDeadListed,
            $maxAliveSample,
        ): void {
            $rowsScanned += $contents->count();

            $urls = $contents->pluck('stream_url')->unique()->filter(static function ($u): bool {
                return is_string($u) && trim($u) !== '';
            })->values()->all();
            if ($urls === []) {
                return;
            }

            $aliveMap = $this->probe->evaluateManyDistinct($urls);

            foreach ($contents as $content) {
                $streamUrl = trim((string) $content->stream_url);
                if ($streamUrl === '') {
                    continue;
                }

                $ok = (bool) ($aliveMap[$streamUrl] ?? false);
                $typeVal = $content->type instanceof ContentType
                    ? $content->type->value
                    : (string) $content->type;

                if ($ok) {
                    $rowsReachable++;
                    if (count($aliveSample) < $maxAliveSample) {
                        $aliveSample[] = [
                            'id' => (int) $content->id,
                            'title' => Str::limit((string) $content->title, 160),
                            'type' => $typeVal,
                        ];
                    }
                } else {
                    $rowsUnreachable++;
                    $deadUrlKeys[$streamUrl] = true;
                    if (count($dead) < $maxDeadListed) {
                        $dead[] = [
                            'id' => (int) $content->id,
                            'title' => Str::limit((string) $content->title, 160),
                            'stream_url' => Str::limit($streamUrl, 280),
                            'type' => $typeVal,
                        ];
                    }
                }
            }
        });

        return [
            'rows_scanned' => $rowsScanned,
            'rows_reachable' => $rowsReachable,
            'rows_unreachable' => $rowsUnreachable,
            'distinct_urls_unreachable' => count($deadUrlKeys),
            'dead' => $dead,
            'dead_list_truncated' => $rowsUnreachable > count($dead),
            'alive_sample' => $aliveSample,
            'seconds_elapsed' => round(microtime(true) - $t0, 2),
        ];
    }
}
