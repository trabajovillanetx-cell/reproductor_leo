<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FfmpegTranscodeService
{
    public function isAvailable(): bool
    {
        if (! (bool) config('streaming.ffmpeg_enabled', false)) {
            return false;
        }

        $bin = $this->binaryPath();
        if ($bin === '') {
            return false;
        }

        $cmd = escapeshellcmd($bin).' -version 2>&1';
        $out = @shell_exec($cmd);

        return is_string($out) && str_contains(strtolower($out), 'ffmpeg version');
    }

    public function transcodeStreamResponse(string $sourceUrl): StreamedResponse
    {
        $bin = $this->binaryPath();
        if ($bin === '') {
            abort(503, 'FFmpeg no configurado.');
        }

        return response()->stream(function () use ($bin, $sourceUrl): void {
            if (! $this->acquireSlot()) {
                Log::warning('FFmpeg transcode: límite de concurrencia alcanzado');

                return;
            }

            try {
                $cmd = [
                    $bin,
                    '-hide_banner',
                    '-nostdin',
                    '-loglevel', 'error',
                    '-i', $sourceUrl,
                    '-map', '0:v:0?',
                    '-map', '0:a:0?',
                    '-c:v', 'copy',
                    '-c:a', 'aac',
                    '-b:a', '192k',
                    '-ac', '2',
                    '-ar', '48000',
                    '-f', 'mp4',
                    '-movflags', 'frag_keyframe+empty_moov+default_base_moof',
                    '-',
                ];

                $descriptorspec = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ];

                $process = proc_open($cmd, $descriptorspec, $pipes, null, null, ['bypass_shell' => true]);
                if (! is_resource($process)) {
                    return;
                }

                fclose($pipes[0]);

                $stdout = $pipes[1];
                $stderr = $pipes[2];

                stream_set_blocking($stdout, false);
                stream_set_blocking($stderr, false);

                while (! feof($stdout)) {
                    if (connection_aborted()) {
                        break;
                    }
                    $chunk = fread($stdout, 65536);
                    if ($chunk !== false && $chunk !== '') {
                        echo $chunk;
                        flush();
                    }
                    // drenar stderr para evitar bloqueo
                    stream_get_contents($stderr, 4096);
                    usleep(2000);
                }

                fclose($stdout);
                fclose($stderr);
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

    private function acquireSlot(): bool
    {
        $key = 'ffmpeg_transcode_active';
        $max = max(1, (int) config('streaming.ffmpeg_max_concurrent', 3));
        $v = (int) Cache::increment($key);
        if ($v > $max) {
            Cache::decrement($key);

            return false;
        }

        return true;
    }

    private function releaseSlot(): void
    {
        $key = 'ffmpeg_transcode_active';
        $n = (int) Cache::get($key, 0);
        if ($n > 0) {
            Cache::decrement($key);
        }
    }

    private function binaryPath(): string
    {
        return trim((string) config('streaming.ffmpeg_bin', 'ffmpeg'));
    }

}
