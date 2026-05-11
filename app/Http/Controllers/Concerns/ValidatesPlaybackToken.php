<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Content;
use App\Models\PlaybackToken;
use App\Models\User;
use App\Services\PlaybackTokenService;
use Illuminate\Http\Request;

trait ValidatesPlaybackToken
{
    protected function validatePlaybackToken(Request $request, Content $content, string $token): PlaybackToken
    {
        $record = app(PlaybackTokenService::class)->findValid($token, $content->id);

        if (! $record) {
            abort(403, 'Token inválido o expirado.');
        }

        $user = User::query()->find($record->user_id);

        if (! $user || ! $user->isCustomer()) {
            abort(403);
        }

        if ($record->customer_profile_id !== null) {
            $sessionPid = $request->session()->get('streaming_profile_id');
            $sessionHasNumericProfile = is_numeric($sessionPid);
            if ($sessionHasNumericProfile && (int) $record->customer_profile_id !== (int) $sessionPid) {
                abort(403, 'La reproducción pertenece a otro espacio. Vuelve a abrir el contenido desde tu espacio actual.');
            }
        }

        if (! $user->hasActiveSubscription()) {
            abort(403, 'Suscripción vencida.');
        }

        abort_unless($content->is_active, 403);

        return $record;
    }
}
