<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Services\LocalMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LibraryFoldersController extends Controller
{
    public function __construct(
        private LocalMediaService $localMedia
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Content::class);

        $parent = $this->normalizeParent($request->query('parent', ''));
        if (str_contains($parent, '..')) {
            abort(400, 'Ruta no válida.');
        }

        $catalogEmpty = Content::query()
            ->whereNotNull('library_folder')
            ->where('library_folder', '!=', '')
            ->doesntExist();

        /** @var Collection<int, Content> $contents */
        $contents = Content::query()
            ->whereNotNull('library_folder')
            ->where('library_folder', '!=', '')
            ->when($parent !== '', function ($q) use ($parent): void {
                $q->where(function ($q2) use ($parent): void {
                    $q2->where('library_folder', $parent)
                        ->orWhere('library_folder', 'like', $parent.'/%');
                });
            })
            ->get(['library_folder', 'type']);

        if ($contents->isEmpty()) {
            return view('admin.library.folders', [
                'parent' => $parent,
                'breadcrumbs' => $this->breadcrumbs($parent),
                'childRows' => collect(),
                'directRow' => null,
                'catalogEmpty' => $catalogEmpty,
                'nothingHere' => ! $catalogEmpty && $parent !== '',
            ]);
        }

        $buckets = [];
        $direct = ['total' => 0, 'vod' => 0, 'series' => 0, 'live' => 0];

        foreach ($contents as $c) {
            $p = $this->normalizePath((string) $c->library_folder);
            if ($p === '') {
                continue;
            }

            $childKey = $this->immediateChildKey($parent, $p);
            if ($childKey === null) {
                if ($parent !== '' && $p === $parent) {
                    $this->incrementTypeBucket($direct, $c->type);
                }

                continue;
            }

            if (! isset($buckets[$childKey])) {
                $buckets[$childKey] = [
                    'path' => $childKey,
                    'label' => $this->labelForChild($childKey),
                    'total' => 0,
                    'vod' => 0,
                    'series' => 0,
                    'live' => 0,
                ];
            }
            $this->incrementTypeBucket($buckets[$childKey], $c->type);
        }

        $childRows = collect($buckets)
            ->map(function (array $row) use ($contents): array {
                $path = $row['path'];
                $row['has_children'] = $contents->contains(function (Content $c) use ($path): bool {
                    $p = trim(str_replace('\\', '/', (string) $c->library_folder), '/');

                    return $p !== $path && str_starts_with($p, $path.'/');
                });

                return $row;
            })
            ->sortBy(fn (array $r) => mb_strtolower($r['label']))
            ->values();

        $directRow = null;
        if ($parent !== '' && $direct['total'] > 0) {
            $directRow = [
                'path' => $parent,
                'label' => 'Contenido en esta carpeta (ruta exacta)',
                'total' => $direct['total'],
                'vod' => $direct['vod'],
                'series' => $direct['series'],
                'live' => $direct['live'],
                'exact_only' => true,
            ];
        }

        return view('admin.library.folders', [
            'parent' => $parent,
            'breadcrumbs' => $this->breadcrumbs($parent),
            'childRows' => $childRows,
            'directRow' => $directRow,
            'catalogEmpty' => false,
            'nothingHere' => false,
        ]);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorize('delete', new Content());

        $data = $request->validate([
            'paths' => ['required', 'array', 'min:1', 'max:200'],
            'paths.*' => ['required', 'string', 'max:512'],
            'return_parent' => ['nullable', 'string', 'max:512'],
        ]);

        $deleted = 0;
        foreach ($data['paths'] as $raw) {
            $path = $this->normalizePath($raw);
            if ($path === '' || str_contains($path, '..')) {
                continue;
            }
            $deleted += Content::query()
                ->where(function ($q) use ($path): void {
                    $q->where('library_folder', $path)
                        ->orWhere('library_folder', 'like', $path.'/%');
                })
                ->delete();
        }

        if ($deleted === 0) {
            return $this->redirectAfterChange($data['return_parent'] ?? null)
                ->with('error', 'No se eliminó ningún ítem (rutas inválidas o vacías).');
        }

        $this->localMedia->bumpRaidriveCacheEpoch();

        return $this->redirectAfterChange($data['return_parent'] ?? null)
            ->with('success', "Se eliminaron {$deleted} ítem(s) del catálogo. No se borraron archivos en el disco.");
    }

    private function redirectAfterChange(?string $returnParent): RedirectResponse
    {
        $rp = $returnParent !== null && $returnParent !== ''
            ? $this->normalizeParent($returnParent)
            : '';

        if ($rp !== '' && str_contains($rp, '..')) {
            $rp = '';
        }

        return $rp === ''
            ? redirect()->route('admin.library.folders.index')
            : redirect()->route('admin.library.folders.index', ['parent' => $rp]);
    }

    /**
     * @return list<array{label: string, path: string}>
     */
    private function breadcrumbs(string $parent): array
    {
        if ($parent === '') {
            return [];
        }
        $parts = explode('/', $parent);
        $out = [];
        $acc = '';
        foreach ($parts as $part) {
            $acc = $acc === '' ? $part : $acc.'/'.$part;
            $out[] = ['label' => $part, 'path' => $acc];
        }

        return $out;
    }

    private function normalizeParent(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return $this->normalizePath($value);
    }

    private function normalizePath(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }

    private function immediateChildKey(string $parent, string $pathNorm): ?string
    {
        if ($parent === '') {
            $seg = explode('/', $pathNorm, 2)[0];

            return $seg !== '' ? $seg : null;
        }
        if ($pathNorm === $parent) {
            return null;
        }
        if (! str_starts_with($pathNorm, $parent.'/')) {
            return null;
        }
        $rel = substr($pathNorm, strlen($parent) + 1);
        $seg = explode('/', $rel, 2)[0];

        return $seg !== '' ? $parent.'/'.$seg : null;
    }

    private function labelForChild(string $childKey): string
    {
        $parts = explode('/', $this->normalizePath($childKey));

        return (string) end($parts);
    }

    /**
     * @param  array{total: int, vod: int, series: int, live: int}  $bucket
     */
    private function incrementTypeBucket(array &$bucket, ContentType $type): void
    {
        $bucket['total']++;
        match ($type) {
            ContentType::Vod => $bucket['vod']++,
            ContentType::Series => $bucket['series']++,
            ContentType::Live => $bucket['live']++,
        };
    }
}
