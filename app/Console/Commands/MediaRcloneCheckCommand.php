<?php

namespace App\Console\Commands;

use App\Services\LocalMediaService;
use App\Services\RcloneCliService;
use Illuminate\Console\Command;

class MediaRcloneCheckCommand extends Command
{
    protected $signature = 'media:rclone-check
                            {--remote= : Remoto rclone a inspeccionar con "rclone about", ej. gdrive: o myremote:Peliculas}';

    protected $description = 'Comprueba rutas de biblioteca local (RAIDRIVE_* o RCLONE_MOUNT_*) y (opcional) rclone about.';

    public function handle(LocalMediaService $localMedia, RcloneCliService $rclone): int
    {
        $roots = $localMedia->roots();
        if ($roots === []) {
            $opt = $localMedia->localLibraryDriverOption();
            $this->warn('No hay rutas de biblioteca local según LOCAL_LIBRARY_DRIVER='.$opt.'.');
            $this->line('  raidrive → RAIDRIVE_LOCAL_PATH / RAIDRIVE_LOCAL_PATHS');
            $this->line('  rclone   → RCLONE_MOUNT_PATH / RCLONE_MOUNT_PATHS');
            $this->line('  auto     → en Windows prioriza RAIDRIVE_*; en Linux prioriza RCLONE_MOUNT_*.');

            return self::FAILURE;
        }

        $this->info('Rutas activas ('.$localMedia->localLibraryRootsBackend().', driver='.$localMedia->localLibraryDriverOption().') — deben existir y ser legibles por PHP:');
        foreach ($roots as $root) {
            $dir = is_dir($root);
            $read = is_readable($root);
            $mark = $dir && $read ? '<fg=green>OK</>' : '<fg=red>FALLO</>';
            $this->line("  {$mark} {$root}");
            if (! $dir) {
                $this->line('      → No es un directorio. En Linux montá la nube con rclone mount y usá esa ruta en .env.');
            } elseif (! $read) {
                $this->line('      → El usuario del servidor web no puede leer esta ruta (chmod o allow_other en mount).');
            }
        }

        $this->newLine();
        $ver = $rclone->runVersion();
        if ($ver === null) {
            $this->warn('No se pudo ejecutar `'.$rclone->binary().' version` (¿instalado y en PATH? Configurá RCLONE_PATH en .env).');
        } else {
            $this->info('rclone: '.$ver);
        }

        $remote = (string) $this->option('remote');
        if ($remote !== '') {
            $this->newLine();
            $this->info('rclone about '.$remote);
            $about = $rclone->runAbout($remote);
            $this->line($about['output']);
            if (! $about['ok']) {
                return self::FAILURE;
            }
        } else {
            $this->comment('Tip: `php artisan media:rclone-check --remote=miRemote:` para ver espacio libre en la nube.');
        }

        return self::SUCCESS;
    }
}
