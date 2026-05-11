<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Invocación segura del binario rclone (diagnósticos en VPS).
 * La reproducción "local:" sigue siendo por archivos en disco; lo habitual es
 * {@see https://rclone.org/commands/rclone_mount/ rclone mount} y en .env usar
 * RCLONE_MOUNT_PATH (o LOCAL_LIBRARY_DRIVER=rclone) apuntando al punto de montaje.
 */
final class RcloneCliService
{
    public function binary(): string
    {
        $b = trim((string) config('media.rclone_binary', 'rclone'));

        return $b !== '' ? $b : 'rclone';
    }

    public function runVersion(): ?string
    {
        try {
            $r = Process::timeout(12)->run([$this->binary(), 'version']);
            if (! $r->successful()) {
                return null;
            }

            $out = trim($r->output());
            $first = explode("\n", $out, 2)[0] ?? $out;

            return $first !== '' ? $first : null;
        } catch (\Throwable $e) {
            Log::debug('rclone version failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{ok: bool, output: string}
     */
    public function runAbout(string $remoteSpec): array
    {
        $remoteSpec = trim($remoteSpec);
        if ($remoteSpec === '' || ! preg_match('#^[A-Za-z0-9_.\\-]+:#', $remoteSpec)) {
            return ['ok' => false, 'output' => 'Formato inválido. Usá algo como "gdrive:Peliculas" o "myremote:".'];
        }

        try {
            $r = Process::timeout(45)->run([$this->binary(), 'about', $remoteSpec]);

            return [
                'ok' => $r->successful(),
                'output' => trim($r->output()."\n".$r->errorOutput()),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }
    }
}
