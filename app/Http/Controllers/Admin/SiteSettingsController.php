<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteSettingsController extends Controller
{
    public function demoSettings(): View
    {
        $demos = User::query()
            ->where('is_demo', true)
            ->with('parent')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.settings.demo', [
            'demoDurationHours' => (int) SiteSetting::get('demo_duration_hours', '1'),
            'demos'             => $demos,
        ]);
    }

    public function updateDemoSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'demo_duration_hours' => ['required', 'integer', 'min:1', 'max:720'],
        ]);

        SiteSetting::put('demo_duration_hours', (string) $data['demo_duration_hours']);

        return back()->with('success', 'Configuración de demos actualizada.');
    }
}
