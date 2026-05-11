<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StreamUrlValidator
{
    public function __construct(
        private LocalMediaService $localMedia
    ) {}

    public function assertValid(string $url): void
    {
        $url = trim($url);

        if ($this->localMedia->isLocalStream($url)) {
            $path = $this->localMedia->absolutePathFromStreamUrl($url);
            if ($path === null || ! $this->localMedia->isAllowedReadableFile($path)) {
                throw ValidationException::withMessages([
                    'stream_url' => 'La ruta local no es válida, no existe, o está fuera de las carpetas permitidas (RAIDRIVE_* o RCLONE_MOUNT_* según LOCAL_LIBRARY_DRIVER, o storage/app/public para subidas del panel).',
                ]);
            }

            return;
        }

        $validator = Validator::make(
            ['stream_url' => $url],
            [
                'stream_url' => ['required', 'url', 'regex:/^https?:\/\//i'],
            ],
            ['stream_url.url' => 'La URL del stream no es válida.']
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $base = rtrim((string) config('streaming.rclone_base_url'), '/');
        if ($base !== '') {
            if (! str_starts_with($url, $base.'/') && $url !== $base) {
                throw ValidationException::withMessages([
                    'stream_url' => 'La URL debe estar bajo el dominio base configurado para medios (RCLONE_BASE_URL).',
                ]);
            }
        }
    }
}
