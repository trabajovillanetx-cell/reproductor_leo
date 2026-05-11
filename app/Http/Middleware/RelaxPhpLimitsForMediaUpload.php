<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Subidas de vídeo pueden tardar mucho en llegar al servidor; evita cortes por tiempo de PHP.
 */
class RelaxPhpLimitsForMediaUpload
{
    public function handle(Request $request, Closure $next): Response
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('max_input_time', '3600');

        return $next($request);
    }
}
