<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneExpiredSessionsCommand extends Command
{
    protected $signature = 'sessions:prune {--dry-run : Solo mostrar cuántas filas se borrarían}';

    protected $description = 'Elimina filas antiguas de la tabla sessions (last_activity demasiado viejo)';

    public function handle(): int
    {
        $minutes = max(30, (int) config('streaming.prune_sessions_idle_minutes', 120));
        $cutoff = now()->subMinutes($minutes)->getTimestamp();
        $table = config('session.table', 'sessions');

        $query = DB::table($table)->where('last_activity', '<', $cutoff);

        if ($this->option('dry-run')) {
            $n = (clone $query)->count();
            $this->info("Se borrarían {$n} sesión(es) con last_activity anterior a {$minutes} minutos.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Sesiones eliminadas: {$deleted}.");

        return self::SUCCESS;
    }
}
