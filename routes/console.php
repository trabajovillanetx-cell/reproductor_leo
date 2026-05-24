<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
use App\Services\FfmpegTranscodeService;

Schedule::call(function () {
    app(FfmpegTranscodeService::class)->cleanupStale();
})->everyFiveMinutes()->name('hls:cleanup')->withoutOverlapping();

// Sincronizar contador FFmpeg con procesos reales cada 5 minutos
Schedule::call(function () {
    $real = (int) shell_exec('ps aux | grep ffmpeg | grep -v grep | wc -l');
    \Illuminate\Support\Facades\Cache::put('ffmpeg_hls_active', max(0, $real), 3600);
})->everyFiveMinutes()->name('ffmpeg:sync-counter');
Schedule::command('library:sync')->everyThirtyMinutes()->name('library:sync');

// Enriquecer carátulas de series nuevas después de cada sync (límite 50 series por pasada)
Schedule::command('content:enrich-series-posters --limit=50 --no-progress')
    ->everyThirtyMinutes()
    ->name('enrich:series-posters')
    ->withoutOverlapping()
    ->runInBackground();
