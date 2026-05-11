<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BuildLoginFingerprintAssetCommand extends Command
{
    protected $signature = 'app:build-login-fingerprint {input : Ruta al PNG de origen} {--threshold=238 : RGB mínimo para tratar como blanco y volver transparente (220–250)} {--knockout-matte=20 : Negro/gris muy uniforme ≤ este valor (0 = desactivar; subí si queda mate, bajá si se comen detalles)}';

    protected $description = 'Genera public/images/login-fingerprint.png: respeta alpha del PNG, quita fondo claro y el mate negro plano típico de exportaciones (requiere GD).';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('La extensión GD de PHP es obligatoria.');

            return self::FAILURE;
        }

        $rawInput = (string) $this->argument('input');
        $candidates = $this->candidatePaths($rawInput);
        $resolved = $this->firstReadableFile($candidates);
        if ($resolved === null) {
            $this->error('No se encontró un PNG legible en esa ruta.');
            $this->line('');
            $this->line('<fg=cyan>Rutas comprobadas:</>');
            foreach ($candidates as $path) {
                $this->line('  · '.$this->describePathFailure($path));
            }
            $this->line('');
            $this->warn('Tenés que pasar un archivo que exista. Copiá tu PNG al disco y usá esa ruta (arrastrá el archivo a la consola en PowerShell para pegar la ruta).');
            $this->line('Ejemplos en Windows:');
            $this->line('  php artisan app:build-login-fingerprint "C:\\Users\\alexy\\Pictures\\logo.png"');
            $this->line('  php artisan app:build-login-fingerprint storage\\app\\login-src.png');
            $this->line('    (primero copiá tu PNG a '.storage_path('app').' con el nombre login-src.png)');
            $this->line('');
            $this->line('Para volver al icono SVG del login, borrá: '.public_path('images/login-fingerprint.png'));

            return self::FAILURE;
        }

        $input = $resolved;
        $data = @file_get_contents($input);
        if ($data === false) {
            $this->error('No se pudo leer el archivo.');

            return self::FAILURE;
        }

        $im = @imagecreatefromstring($data);
        if ($im === false) {
            $this->error('No es una imagen válida (usa PNG).');

            return self::FAILURE;
        }

        imagealphablending($im, false);
        imagesavealpha($im, true);

        $w = imagesx($im);
        $h = imagesy($im);
        $threshold = max(200, min(252, (int) $this->option('threshold')));
        $knockoutMatte = max(0, min(80, (int) $this->option('knockout-matte')));
        $tr = imagecolorallocatealpha($im, 0, 0, 0, 127);
        $isTruecolor = imageistruecolor($im);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($im, $x, $y);
                $a = $isTruecolor ? (($rgba >> 24) & 0x7F) : 0;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                // PNG con alpha: píxeles casi transparentes en el origen.
                if ($isTruecolor && $a >= 100) {
                    imagesetpixel($im, $x, $y, $tr);

                    continue;
                }

                if ($r >= $threshold && $g >= $threshold && $b >= $threshold) {
                    imagesetpixel($im, $x, $y, $tr);

                    continue;
                }

                // Mate negro/gris plano (fondo “sin fondo” mal exportado).
                if ($knockoutMatte > 0) {
                    $hi = max($r, $g, $b);
                    $lo = min($r, $g, $b);
                    if ($hi <= $knockoutMatte && ($hi - $lo) <= 10) {
                        imagesetpixel($im, $x, $y, $tr);
                    }
                }
            }
        }

        $outDir = public_path('images');
        File::ensureDirectoryExists($outDir);
        $outPath = $outDir.'/login-fingerprint.png';

        imagealphablending($im, false);
        imagesavealpha($im, true);
        if (! @imagepng($im, $outPath, 9)) {
            imagedestroy($im);
            $this->error('No se pudo escribir '.$outPath);

            return self::FAILURE;
        }

        imagedestroy($im);
        $this->info('Listo: '.$outPath);
        $this->line('Si el navegador sigue mostrando la imagen vieja, probá recargar el login con Ctrl+F5 (sin caché).');
        $this->line('Si aún ves mate negro/gris, probá subir --knockout-matte (p. ej. 35) o bajarlo si se pierden trazos finos.');

        return self::SUCCESS;
    }

    /**
     * @return list<non-empty-string>
     */
    private function candidatePaths(string $raw): array
    {
        $trimmed = trim($raw, " \t\n\r\0\x0B\"'");
        if ($trimmed === '') {
            return [];
        }

        $norm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $trimmed);

        $isAbsolute = str_starts_with($norm, DIRECTORY_SEPARATOR)
            || (strlen($norm) >= 2 && ctype_alpha($norm[0]) && $norm[1] === ':');

        $candidates = [$trimmed, $norm];

        if (! $isAbsolute) {
            $rel = ltrim($norm, DIRECTORY_SEPARATOR);
            $candidates[] = base_path($rel);
            if (! preg_match('#^storage[/\\\\]#i', $rel)) {
                $candidates[] = public_path($rel);
            }
            if (preg_match('#^storage[/\\\\]#i', $rel)) {
                $inside = preg_replace('#^storage[/\\\\]#i', '', $rel);
                $inside = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $inside);
                $candidates[] = storage_path($inside);
            }
        } elseif (DIRECTORY_SEPARATOR === '\\') {
            $fwd = str_replace('\\', '/', $trimmed);
            if ($fwd !== $trimmed) {
                $candidates[] = $fwd;
            }
        }

        /** @var list<non-empty-string> $out */
        $out = array_values(array_unique(array_filter($candidates, fn (string $p): bool => $p !== '')));

        return $out;
    }

    /**
     * @param  list<non-empty-string>  $candidates
     * @return non-empty-string|null
     */
    private function firstReadableFile(array $candidates): ?string
    {
        foreach ($candidates as $path) {
            $real = @realpath($path);
            if ($real !== false && is_file($real) && is_readable($real)) {
                return $real;
            }
        }

        return null;
    }

    private function describePathFailure(string $path): string
    {
        $exists = @file_exists($path);
        if (! $exists) {
            $dir = dirname($path);
            if (@is_dir($dir)) {
                return $path.' <fg=yellow>(no existe el archivo; la carpeta sí)</>';
            }

            return $path.' <fg=yellow>(no existe)</>';
        }

        if (! is_file($path)) {
            return $path.' <fg=yellow>(existe pero no es un archivo)</>';
        }

        if (! is_readable($path)) {
            return $path.' <fg=yellow>(sin permiso de lectura; revisá open_basedir en php.ini si usás Laragon)</>';
        }

        $real = @realpath($path);

        return $path.' <fg=yellow>('.($real === false ? 'realpath falló' : 'no legible').')</>';
    }
}
