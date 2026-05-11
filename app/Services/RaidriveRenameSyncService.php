<?php

namespace App\Services;

use App\Models\Content;

/**
 * Reconcilia filas catalog local: tras renombrar un vídeo en RaiDrive, la ruta guardada puede quedar inválida.
 * Si en la MISMA carpeta hay exactamente 1 título catalogado sin archivo en disco y 1 vídeo en disco sin fila catalogada,
 * actualiza ese título para apuntar al archivo renombrado (sin duplicados por URL).
 */
final class RaidriveRenameSyncService
{
    public function __construct(
        private LocalMediaService $localMedia,
        private TmdbPosterService $tmdbPosters,
    ) {}

    /**
     * @return array{
     *   relinked: int,
     *   still_broken: int,
     *   ambiguous: int,
     *   parent_unreachable: int,
     * }
     */
    public function sync(): array
    {
        $stats = [
            'relinked' => 0,
            'still_broken' => 0,
            'ambiguous' => 0,
            'parent_unreachable' => 0,
        ];

        /** @var array<string, true> $urlTaken */
        $urlTaken = array_fill_keys(
            Content::query()
                ->where('stream_url', 'like', LocalMediaService::LOCAL_PREFIX.'%')
                ->pluck('stream_url')
                ->unique()
                ->filter()
                ->values()
                ->all(),
            true
        );

        $brokenByParent = [];

        foreach (Content::query()->where('stream_url', 'like', LocalMediaService::LOCAL_PREFIX.'%')->get() as $content) {
            $abs = $this->localMedia->absolutePathFromStreamUrl((string) $content->stream_url);
            if ($abs === null || $abs === '') {
                continue;
            }

            $absNorm = str_replace('/', DIRECTORY_SEPARATOR, $abs);

            if ($this->localMedia->isAllowedReadableFile($absNorm)) {
                continue;
            }

            $parentDir = dirname($absNorm);
            $parentReal = @realpath($parentDir);

            if ($parentReal === false || ! is_dir($parentReal)) {
                $stats['parent_unreachable']++;

                continue;
            }

            $parentKey = strtolower(str_replace('/', DIRECTORY_SEPARATOR, $parentReal));

            $brokenByParent[$parentKey] ??= [
                'physical' => str_replace('/', DIRECTORY_SEPARATOR, $parentReal),
                'rows' => [],
            ];
            $brokenByParent[$parentKey]['rows'][] = $content;
        }

        foreach ($brokenByParent as $group) {
            $physical = $group['physical'];

            /** @var list<Content> $brokenRows */
            $brokenRows = $group['rows'];
            $b = count($brokenRows);

            $videosOnDisk = $this->localMedia->listImmediateVideoFilesInDirectory($physical);

            $orphans = [];
            foreach ($videosOnDisk as $fileAbs) {
                $url = $this->localMedia->streamUrlForPath($fileAbs);
                if (! isset($urlTaken[$url])) {
                    $orphans[] = $fileAbs;
                }
            }

            if ($b === 1 && count($orphans) === 1) {
                $content = $brokenRows[0];
                $newAbs = $orphans[0];
                $oldUrl = (string) $content->stream_url;
                $newUrl = $this->localMedia->streamUrlForPath($newAbs);

                $title = pathinfo($newAbs, PATHINFO_FILENAME);
                if ($title === '') {
                    $title = 'Sin título';
                }

                $libFolder = $this->localMedia->libraryFolderForAbsoluteFile($newAbs);

                $content->forceFill([
                    'stream_url' => $newUrl,
                    'title' => $title,
                    'library_folder' => $libFolder ?? $content->library_folder,
                    'poster_url' => null,
                ])->save();

                unset($urlTaken[$oldUrl]);
                $urlTaken[$newUrl] = true;
                $stats['relinked']++;

                $this->enrichPosterIfPossible($content->fresh());

                continue;
            }

            if ($b === 1 && count($orphans) === 0) {
                $stats['still_broken']++;
            } elseif ($b >= 1) {
                $stats['ambiguous']++;
            }
        }

        return $stats;
    }

    private function enrichPosterIfPossible(Content $content): void
    {
        if (! $this->tmdbPosters->isConfigured()) {
            return;
        }
        if ($this->tmdbPosters->enrichContentPoster($content)) {
            usleep(max(0, (int) config('services.tmdb.delay_ms_between_requests', 200)) * 1000);
        }
    }
}
