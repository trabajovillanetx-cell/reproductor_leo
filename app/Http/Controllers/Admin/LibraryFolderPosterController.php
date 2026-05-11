<?php



namespace App\Http\Controllers\Admin;



use App\Http\Controllers\Controller;

use App\Models\Content;

use App\Models\LibraryFolderPoster;

use App\Support\StreamingCatalogNav;

use App\Support\StreamingLabel;

use Illuminate\Http\RedirectResponse;

use Illuminate\Http\Request;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Str;

use Illuminate\View\View;



class LibraryFolderPosterController extends Controller

{

    private const UPLOAD_SUBDIR = 'imports/folder-posters';



    public function index(): View

    {

        $this->authorize('viewAny', Content::class);



        $rows = LibraryFolderPoster::query()->orderBy('folder_path')->get();



        return view('admin.library.folder-posters', [

            'rows' => $rows,

            'suggestedFolderPaths' => $this->suggestedCatalogFolderPaths(),

        ]);

    }



    public function store(Request $request): RedirectResponse

    {

        $this->authorize('create', Content::class);



        $data = $request->validate([

            'folder_path' => ['required', 'string', 'max:512'],

            'poster_url' => ['nullable', 'string', 'max:2048'],

            'poster_file' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:12288'],

        ]);



        if (str_contains($data['folder_path'], '..')) {

            return redirect()

                ->route('admin.library.folder-posters.index')

                ->with('error', 'La ruta de carpeta no es válida.');

        }



        $normalized = StreamingLabel::normalizeLibraryPath($data['folder_path']);

        if ($normalized === '') {

            return redirect()

                ->route('admin.library.folder-posters.index')

                ->with('error', 'La ruta de carpeta no puede quedar vacía.');

        }



        $hasFile = $request->hasFile('poster_file');

        $urlInput = trim((string) ($data['poster_url'] ?? ''));



        if (! $hasFile && $urlInput === '') {

            return redirect()

                ->route('admin.library.folder-posters.index')

                ->withErrors(['poster_file' => 'Subí una imagen o pegá una URL de imagen.'])

                ->withInput();

        }



        if (! $hasFile) {

            if (filter_var($urlInput, FILTER_VALIDATE_URL) === false) {

                return redirect()

                    ->route('admin.library.folder-posters.index')

                    ->withErrors(['poster_url' => 'La URL de la imagen no es válida.'])

                    ->withInput();

            }

        }



        $posterUrl = null;

        if ($hasFile) {

            $file = $request->file('poster_file');

            $ext = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg'));

            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {

                $ext = 'jpg';

            }

            $storedName = Str::uuid()->toString().'.'.$ext;

            $storedPath = $file->storeAs(self::UPLOAD_SUBDIR, $storedName, 'public');

            $posterUrl = Storage::disk('public')->url($storedPath);

        } else {

            $posterUrl = $urlInput;

        }



        $existing = LibraryFolderPoster::query()->where('folder_path', $normalized)->first();

        if ($existing !== null) {

            if ($hasFile) {

                $this->deleteStoredPosterFileIfUploaded($existing->poster_url);

            } elseif ($urlInput !== '' && (string) $existing->poster_url !== $urlInput) {

                $this->deleteStoredPosterFileIfUploaded($existing->poster_url);

            }

        }



        LibraryFolderPoster::query()->updateOrCreate(

            ['folder_path' => $normalized],

            ['poster_url' => $posterUrl],

        );



        return redirect()

            ->route('admin.library.folder-posters.index')

            ->with('success', 'Carátula guardada para la carpeta «'.$normalized.'».');

    }



    public function destroy(LibraryFolderPoster $folderPoster): RedirectResponse

    {

        $this->authorize('create', Content::class);



        $path = $folderPoster->folder_path;

        $this->deleteStoredPosterFileIfUploaded($folderPoster->poster_url);

        $folderPoster->delete();



        return redirect()

            ->route('admin.library.folder-posters.index')

            ->with('success', 'Se quitó la carátula manual de «'.$path.'».');

    }



    /**

     * Rutas de primer nivel del catálogo cliente (misma lógica que “Carpetas principales” en Películas/Series/TV).

     *

     * @return Collection<int, string>

     */

    private function suggestedCatalogFolderPaths(): Collection

    {

        $out = collect();

        foreach (['peliculas', 'series', 'tv'] as $section) {

            $base = Content::query()

                ->where('is_active', true)

                ->whereHas('category', fn ($q) => $q->where('is_active', true))

                ->whereNotNull('library_folder')

                ->where('library_folder', '!=', '');

            StreamingCatalogNav::applyCatalogSectionFilter($base, $section);

            foreach (StreamingCatalogNav::promotedRootFolderLibRows(clone $base) as $row) {

                $out->push(StreamingLabel::normalizeLibraryPath($row['lib']));

            }

        }



        return $out->unique()->sort()->values();

    }



    private function deleteStoredPosterFileIfUploaded(?string $posterUrl): void

    {

        if ($posterUrl === null || $posterUrl === '') {

            return;

        }

        $parsed = parse_url($posterUrl, PHP_URL_PATH);

        if (! is_string($parsed) || $parsed === '') {

            return;

        }

        $relative = ltrim($parsed, '/');

        if (! str_starts_with($relative, 'storage/')) {

            return;

        }

        $diskPath = substr($relative, strlen('storage/'));

        if (! str_starts_with($diskPath, self::UPLOAD_SUBDIR.'/')) {

            return;

        }

        Storage::disk('public')->delete($diskPath);

    }

}

