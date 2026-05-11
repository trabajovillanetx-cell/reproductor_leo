<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncXtreamSourceJob;
use App\Models\Category;
use App\Models\XtreamSource;
use App\Services\XtreamApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class XtreamSourceController extends Controller
{
    public function index(): View
    {
        $this->authorize('create', \App\Models\Content::class);

        return view('admin.xtream.index', [
            'sources' => XtreamSource::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', \App\Models\Content::class);

        return view('admin.xtream.create', [
            'categoryOptions' => Category::orderedTreeOptions(onlyActive: true),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', \App\Models\Content::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:512'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'live_category_id' => ['nullable', 'exists:categories,id'],
            'vod_category_id' => ['nullable', 'exists:categories,id'],
        ]);

        XtreamSource::query()->create([
            'name' => $data['name'],
            'host' => rtrim($data['host'], '/'),
            'username' => $data['username'],
            'password' => $data['password'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'live_category_id' => $data['live_category_id'] ?? null,
            'vod_category_id' => $data['vod_category_id'] ?? null,
        ]);

        return redirect()
            ->route('admin.xtream.index')
            ->with('success', 'Fuente Xtream guardada. Podés probar conexión y sincronizar.');
    }

    public function destroy(XtreamSource $xtreamSource): RedirectResponse
    {
        $this->authorize('create', \App\Models\Content::class);

        $xtreamSource->delete();

        return redirect()
            ->route('admin.xtream.index')
            ->with('success', 'Fuente eliminada. El contenido vinculado se eliminó en cascada.');
    }

    public function sync(XtreamSource $xtreamSource): RedirectResponse
    {
        $this->authorize('create', \App\Models\Content::class);

        if (! $xtreamSource->live_category_id && ! $xtreamSource->vod_category_id) {
            return back()->withErrors(['sync' => 'Definí al menos una categoría destino (TV en vivo y/o VOD) antes de sincronizar.']);
        }

        SyncXtreamSourceJob::dispatch($xtreamSource->id);

        return back()->with('success', 'Sincronización encolada. Si la cola es «sync», termina en esta petición; con «database» revisá el worker.');
    }

    public function test(XtreamSource $xtreamSource, XtreamApiService $api): RedirectResponse
    {
        $this->authorize('create', \App\Models\Content::class);

        $ok = $api->testConnection($xtreamSource->host, $xtreamSource->username, $xtreamSource->password);

        return back()->with(
            $ok ? 'success' : 'error',
            $ok ? 'Conexión Xtream OK (credenciales válidas).' : 'No se pudo autenticar con player_api.php. Revisá host, usuario y contraseña.'
        );
    }
}
