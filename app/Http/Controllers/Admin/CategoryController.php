<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Category::class);

        return view('admin.categories.index', [
            'categories' => Category::query()
                ->with('parent')
                ->orderByRaw('parent_id is null desc')
                ->orderBy('name')
                ->paginate(30),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('admin.categories.create', [
            'types' => ContentType::cases(),
            'parentOptions' => Category::orderedTreeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ContentType::class)],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->has('is_active');
        $data['parent_id'] = $data['parent_id'] ?? null;

        if ($data['parent_id'] !== null) {
            $parent = Category::query()->findOrFail((int) $data['parent_id']);
            if ($parent->type->value !== $data['type']) {
                return back()
                    ->withErrors(['parent_id' => 'El tipo debe ser el mismo que la carpeta padre (ej. VOD dentro de VOD).'])
                    ->withInput();
            }
        }

        Category::query()->create($data);

        return redirect()->route('admin.categories.index')->with('success', 'Categoría creada.');
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        $forbiddenParentIds = $category->descendantIdsIncludingSelf();

        $parentOptions = Category::orderedTreeOptions()->filter(function (array $row) use ($forbiddenParentIds): bool {
            return ! in_array($row['id'], $forbiddenParentIds, true);
        });

        return view('admin.categories.edit', [
            'category' => $category,
            'types' => ContentType::cases(),
            'parentOptions' => $parentOptions,
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ContentType::class)],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->has('is_active');
        $data['parent_id'] = $data['parent_id'] ?? null;

        if ($data['parent_id'] !== null) {
            $parent = Category::query()->findOrFail((int) $data['parent_id']);
            if ($parent->type->value !== $data['type']) {
                return back()
                    ->withErrors(['parent_id' => 'El tipo debe coincidir con la carpeta padre.'])
                    ->withInput();
            }

            $forbidden = $category->descendantIdsIncludingSelf();
            if (in_array((int) $data['parent_id'], $forbidden, true)) {
                return back()
                    ->withErrors(['parent_id' => 'No puedes colocar una categoría dentro de sí misma o de sus subcarpetas.'])
                    ->withInput();
            }
        }

        $category->update($data);

        return redirect()->route('admin.categories.index')->with('success', 'Categoría actualizada.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->children()->exists()) {
            return back()->with('error', 'Elimina o mueve primero las subcarpetas de esta categoría.');
        }

        if ($category->contents()->exists()) {
            return back()->with('error', 'Hay contenido en esta categoría; reasígnalo o elimínalo antes.');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Categoría eliminada.');
    }
}
