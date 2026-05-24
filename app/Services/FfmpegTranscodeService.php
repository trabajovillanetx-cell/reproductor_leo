<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class FfmpegTranscodeService
{
    private string $hlsBase = '/tmp/hls';

    public function isAvailable(): bool
    {
        if (! (bool) config('streaming.ffmpeg_enabled', false)) {
            return false;
        }
        $bin = $this->binaryPath();
        if ($bin === '') {
            return false;
        }
        $out = @shell_exec(escapeshellcmd($bin) . ' -version 2>&1');
        return is_string($out) && str_contains(strtolower($out), 'ffmpeg version');
    }

    public function transcodeLocalFileResponse(string $filePath): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $bin = $this->binaryPath();
        return response()->stream(function () use ($bin, $filePath): void {
            if (! $this->acquireSlot()) {
                return;
            }
            try {
                $cmd = [
                    $bin, '-hide_banner', '-nostdin', '-loglevel', 'error',
                    '-i', $filePath,
                    '-map', '0:v:0?', '-map', '0:a:0?',
                    '-c:v', 'copy',
                    '-c:a', 'aac', '-b:a', '192k', '-ac', '2', '-ar', '48000',
                    '-f', 'mp4',
                    '-movflags', 'frag_keyframe+empty_moov+default_base_moof',
                    '-',
                ];
                $process = proc_open($cmd, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, null, null, ['bypass_shell'=>true]);
                if (!is_resource($process)) return;
                fclose($pipes[0]);
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);
                while (!feof($pipes[1])) {
                    if (connection_aborted()) break;
                    $chunk = fread($pipes[1], 65536);
                    if ($chunk !== false && $chunk !== '') { echo $chunk; flush(); }
                    stream_get_contents($pipes[2], 4096);
                    usleep(2000);
                }
                fclose($pipes[1]); fclose($pipes[2]);
                proc_close($process);
            } finally {
                $this->releaseSlot();
            }
        }, 200, [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'private, no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function getHlsManifestUrl(string $sourceUrl, string $channelKey): string
    {
        $dir     = $this->hlsBase . '/' . $channelKey;
        $pidFile = $dir . '/ffmpeg.pid';
        $m3u8    = $dir . '/stream.m3u8';

        if (! $this->ffmpegIsRunning($pidFile) || ! file_exists($m3u8)) {
            $this->startFfmpeg($sourceUrl, $channelKey);

            $attempts = 0;
            while (! file_exists($m3u8) && $attempts < 10) {
                usleep(500_000);
                $attempts++;
            }

            if (! file_exists($m3u8)) {
                Log::error("FFmpeg HLS: no se generó stream.m3u8 para {$channelKey}");
                abort(503, 'Canal no disponible en este momento.');
            }
        }

        return '/hls/' . $channelKey . '/stream.m3u8';
    }

    public function stopChannel(string $channelKey): void
    {
        $pidFile = $this->hlsBase . '/' . $channelKey . '/ffmpeg.pid';
        if (file_exists($pidFile)) {
            $pid = (int) file_get_contents($pidFile);
            if ($pid > 0) {
                posix_kill($pid, SIGTERM);
            }
        }
    }

    public function cleanupStale(): void
    {
        $hlsBase = $this->hlsBase;

        foreach (glob($hlsBase . '/*/ffmpeg.pid') ?: [] as $pidFile) {
            $dir  = dirname($pidFile);
            $pid  = (int) file_get_contents($pidFile);
            $m3u8 = $dir . '/stream.m3u8';

            $processDead = $pid <= 0 || ! file_exists("/proc/{$pid}");

            // m3u8 invalido: no existe, o tiene TARGETDURATION:0, o tiene menos de 50 bytes
            $m3u8Invalid = ! file_exists($m3u8)
                || filesize($m3u8) < 50
                || str_contains((string) @file_get_contents($m3u8), 'TARGETDURATION:0');

            // Sin actividad: el m3u8 no se modifico en los ultimos 60 segundos y el proceso esta vivo
            $m3u8Stale = file_exists($m3u8) && (time() - filemtime($m3u8)) > 60;

            if ($processDead || $m3u8Invalid || $m3u8Stale) {
                // Matar proceso si sigue vivo
                if ($pid > 0 && file_exists("/proc/{$pid}")) {
                    posix_kill($pid, SIGTERM);
                    usleep(200_000);
                    if (file_exists("/proc/{$pid}")) {
                        posix_kill($pid, SIGKILL);
                    }
                }
                foreach (glob($dir . '/*') ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir($dir);
                \Illuminate\Support\Facades\Log::info("HLS cleanup: eliminado {$dir} (dead={$processDead} invalid={$m3u8Invalid} stale={$m3u8Stale})");
            }
        }
    }

    private function ffmpegIsRunning(string $pidFile): bool
    {
        if (! file_exists($pidFile)) {
            return false;
        }
        $pid = (int) file_get_contents($pidFile);
        return $pid > 0 && file_exists("/proc/{$pid}");
    }

    private function startFfmpeg(string $sourceUrl, string $channelKey): void
    {
        if (! $this->acquireSlot()) {
            abort(503, 'Límite de streams simultáneos alcanzado.');
        }

        $dir = $this->hlsBase . '/' . $channelKey;
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $bin     = escapeshellarg($this->binaryPath());
        $input   = escapeshellarg($sourceUrl);
        $pidFile = $dir . '/ffmpeg.pid';
        $logFile = $dir . '/ffmpeg.log';
        $m3u8    = $dir . '/stream.m3u8';
        $segPat  = $dir . '/seg_%04d.ts';

        $cmd = implode(' ', [
            $bin,
            '-hide_banner -nostdin -loglevel error',
            '-reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5',
            '-timeout 10000000',
            '-err_detect ignore_err',
            '-i', $input,
            '-map 0:v:0? -map 0:a:0?',
            '-fflags +discardcorrupt',
            '-c:v copy',
            '-c:a aac -b:a 128k -ac 2 -ar 48000',
            '-f hls',
            '-hls_time 1',
            '-hls_list_size 6',
            '-hls_flags delete_segments+append_list+omit_endlist',
            '-hls_segment_type mpegts',
            '-hls_segment_filename', escapeshellarg($segPat),
            '-flush_packets 1',
            escapeshellarg($m3u8),
        ]);

        $full = "nohup {$cmd} > " . escapeshellarg($logFile) . " 2>&1 & echo \$!";
        $pid  = (int) trim((string) shell_exec($full));

        file_put_contents($pidFile, $pid);
        Log::info("FFmpeg HLS iniciado: canal={$channelKey} pid={$pid}");
    }

    private function acquireSlot(): bool
    {
        $max = max(1, (int) config('streaming.ffmpeg_max_concurrent', 30));
        try {
            if (Cache::get('ffmpeg_hls_active') === null) {
                Cache::put('ffmpeg_hls_active', 0, 3600);
            }
            $v = (int) Cache::increment('ffmpeg_hls_active');
        } catch (\Throwable $e) {
            Cache::put('ffmpeg_hls_active', 1, 3600);
            $v = 1;
        }
        if ($v > $max) {
            Cache::decrement('ffmpeg_hls_active');
            return false;
        }
        return true;
    }

    private function binaryPath(): string
    {
        return trim((string) config('streaming.ffmpeg_bin', 'ffmpeg'));
    }
}
