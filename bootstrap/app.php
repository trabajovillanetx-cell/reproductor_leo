<?php

use App\Http\Middleware\CheckActiveSubscription;
use App\Http\Middleware\CheckConcurrentSessions;
use App\Http\Middleware\EnsureStreamingProfileSelected;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\RelaxPhpLimitsForMediaUpload;
use App\Models\PlaybackToken;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserRole::class,
            'subscription.active' => CheckActiveSubscription::class,
            'relax.upload' => RelaxPhpLimitsForMediaUpload::class,
            'streaming.profile' => EnsureStreamingProfileSelected::class,
            'concurrent.sessions' => CheckConcurrentSessions::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('sessions:prune')->everyFifteenMinutes()->withoutOverlapping(5);

        $schedule->command('model:prune', [
            '--model' => PlaybackToken::class,
        ])->hourly();

        // Barrido automático de canales muertos — por defecto cada 6 horas
        // Configurable con CULL_DEAD_STREAMS_SCHEDULE en .env (cron expression o 'disabled')
        $cullSchedule = env('CULL_DEAD_STREAMS_SCHEDULE', 'everysixhours');
        if ($cullSchedule !== 'disabled' && $cullSchedule !== '') {
            $cmd = $schedule->command('content:cull-unreachable-remotes');
            match ($cullSchedule) {
                'hourly' => $cmd->hourly(),
                'everytwohours' => $cmd->everyTwoHours(),
                'everyfourhours' => $cmd->everyFourHours(),
                'everytwelvehours' => $cmd->everyTwelveHours(),
                'daily' => $cmd->dailyAt('03:00'),
                'weekly' => $cmd->weekly(),
                default => $cmd->everyFourHours(), // fallback seguro
            };
            $cmd->withoutOverlapping(120)->runInBackground()->onFailure(function () {
                Log::warning('Scheduled cull-unreachable-remotes falló o se solapó.');
            });
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (PostTooLargeException $e, Request $request) {
            if ($request->is('admin/m3u/import')) {
                return redirect()
                    ->route('admin.m3u.import')
                    ->withErrors([
                        'm3u_file' => 'El tamaño supera los límites de PHP (post_max_size / upload_max_filesize). En Laragon: Menú PHP > php.ini, sube ambos valores (por ejemplo 512M), guarda y reinicia Apache. Luego vuelve a subir el archivo.',
                    ])
                    ->withInput();
            }

            return null;
        });
    })->create();
