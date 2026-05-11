<?php

namespace App\Console\Commands;

use App\Services\LocalMediaService;
use App\Services\RaidriveRenameSyncService;
use Illuminate\Console\Command;

final class SyncLocalRenamesCommand extends Command
{
    protected $signature = 'content:sync-local-renames';

    protected $description = 'Actualiza rutas local: renombres en la misma carpeta (1 fila rota + 1 archivo sin registrar → re-enlaza y reintenta TMDB).';

    public function handle(LocalMediaService $localMedia, RaidriveRenameSyncService $renameSync): int
    {
        if (! $localMedia->isConfigured()) {
            $this->error('Biblioteca local sin rutas: revisá LOCAL_LIBRARY_DRIVER y RAIDRIVE_* o RCLONE_MOUNT_* en .env.');

            return self::FAILURE;
        }

        $stats = $renameSync->sync();
        $localMedia->bumpRaidriveCacheEpoch();

        $this->info('Re-enlazados: '.$stats['relinked']);
        $this->line('Sin archivo claro en la carpeta: '.$stats['still_broken']);
        $this->line('Casos ambiguos (omitidos): '.$stats['ambiguous']);
        $this->line('Directorio padre ilegible: '.$stats['parent_unreachable']);

        return self::SUCCESS;
    }
}
