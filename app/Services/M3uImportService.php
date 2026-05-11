<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Content;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class M3uImportService
{
    public function __construct(
        private StreamReachabilityProbe $reachabilityProbe
    ) {}

    /**
     * Importa desde texto completo (pegar en formulario). Normaliza codificación sobre el bloque entero.
     *
     * @return array{created: int, skipped: int, rejected_unreachable: int, errors: list<string>}
     */
    public function import(string $m3uText, Category $targetCategory, bool $splitByGroupTitle = false, bool $probeStreams = false): array
    {
        $m3uText = $this->normalizeEncodingFull(trim($m3uText));
        // Normalizar saltos de línea Windows (\r\n) y Mac antiguo (\r) a Unix (\n)
        $m3uText = str_replace(["\r\n", "\r"], "\n", $m3uText);
        $lines = explode("\n", $m3uText);

        return $this->importFromLineIterator($this->indexedLinesFromArray($lines), $targetCategory, $splitByGroupTitle, $probeStreams);
    }

    /**
     * Importa desde ruta en disco (archivo subido temporal). Lee línea a línea: sirve para listas M3U grandes sin agotar memoria.
     *
     * @return array{created: int, skipped: int, rejected_unreachable: int, errors: list<string>}
     */
    public function importFromPath(string $absolutePath, Category $targetCategory, bool $splitByGroupTitle = false, bool $probeStreams = false): array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return ['created' => 0, 'skipped' => 0, 'rejected_unreachable' => 0, 'errors' => ['No se pudo leer el archivo.']];
        }

        $fh = fopen($absolutePath, 'rb');
        if ($fh === false) {
            return ['created' => 0, 'skipped' => 0, 'rejected_unreachable' => 0, 'errors' => ['No se pudo abrir el archivo.']];
        }

        return $this->importFromLineIterator(
            $this->indexedLinesFromFileHandle($fh),
            $targetCategory,
            $splitByGroupTitle,
            $probeStreams
        );
    }

    /**
     * @param  iterable<int, string>  $lines  número de línea (1-based) ⇒ contenido
     * @return array{created: int, skipped: int, rejected_unreachable: int, errors: list<string>}
     */
    private function importFromLineIterator(iterable $lines, Category $targetCategory, bool $splitByGroupTitle, bool $probeStreams): array
    {
        $stats = ['created' => 0, 'skipped' => 0, 'rejected_unreachable' => 0, 'errors' => []];

        /** @var list<array{line: int, extinf: array{title: string, group: ?string, logo: ?string}, url: string}> $buffer */
        $buffer = [];

        $pendingExtinf = null;

        $targetCategory->loadMissing('parent');

        $bufferUntil = max(1, (int) config('m3u.probe_buffer_rows', 72));

        if ($probeStreams) {
            $this->reachabilityProbe->resetMemo();
        }

        foreach ($lines as $i => $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#EXTM3U')) {
                continue;
            }

            if (str_starts_with($line, '#EXTINF:')) {
                $pendingExtinf = $this->parseExtinf($line);
                continue;
            }

            if (str_starts_with($line, '#')) {
                continue;
            }

            if (! str_starts_with($line, 'http://') && ! str_starts_with($line, 'https://')) {
                $stats['skipped']++;
                $pendingExtinf = null;

                continue;
            }

            if ($pendingExtinf === null) {
                $stats['errors'][] = 'Línea '.$i.': URL sin #EXTINF previo.';
                $stats['skipped']++;

                continue;
            }

            if ($probeStreams) {
                $buffer[] = ['line' => $i, 'extinf' => $pendingExtinf, 'url' => $line];
                if (count($buffer) >= $bufferUntil) {
                    $this->flushProbativeBuffer($buffer, $targetCategory, $splitByGroupTitle, $stats);
                }
            } else {
                try {
                    $this->persistOneRowDb($pendingExtinf, $line, $targetCategory, $splitByGroupTitle, $stats);
                } catch (\Throwable $e) {
                    $stats['errors'][] = 'Línea '.$i.': '.$e->getMessage();
                    $stats['skipped']++;
                }
            }

            $pendingExtinf = null;
        }

        if ($probeStreams && $buffer !== []) {
            $this->flushProbativeBuffer($buffer, $targetCategory, $splitByGroupTitle, $stats);
        }

        return $stats;
    }

    /**
     * @param  list<array{line: int, extinf: array{title: string, group: ?string, logo: ?string}, url: string}>  $buffer
     * @param  array{created: int, skipped: int, rejected_unreachable: int, errors: list<string>}  $stats
     */
    private function flushProbativeBuffer(array &$buffer, Category $targetCategory, bool $splitByGroupTitle, array &$stats): void
    {
        if ($buffer === []) {
            return;
        }

        $probeMap = $this->reachabilityProbe->evaluateManyDistinct(array_column($buffer, 'url'));

        foreach ($buffer as $row) {
            if (! ($probeMap[$row['url']] ?? false)) {
                $stats['rejected_unreachable']++;
                continue;
            }

            try {
                DB::transaction(function () use (&$stats, $row, $splitByGroupTitle, $targetCategory): void {
                    $contentCategory = $this->resolveCategoryForRow(
                        $targetCategory,
                        $splitByGroupTitle,
                        $row['extinf']['group']
                    );

                    Content::create([
                        'category_id' => $contentCategory->id,
                        'title' => $row['extinf']['title'],
                        'description' => null,
                        'type' => $contentCategory->type->value,
                        'stream_url' => $row['url'],
                        'poster_url' => $row['extinf']['logo'],
                        'duration' => null,
                        'is_active' => true,
                    ]);
                    $stats['created']++;
                });
            } catch (\Throwable $e) {
                $stats['errors'][] = 'Línea '.$row['line'].': '.$e->getMessage();
                $stats['skipped']++;
            }
        }

        $buffer = [];
    }

    /**
     * @param  array{created: int, skipped: int, rejected_unreachable: int, errors: list<string>}  $stats
     */
    private function persistOneRowDb(
        array $pendingExtinf,
        string $line,
        Category $targetCategory,
        bool $splitByGroupTitle,
        array &$stats,
    ): void {
        DB::transaction(function () use ($pendingExtinf, $line, $targetCategory, $splitByGroupTitle, &$stats): void {
            $contentCategory = $this->resolveCategoryForRow(
                $targetCategory,
                $splitByGroupTitle,
                $pendingExtinf['group']
            );

            Content::create([
                'category_id' => $contentCategory->id,
                'title' => $pendingExtinf['title'],
                'description' => null,
                'type' => $contentCategory->type->value,
                'stream_url' => $line,
                'poster_url' => $pendingExtinf['logo'],
                'duration' => null,
                'is_active' => true,
            ]);
            $stats['created']++;
        });
    }

    /**
     * @return \Generator<int, string>
     */
    private function indexedLinesFromArray(array $lines): \Generator
    {
        foreach ($lines as $i => $line) {
            yield ((int) $i) + 1 => $line;
        }
    }

    /**
     * @param  resource  $fh
     * @return \Generator<int, string>
     */
    private function indexedLinesFromFileHandle($fh): \Generator
    {
        $n = 0;
        try {
            while (($line = fgets($fh)) !== false) {
                $n++;
                if ($n === 1) {
                    $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
                }
                $line = $this->normalizeLineEncoding($line);
                yield $n => $line;
            }
        } finally {
            if (is_resource($fh)) {
                fclose($fh);
            }
        }
    }

    private function normalizeEncodingFull(string $raw): string
    {
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $detected = mb_detect_encoding($raw, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($detected !== false && $detected !== 'UTF-8') {
                $raw = mb_convert_encoding($raw, 'UTF-8', $detected);
            }
        }

        return $raw;
    }

    private function normalizeLineEncoding(string $line): string
    {
        if (mb_check_encoding($line, 'UTF-8')) {
            return $line;
        }

        $detected = mb_detect_encoding($line, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);

        if ($detected !== false && $detected !== 'UTF-8') {
            return mb_convert_encoding($line, 'UTF-8', $detected);
        }

        return $line;
    }

    private function resolveCategoryForRow(Category $targetCategory, bool $splitByGroupTitle, ?string $groupTitle): Category
    {
        if (! $splitByGroupTitle) {
            return $targetCategory;
        }

        $name = $groupTitle !== null && trim($groupTitle) !== ''
            ? Str::limit(trim($groupTitle), 255)
            : 'Sin grupo';

        return Category::query()->firstOrCreate(
            [
                'parent_id' => $targetCategory->id,
                'name' => $name,
                'type' => $targetCategory->type->value,
            ],
            ['is_active' => true]
        );
    }

    /**
     * @return array{title: string, group: ?string, logo: ?string}
     */
    private function parseExtinf(string $line): array
    {
        $group = null;
        if (preg_match('/group-title="([^"]*)"/i', $line, $m)) {
            $group = $m[1];
        }

        $logo = null;
        if (preg_match('/tvg-logo="([^"]*)"/i', $line, $m)) {
            $logo = $m[1];
        }

        $titleFromAttr = null;
        if (preg_match('/tvg-name="([^"]*)"/i', $line, $m)) {
            $titleFromAttr = $m[1];
        }

        $commaTitle = null;
        if (preg_match('/,(.+)$/', $line, $m)) {
            $commaTitle = trim($m[1]);
            if (
                $commaTitle !== ''
                && str_starts_with($commaTitle, '"')
                && str_ends_with($commaTitle, '"')
                && mb_strlen($commaTitle) >= 2
            ) {
                $commaTitle = trim(mb_substr($commaTitle, 1, -1));
            }
        }

        $title = $titleFromAttr ?: $commaTitle ?: 'Sin título';

        return [
            'title' => Str::limit($title, 255),
            'group' => $group ? Str::limit($group, 255) : null,
            'logo' => $logo ? Str::limit($logo, 2048) : null,
        ];
    }
}
