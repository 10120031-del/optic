<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactLens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactLensController extends Controller
{
    private const BASE_RULES = [
        'name' => ['required', 'string', 'max:255'],
        'brand' => ['required', 'string', 'max:255'],
        'type' => ['required', 'in:daily,weekly,biweekly,monthly,yearly'],
        'material' => ['required', 'in:hydrogel,silicone_hydrogel'],
        'color' => ['nullable', 'string', 'max:255'],
        'diameter' => ['nullable', 'numeric'],
        'base_curve' => ['nullable', 'numeric'],
        'pack_size' => ['required', 'integer', 'min:1'],
        'expiry_months' => ['nullable', 'integer', 'min:1'],
        'price' => ['required', 'numeric', 'min:0'],
        'description' => ['nullable', 'string'],
        'stock' => ['required', 'integer', 'min:0'],
        'is_active' => ['sometimes', 'boolean'],
        'image' => ['nullable', 'image', 'max:8192'],
    ];

    public function index(Request $request): View
    {
        $lenses = ContactLens::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q')->toString().'%'))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.contact-lenses.index', ['lenses' => $lenses]);
    }

    public function create(): View
    {
        return view('admin.contact-lenses.form', ['contactLens' => new ContactLens()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('contact-lenses', 'public');
        }

        $contactLens = ContactLens::create($data);

        return redirect()->route('admin.contact-lenses.edit', $contactLens)->with('status', 'Contact lens created.');
    }

    public function edit(ContactLens $contactLens): View
    {
        return view('admin.contact-lenses.form', ['contactLens' => $contactLens]);
    }

    public function update(Request $request, ContactLens $contactLens): RedirectResponse
    {
        $data = $request->validate($this->rules($contactLens));
        $data['is_active'] = $request->boolean('is_active', false);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('contact-lenses', 'public');
        }

        $contactLens->update($data);

        return redirect()->route('admin.contact-lenses.edit', $contactLens)->with('status', 'Contact lens updated.');
    }

    public function destroy(ContactLens $contactLens): RedirectResponse
    {
        $contactLens->delete();

        return redirect()->route('admin.contact-lenses.index')->with('status', 'Contact lens deleted.');
    }

    private function rules(?ContactLens $contactLens = null): array
    {
        return self::BASE_RULES + [
            'sku' => ['required', 'string', 'max:255', Rule::unique('contact_lenses', 'sku')->ignore($contactLens?->id)],
        ];
    }
}
