<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ChannelStreamDiagnosticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChannelDiagnosticsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('create', \App\Models\Content::class);

        return view('admin.diagnostics.channels', [
            'result' => $request->session()->get('diag_result'),
        ]);
    }

    public function diagnose(Request $request, ChannelStreamDiagnosticsService $diagnostics): RedirectResponse
    {
        $this->authorize('create', \App\Models\Content::class);

        $data = $request->validate([
            'url' => ['required', 'string', 'max:4000'],
        ]);

        return redirect()
            ->route('admin.diagnostics.channels')
            ->with('diag_result', $diagnostics->diagnose($data['url']));
    }
}
