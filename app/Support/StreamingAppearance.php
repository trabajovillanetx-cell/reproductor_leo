<?php

namespace App\Support;

final class StreamingAppearance
{
    /**
     * Fondo de la pantalla de elegir espacio (perfil): prioridad BD admin → .env → imagen por defecto.
     */
    public static function profilesPickerBackgroundUrl(): string
    {
        return SiteTheme::profilesPickerBackgroundUrl();
    }
}
