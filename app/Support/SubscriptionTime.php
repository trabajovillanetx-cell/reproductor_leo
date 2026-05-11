<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Decide si una fecha de fin de suscripción ya pasó para el cliente,
 * usando la zona horaria de la app y (opcional) “válido hasta el final del día”.
 */
final class SubscriptionTime
{
    public static function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    /**
     * Último instante cubierto por la suscripción (inclusive para el cliente).
     */
    public static function inclusiveEndBoundary(?CarbonInterface $expiresAt): ?CarbonInterface
    {
        if ($expiresAt === null) {
            return null;
        }

        $tz = self::timezone();

        if (config('streaming.subscription_expires_after_end_of_day', true)) {
            return $expiresAt->copy()->timezone($tz)->endOfDay();
        }

        return $expiresAt->copy();
    }

    /** La suscripción por fecha está vencida (sin mirar estado enum). */
    public static function isExpiredByInstant(?CarbonInterface $expiresAt): bool
    {
        if ($expiresAt === null) {
            return true;
        }

        $boundary = self::inclusiveEndBoundary($expiresAt);
        if ($boundary === null) {
            return true;
        }

        return Carbon::now(self::timezone())->isAfter($boundary);
    }

    /** Fecha válida para seguir usando el servicio (solo lado tiempo; no cuenta suspendidos). */
    public static function isDateWindowOpen(?CarbonInterface $expiresAt): bool
    {
        if ($expiresAt === null) {
            return false;
        }

        return ! self::isExpiredByInstant($expiresAt);
    }
}
