<?php

namespace App\Jobs;

use App\Models\SiteSetting;
use App\Services\RemoteUnreachableStreamsCuller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CullDeadStreamsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Clave de caché donde se almacena el estado del último barrido */
    public const STATUS_CACHE_KEY = 'cull_dead_streams_status';

    /** Tiempo máximo que puede ejecutarse el job (segundos) */
    public int $timeout = 7200; // 2 horas

    /** Sin reintentos automáticos — si falla, que lo relance el admin */
    public int $tries = 1;

    public function __construct(
        public readonly ?int $categoryId = null,
        public readonly bool $triggeredBySchedule = false,
        public readonly bool $dryRun = false,
        /** @var 'vod'|'live'|'series'|null */
        public readonly ?string $contentType = null,
    ) {}

    public function handle(RemoteUnreachableStreamsCuller $culler): void
    {
        // Marcar como en progreso
        Cache::put(self::STATUS_CACHE_KEY, [
            'running'   => true,
            'started_at' => now()->toIso8601String(),
            'triggered_by' => $this->triggeredBySchedule ? 'schedule' : 'admin',
            'category_id' => $this->categoryId,
            'content_type' => $this->contentType,
            'dry_run' => $this->dryRun,
            'result'    => null,
        ], now()->addHours(3));

        try {
            set_time_limit(0);
            $sampleLimit = $this->dryRun
                ? max(0, (int) config('m3u.dry_run_dead_sample_limit'))
                : 0;
            $report = $culler->cull($this->categoryId, $this->dryRun, $sampleLimit, $this->contentType);

            $status = [
                'running'      => false,
                'started_at'   => Cache::get(self::STATUS_CACHE_KEY)['started_at'] ?? null,
                'finished_at'  => now()->toIso8601String(),
                'triggered_by' => $this->triggeredBySchedule ? 'schedule' : 'admin',
                'category_id'  => $this->categoryId,
                'content_type' => $this->contentType,
                'dry_run'      => $this->dryRun,
                'result'       => $report,
            ];

            Cache::put(self::STATUS_CACHE_KEY, $status, now()->addHours(24));

            Log::info('CullDeadStreamsJob completado', $report);

        } catch (\Throwable $e) {
            Cache::put(self::STATUS_CACHE_KEY, [
                'running'     => false,
                'finished_at' => now()->toIso8601String(),
                'triggered_by' => $this->triggeredBySchedule ? 'schedule' : 'admin',
                'category_id'  => $this->categoryId,
                'content_type' => $this->contentType,
                'dry_run'     => $this->dryRun,
                'error'       => $e->getMessage(),
                'result'      => null,
            ], now()->addHours(24));

            Log::error('CullDeadStreamsJob falló: '.$e->getMessage());
            throw $e;
        }
    }
}
