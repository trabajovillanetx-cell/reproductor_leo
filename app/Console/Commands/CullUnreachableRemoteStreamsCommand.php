<?php

namespace App\Console\Commands;

use App\Services\RemoteUnreachableStreamsCuller;
use Illuminate\Console\Command;

class CullUnreachableRemoteStreamsCommand extends Command
{
    protected $signature = 'content:cull-unreachable-remotes
                            {--category-id= : Limitar a esta categoría y sus subcarpetas}
                            {--type= : Limitar a tipo de contenido: vod, live o series}
                            {--dry-run : Solo muestra cantidades sin borrar}';

    protected $description = 'Elimina del catálogo los ítems con URL http(s) que ya no respondan (misma sonda que al importar M3U).';

    public function handle(RemoteUnreachableStreamsCuller $culler): int
    {
        set_time_limit(0);

        $categoryIdRaw = $this->option('category-id');
        $categoryId = $categoryIdRaw !== null && $categoryIdRaw !== '' ? (int) $categoryIdRaw : null;

        $dry = (bool) $this->option('dry-run');

        $typeRaw = $this->option('type');
        $restrictType = is_string($typeRaw) && in_array(mb_strtolower($typeRaw), ['vod', 'live', 'series'], true)
            ? mb_strtolower($typeRaw)
            : null;

        // Proteccion: nunca borrar live sin confirmacion explicita
        if (!$dry && ($restrictType === null || $restrictType === 'live')) {
            if (!$this->confirm('⚠️  Esto puede borrar canales LIVE. ¿Seguro?', false)) {
                $this->warn('Cancelado.');
                return self::SUCCESS;
            }
        }
        $this->info($dry ? 'Simulación (no se borrará nada)…' : 'Comprobando URLs y borrando entradas caídas…');

        $report = $culler->cull($categoryId, $dry, $dry ? 40 : 0, $restrictType);

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Filas revisadas', (string) $report['rows_scanned']],
                ['URLs únicas irresolubles (aprox.)', (string) $report['distinct_unreachable_urls']],
                [$dry ? 'Se eliminarían (filas)' : 'Eliminadas (filas)', (string) $report['removed']],
            ]
        );

        if ($dry && ($report['dead_samples'] ?? []) !== []) {
            $this->newLine();
            $this->info('Muestra de canales caídos (máx. 40):');
            $this->table(
                ['ID', 'Título', 'URL stream'],
                array_map(static fn (array $r): array => [
                    (string) $r['id'],
                    $r['title'],
                    $r['stream_url'],
                ], $report['dead_samples'])
            );
            if ($report['removed'] > count($report['dead_samples'])) {
                $this->warn('… y '.($report['removed'] - count($report['dead_samples'])).' fila(s) más no listadas.');
            }
        }

        return self::SUCCESS;
    }
}
