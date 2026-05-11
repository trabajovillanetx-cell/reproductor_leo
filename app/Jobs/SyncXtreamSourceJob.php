<?php

namespace App\Jobs;

use App\Enums\ContentType;
use App\Models\Category;
use App\Models\Content;
use App\Models\XtreamSource;
use App\Services\XtreamApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncXtreamSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public int $xtreamSourceId
    ) {}

    public function handle(XtreamApiService $api): void
    {
        $source = XtreamSource::query()->find($this->xtreamSourceId);
        if (! $source || ! $source->is_active) {
            return;
        }

        if ($source->live_category_id) {
            $liveCat = Category::query()->find($source->live_category_id);
            if ($liveCat && $liveCat->type === ContentType::Live) {
                $this->syncLive($api, $source, $liveCat->id);
            }
        }

        if ($source->vod_category_id) {
            $vodCat = Category::query()->find($source->vod_category_id);
            if ($vodCat && $vodCat->type === ContentType::Vod) {
                $this->syncVod($api, $source, $vodCat->id);
            }
        }

        $source->forceFill(['last_synced_at' => now()])->save();
    }

    private function syncLive(XtreamApiService $api, XtreamSource $source, int $categoryId): void
    {
        $streams = $api->getLiveStreams($source, null);
        foreach ($streams as $row) {
            if (! is_array($row)) {
                continue;
            }
            $streamId = $this->normalizeStreamId($row);
            if ($streamId === '') {
                continue;
            }
            $title = trim((string) ($row['name'] ?? $row['title'] ?? 'Canal'));
            $poster = isset($row['stream_icon']) && is_string($row['stream_icon']) ? $row['stream_icon'] : null;

            try {
                Content::query()->updateOrCreate(
                    [
                        'xtream_source_id' => $source->id,
                        'stream_id' => $streamId,
                        'type' => ContentType::Live,
                    ],
                    [
                        'category_id' => $categoryId,
                        'title' => $title !== '' ? $title : 'Canal '.$streamId,
                        'description' => null,
                        'stream_url' => $api->buildLiveUrl($source, $streamId),
                        'poster_url' => $poster,
                        'library_folder' => null,
                        'duration' => null,
                        'is_active' => true,
                        'source_type' => 'xtream',
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Xtream live upsert failed', ['stream_id' => $streamId, 'message' => $e->getMessage()]);
            }
        }
    }

    private function syncVod(XtreamApiService $api, XtreamSource $source, int $categoryId): void
    {
        $streams = $api->getVodStreams($source, null);
        foreach ($streams as $row) {
            if (! is_array($row)) {
                continue;
            }
            $streamId = $this->normalizeStreamId($row);
            if ($streamId === '') {
                continue;
            }
            $title = trim((string) ($row['name'] ?? $row['title'] ?? 'VOD'));
            $poster = isset($row['stream_icon']) && is_string($row['stream_icon']) ? $row['stream_icon'] : null;
            $ext = isset($row['container_extension']) && is_string($row['container_extension'])
                ? $row['container_extension']
                : 'mp4';

            try {
                Content::query()->updateOrCreate(
                    [
                        'xtream_source_id' => $source->id,
                        'stream_id' => $streamId,
                        'type' => ContentType::Vod,
                    ],
                    [
                        'category_id' => $categoryId,
                        'title' => $title !== '' ? $title : 'VOD '.$streamId,
                        'description' => null,
                        'stream_url' => $api->buildVodUrl($source, $streamId, $ext),
                        'poster_url' => $poster,
                        'library_folder' => null,
                        'duration' => isset($row['duration']) && is_numeric($row['duration']) ? (int) $row['duration'] : null,
                        'is_active' => true,
                        'source_type' => 'xtream',
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Xtream VOD upsert failed', ['stream_id' => $streamId, 'message' => $e->getMessage()]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function normalizeStreamId(array $row): string
    {
        foreach (['stream_id', 'id', 'num'] as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $v = $row[$key];
            if (is_int($v) || is_float($v)) {
                return (string) (int) $v;
            }
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        return '';
    }
}
