<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaceShape;
use App\Models\Frame;
use App\Models\FrameImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FrameController extends Controller
{
    private const BASE_RULES = [
        'name' => ['required', 'string', 'max:255'],
        'brand' => ['required', 'string', 'max:255'],
        'manufactured_in' => ['nullable', 'string', 'max:255'],
        'lens_width' => ['required', 'numeric'],
        'lens_height' => ['required', 'numeric'],
        'bridge_width' => ['required', 'numeric'],
        'temple_length' => ['required', 'numeric'],
        'frame_width' => ['nullable', 'numeric'],
        'weight_grams' => ['nullable', 'integer'],
        'size' => ['nullable', 'in:narrow,medium,wide'],
        'description' => ['nullable', 'string'],
        'material' => ['required', 'in:acetate,metal,titanium,plastic,mixed'],
        'category' => ['required', 'in:eyeglasses,sunglasses,sports'],
        'type' => ['required', 'in:full_rim,semi_rimless,rimless'],
        'shape' => ['nullable', 'in:round,square,rectangle,oval,cat_eye,aviator,wayfarer,browline,geometric,hexagonal'],
        'gender' => ['required', 'in:men,women,unisex,kids'],
        'color' => ['nullable', 'string', 'max:255'],
        'color_hex' => ['nullable', 'string', 'max:7'],
        'price' => ['required', 'numeric', 'min:0'],
        'stock' => ['required', 'integer', 'min:0'],
        'is_active' => ['sometimes', 'boolean'],
        'face_shape_ids' => ['sometimes', 'array'],
        'face_shape_ids.*' => ['exists:face_shapes,id'],
        'images' => ['sometimes', 'array', 'max:8'],
        'images.*' => ['image', 'max:8192'],
    ];

    private function rules(?Frame $frame = null): array
    {
        return self::BASE_RULES + [
            'sku' => ['required', 'string', 'max:255', Rule::unique('frames', 'sku')->ignore($frame?->id)],
        ];
    }

    public function index(Request $request): View
    {
        $frames = Frame::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q')->toString().'%')
                ->orWhere('sku', 'like', '%'.$request->string('q')->toString().'%'))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.frames.index', ['frames' => $frames]);
    }

    public function create(): View
    {
        return view('admin.frames.form', ['frame' => new Frame(), 'faceShapes' => FaceShape::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['is_active'] = $request->boolean('is_active', true);

        $frame = Frame::create($data);
        $this->syncFaceShapes($frame, $data);
        $this->storeImages($request, $frame);

        return redirect()->route('admin.frames.edit', $frame)->with('status', 'Frame created.');
    }

    public function edit(Frame $frame): View
    {
        $frame->load(['images', 'faceShapes']);

        return view('admin.frames.form', ['frame' => $frame, 'faceShapes' => FaceShape::orderBy('name')->get()]);
    }

    public function update(Request $request, Frame $frame): RedirectResponse
    {
        $data = $request->validate($this->rules($frame));
        $data['is_active'] = $request->boolean('is_active', false);

        $frame->update($data);
        $this->syncFaceShapes($frame, $data);
        $this->storeImages($request, $frame);

        return redirect()->route('admin.frames.edit', $frame)->with('status', 'Frame updated.');
    }

    public function destroy(Frame $frame): RedirectResponse
    {
        $frame->delete();

        return redirect()->route('admin.frames.index')->with('status', 'Frame deleted.');
    }

    public function destroyImage(Frame $frame, FrameImage $image): RedirectResponse
    {
        abort_unless($image->frame_id === $frame->id, 404);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        // The gallery always shows a primary image first, so hand the badge to
        // the next remaining image when the primary one is the one removed.
        if ($image->is_primary) {
            $frame->images()->first()?->update(['is_primary' => true]);
        }

        return back()->with('status', 'Image removed.');
    }

    private function syncFaceShapes(Frame $frame, array $data): void
    {
        $frame->faceShapes()->sync($data['face_shape_ids'] ?? []);
    }

    private function storeImages(Request $request, Frame $frame): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $hasPrimary = $frame->images()->where('is_primary', true)->exists();
        $nextOrder = (int) $frame->images()->max('sort_order');

        foreach ($request->file('images') as $image) {
            $nextOrder++;

            $frame->images()->create([
                'path' => $image->store('frames', 'public'),
                'is_primary' => ! $hasPrimary,
                'sort_order' => $nextOrder,
            ]);

            $hasPrimary = true;
        }
    }
}
