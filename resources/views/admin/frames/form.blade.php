<x-admin-layout :title="$frame->exists ? 'Edit Frame' : 'New Frame'" :heading="$frame->exists ? $frame->name : __('New frame')">
    <nav class="mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('admin.frames.index') }}" class="hover:text-ink">{{ __('Frames') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ $frame->exists ? __('Edit') : __('New') }}</span>
    </nav>

    <form method="POST" action="{{ $frame->exists ? route('admin.frames.update', $frame) : route('admin.frames.store') }}" enctype="multipart/form-data" class="max-w-3xl space-y-10">
        @csrf
        @if ($frame->exists) @method('PUT') @endif

        <section>
            <p class="eyebrow mb-4">{{ __('Basics') }}</p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="field-label" for="name">{{ __('Name') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $frame->name) }}" required class="input">
                </div>
                <div>
                    <label class="field-label" for="brand">{{ __('Brand') }}</label>
                    <input type="text" id="brand" name="brand" value="{{ old('brand', $frame->brand) }}" required class="input">
                </div>
                <div>
                    <label class="field-label" for="sku">{{ __('SKU') }}</label>
                    <input type="text" id="sku" name="sku" value="{{ old('sku', $frame->sku) }}" required class="input">
                </div>
                <div>
                    <label class="field-label" for="manufactured_in">{{ __('Manufactured in') }}</label>
                    <input type="text" id="manufactured_in" name="manufactured_in" value="{{ old('manufactured_in', $frame->manufactured_in) }}" class="input">
                </div>
            </div>
            <div class="mt-4">
                <label class="field-label" for="description">{{ __('Description') }}</label>
                <textarea id="description" name="description" rows="3" class="textarea">{{ old('description', $frame->description) }}</textarea>
            </div>
        </section>

        <section class="hairline-top pt-8">
            <p class="eyebrow mb-4">{{ __('Classification') }}</p>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                @php
                    $enums = [
                        'material' => ['acetate', 'metal', 'titanium', 'plastic', 'mixed'],
                        'category' => ['eyeglasses', 'sunglasses', 'sports'],
                        'type' => ['full_rim', 'semi_rimless', 'rimless'],
                        'shape' => ['round', 'square', 'rectangle', 'oval', 'cat_eye', 'aviator', 'wayfarer', 'browline', 'geometric', 'hexagonal'],
                        'gender' => ['men', 'women', 'unisex', 'kids'],
                        'size' => ['narrow', 'medium', 'wide'],
                    ];
                @endphp
                @foreach ($enums as $field => $options)
                    <div>
                        <label class="field-label" for="{{ $field }}">{{ str($field)->headline() }}</label>
                        <select id="{{ $field }}" name="{{ $field }}" class="select" @required(in_array($field, ['material', 'category', 'type', 'gender']))>
                            @unless (in_array($field, ['material', 'category', 'type', 'gender']))
                                <option value="">{{ __('—') }}</option>
                            @endunless
                            @foreach ($options as $option)
                                <option value="{{ $option }}" @selected(old($field, $frame->$field) === $option)>{{ str($option)->headline() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div>
                    <label class="field-label" for="color">{{ __('Color') }}</label>
                    <input type="text" id="color" name="color" value="{{ old('color', $frame->color) }}" class="input">
                </div>
                <div>
                    <label class="field-label" for="color_hex">{{ __('Color swatch') }}</label>
                    <input type="color" id="color_hex" name="color_hex" value="{{ old('color_hex', $frame->color_hex ?? '#333333') }}" class="input !h-11 !p-1">
                </div>
            </div>
        </section>

        <section class="hairline-top pt-8">
            <p class="eyebrow mb-4">{{ __('Measurements') }}</p>
            <div class="grid grid-cols-3 gap-4 sm:grid-cols-5">
                <div>
                    <label class="field-label" for="lens_width">{{ __('Lens width') }}</label>
                    <input type="number" step="0.01" id="lens_width" name="lens_width" value="{{ old('lens_width', $frame->lens_width) }}" required class="input">
                </div>
                <div>
                    <label class="field-label" for="lens_height">{{ __('Lens height') }}</label>
                    <input type="number" step="0.01" id="lens_height" name="lens_height" value="{{ old('lens_height', $frame->lens_height) }}" required class="input">
                </div>
                <div>
                    <label class="field-label" for="bridge_width">{{ __('Bridge') }}</label>
                    <input type="number" step="0.01" id="bridge_width" name="bridge_width" value="{{ old('bridge_width', $frame->bridge_width) }}" required class="input">
                </div>
                <div>
                    <label class="field-label" for="temple_length">{{ __('Temple') }}</label>
                    <input type="number" step="0.01" id="temple_length" name="temple_length" value="{{ old('temple_length', $frame->temple_length) }}" required class="input">
                </div>
                <div>
                    <label class="field-label" for="frame_width">{{ __('Frame width') }}</label>
                    <input type="number" step="0.01" id="frame_width" name="frame_width" value="{{ old('frame_width', $frame->frame_width) }}" class="input">
                </div>
                <div>
                    <label class="field-label" for="weight_grams">{{ __('Weight (g)') }}</label>
                    <input type="number" id="weight_grams" name="weight_grams" value="{{ old('weight_grams', $frame->weight_grams) }}" class="input">
                </div>
            </div>
        </section>

        <section class="hairline-top pt-8">
            <p class="eyebrow mb-4">{{ __('Inventory') }}</p>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div>
                    <label class="field-label" for="price">{{ __('Price') }}</label>
                    <input type="number" step="0.01" min="0" id="price" name="price" value="{{ old('price', $frame->price) }}" required class="input">
                </div>
                <div>
                    <label class="field-label" for="stock">{{ __('Stock') }}</label>
                    <input type="number" min="0" id="stock" name="stock" value="{{ old('stock', $frame->stock) }}" required class="input">
                </div>
                <label class="mt-6 flex items-center gap-2 text-sm text-ink-soft">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $frame->exists ? $frame->is_active : true)) class="checkbox">
                    {{ __('Visible in store') }}
                </label>
            </div>
        </section>

        <section class="hairline-top pt-8">
            <p class="eyebrow mb-4">{{ __('Recommended face shapes') }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($faceShapes as $shape)
                    <label class="flex cursor-pointer items-center gap-2 rounded-full border border-hairline-strong px-3 py-1.5 text-sm has-[:checked]:border-ink has-[:checked]:bg-wash">
                        <input type="checkbox" name="face_shape_ids[]" value="{{ $shape->id }}" class="checkbox" @checked($frame->faceShapes->pluck('id')->contains($shape->id))>
                        {{ $shape->name }}
                    </label>
                @endforeach
            </div>
        </section>

        <section class="hairline-top pt-8">
            <p class="eyebrow mb-4">{{ __('Images') }}</p>
            @if ($frame->exists && $frame->images->isNotEmpty())
                <div class="mb-4 grid grid-cols-4 gap-3 sm:grid-cols-6">
                    @foreach ($frame->images as $image)
                        <div class="tick-frame relative aspect-square overflow-hidden bg-wash">
                            <img src="{{ Storage::disk('public')->url($image->path) }}" alt="" class="h-full w-full object-cover">
                            @if ($image->is_primary)
                                <span class="badge-signal absolute left-1 top-1 bg-white !text-[9px]">{{ __('Primary') }}</span>
                            @endif
                            <button type="submit" form="delete-image-{{ $image->id }}"
                                title="{{ __('Remove image') }}"
                                class="absolute right-1 top-1 flex size-5 items-center justify-center rounded-full bg-white/90 text-danger shadow-sm transition-colors hover:bg-danger hover:text-white">
                                <span class="sr-only">{{ __('Remove image') }}</span>
                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" /></svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
            <input type="file" name="images[]" accept="image/*" multiple class="input !py-1.5 file:mr-3 file:rounded-[3px] file:border-0 file:bg-ink file:px-3 file:py-1.5 file:text-xs file:text-white">
            <p class="mt-1.5 text-xs text-ink-faint">{{ __('Newly uploaded images are added to the gallery; the first image ever uploaded becomes primary.') }}</p>
        </section>

        <button type="submit" class="btn-primary">{{ $frame->exists ? __('Save changes') : __('Create frame') }}</button>
    </form>

    @if ($frame->exists)
        @foreach ($frame->images as $image)
            <form id="delete-image-{{ $image->id }}" method="POST" action="{{ route('admin.frames.images.destroy', [$frame, $image]) }}" class="hidden" onsubmit="return confirm('{{ __('Remove this image?') }}')">
                @csrf @method('DELETE')
            </form>
        @endforeach
    @endif
</x-admin-layout>
