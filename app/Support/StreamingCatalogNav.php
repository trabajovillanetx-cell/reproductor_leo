<?php

namespace App\Support;

use App\Enums\ContentType;
use Illuminate\Database\Eloquent\Builder;

final class StreamingCatalogNav
{
    public static function applyCatalogSectionFilter(Builder $query, string $section): void
    {
        if ($section === 'peliculas') {
            $query->where('type', ContentType::Vod);

            return;
        }
        if ($section === 'series') {
            $query->where('type', ContentType::Series);

            return;
        }
        if ($section === 'tv') {
            $query->where('type', ContentType::Live);

            return;
        }
        if ($section === 'estrenos') {
            $query->where('created_at', '>=', now()->subDays(60));
        }
    }

    /**
     * @return list<array{label: string, lib: string}>
     */
    public static function libraryFolderNav(Builder $baseQuery, string $lib): array
    {
        $lib = trim(str_replace('\\', '/', $lib), '/');

        $map = [];

        $q = clone $baseQuery;
        $q->setEagerLoads([]);
        $q->reorder('id');
        $q->whereNotNull('library_folder');
        $q->where('library_folder', '!=', '');

        if ($lib !== '') {
            $likePrefix = str_replace(['%', '_'], ['\%', '\_'], $lib).'/';
            $q->where('library_folder', 'like', $likePrefix.'%');
        }

        foreach ($q->cursor() as $row) {
            $lf = trim(str_replace('\\', '/', (string) $row->library_folder), '/');
            if ($lf === '') {
                continue;
            }

            if ($lib === '') {
                $seg = explode('/', $lf, 2)[0];
                if ($seg !== '') {
                    $map[$seg] = ['label' => StreamingLabel::decode($seg), 'lib' => $seg];
                }

                continue;
            }

            if ($lf === $lib) {
                continue;
            }

            if (! str_starts_with($lf, $lib.'/')) {
                continue;
            }

            $rest = substr($lf, strlen($lib) + 1);
            $seg = explode('/', $rest, 2)[0];
            if ($seg !== '') {
                $childLib = $lib.'/'.$seg;
                $map[$childLib] = ['label' => StreamingLabel::decode($seg), 'lib' => $childLib];
            }
        }

        $out = array_values($map);
        usort($out, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        return $out;
    }

    /**
     * Primera rejilla de carpetas del cliente con `lib` vacío: si en un nivel solo hay un prefijo,
     * se “baja” automáticamente (misma lógica que el catálogo en Películas/Series/TV con `lib` vacío).
     *
     * @return list<array{label: string, lib: string}>
     */
    public static function promotedRootFolderLibRows(Builder $navBase): array
    {
        $folderBrowseRows = self::libraryFolderNav(clone $navBase, '');

        for ($i = 0; $i < 32 && count($folderBrowseRows) === 1; $i++) {
            $soleLib = $folderBrowseRows[0]['lib'];
            $children = self::libraryFolderNav(clone $navBase, $soleLib);
            if ($children === []) {
                break;
            }
            $folderBrowseRows = $children;
        }

        return $folderBrowseRows;
    }
}
