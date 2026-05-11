<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Plan::class);

        return view('admin.plans.index', [
            'plans' => Plan::query()->orderBy('duration_months')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Plan::class);

        return view('admin.plans.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Plan::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->has('is_active');

        Plan::query()->create($data);

        return redirect()->route('admin.plans.index')->with('success', 'Plan creado.');
    }

    public function edit(Plan $plan): View
    {
        $this->authorize('update', $plan);

        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->has('is_active');

        $plan->update($data);

        return redirect()->route('admin.plans.index')->with('success', 'Plan actualizado.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $plan->delete();

        return redirect()->route('admin.plans.index')->with('success', 'Plan eliminado.');
    }
}
