<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LensFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LensFeatureController extends Controller
{
    public function index(): View
    {
        return view('admin.lens-features.index', ['features' => LensFeature::orderBy('name')->paginate(25)]);
    }

    public function create(): View
    {
        return view('admin.lens-features.form', ['feature' => new LensFeature()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', true);

        LensFeature::create($data);

        return redirect()->route('admin.lens-features.index')->with('status', 'Feature created.');
    }

    public function edit(LensFeature $lensFeature): View
    {
        return view('admin.lens-features.form', ['feature' => $lensFeature]);
    }

    public function update(Request $request, LensFeature $lensFeature): RedirectResponse
    {
        $data = $this->validated($request, $lensFeature);
        $data['is_active'] = $request->boolean('is_active', false);

        $lensFeature->update($data);

        return redirect()->route('admin.lens-features.index')->with('status', 'Feature updated.');
    }

    public function destroy(LensFeature $lensFeature): RedirectResponse
    {
        $lensFeature->delete();

        return redirect()->route('admin.lens-features.index')->with('status', 'Feature deleted.');
    }

    private function validated(Request $request, ?LensFeature $feature = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('lens_features', 'name')->ignore($feature?->id)],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $data['slug'] = Str::slug($data['name']);

        return $data;
    }
}
