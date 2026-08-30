<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lens;
use App\Models\LensFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LensController extends Controller
{
    private const RULES = [
        'name' => ['required', 'string', 'max:255'],
        'material' => ['required', 'in:plastic,polycarbonate,high_index,trivex,glass'],
        'type' => ['required', 'in:plano,single_vision,bifocal,progressive,reading'],
        'refractive_index' => ['nullable', 'numeric'],
        'price' => ['required', 'numeric', 'min:0'],
        'description' => ['nullable', 'string'],
        'is_active' => ['sometimes', 'boolean'],
        'feature_ids' => ['sometimes', 'array'],
        'feature_ids.*' => ['exists:lens_features,id'],
    ];

    public function index(): View
    {
        return view('admin.lenses.index', ['lenses' => Lens::withCount('features')->orderBy('name')->paginate(25)]);
    }

    public function create(): View
    {
        return view('admin.lenses.form', ['lens' => new Lens(), 'features' => LensFeature::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(self::RULES);
        $data['is_active'] = $request->boolean('is_active', true);

        $lens = Lens::create($data);
        $lens->features()->sync($data['feature_ids'] ?? []);

        return redirect()->route('admin.lenses.edit', $lens)->with('status', 'Lens package created.');
    }

    public function edit(Lens $lens): View
    {
        $lens->load('features');

        return view('admin.lenses.form', ['lens' => $lens, 'features' => LensFeature::orderBy('name')->get()]);
    }

    public function update(Request $request, Lens $lens): RedirectResponse
    {
        $data = $request->validate(self::RULES);
        $data['is_active'] = $request->boolean('is_active', false);

        $lens->update($data);
        $lens->features()->sync($data['feature_ids'] ?? []);

        return redirect()->route('admin.lenses.edit', $lens)->with('status', 'Lens package updated.');
    }

    public function destroy(Lens $lens): RedirectResponse
    {
        $lens->delete();

        return redirect()->route('admin.lenses.index')->with('status', 'Lens package deleted.');
    }
}
