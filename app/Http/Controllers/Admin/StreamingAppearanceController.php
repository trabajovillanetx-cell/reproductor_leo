<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * @deprecated Rutas redirigen a «Imágenes del sitio» (admin.theme-assets).
 */
class StreamingAppearanceController extends Controller
{
    public function edit(): RedirectResponse
    {
        return redirect()->route('admin.theme-assets.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        return redirect()->route('admin.theme-assets.edit')
            ->with('success', 'Usá la sección «Imágenes del sitio» para subir fondos y logos.');
    }
}
