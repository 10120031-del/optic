<x-admin-layout :title="$contactLens->exists ? 'Edit Contact Lens' : 'New Contact Lens'" :heading="$contactLens->exists ? $contactLens->name : __('New contact lens')">
    <nav class="mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('admin.contact-lenses.index') }}" class="hover:text-ink">{{ __('Contact Lenses') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ $contactLens->exists ? __('Edit') : __('New') }}</span>
    </nav>

    <form method="POST" action="{{ $contactLens->exists ? route('admin.contact-lenses.update', $contactLens) : route('admin.contact-lenses.store') }}" enctype="multipart/form-data" class="max-w-2xl space-y-8">
        @csrf
        @if ($contactLens->exists) @method('PUT') @endif

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="field-label" for="name">{{ __('Name') }}</label>
                <input type="text" id="name" name="name" value="{{ old('name', $contactLens->name) }}" required class="input">
            </div>
            <div>
                <label class="field-label" for="brand">{{ __('Brand') }}</label>
                <input type="text" id="brand" name="brand" value="{{ old('brand', $contactLens->brand) }}" required class="input">
            </div>
            <div>
                <label class="field-label" for="sku">{{ __('SKU') }}</label>
                <input type="text" id="sku" name="sku" value="{{ old('sku', $contactLens->sku) }}" required class="input">
            </div>
            <div>
                <label class="field-label" for="type">{{ __('Wear schedule') }}</label>
                <select id="type" name="type" class="select" required>
                    @foreach (['daily', 'weekly', 'biweekly', 'monthly', 'yearly'] as $option)
                        <option value="{{ $option }}" @selected(old('type', $contactLens->type) === $option)>{{ str($option)->headline() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label" for="material">{{ __('Material') }}</label>
                <select id="material" name="material" class="select" required>
                    <option value="hydrogel" @selected(old('material', $contactLens->material) === 'hydrogel')>{{ __('Hydrogel') }}</option>
                    <option value="silicone_hydrogel" @selected(old('material', $contactLens->material) === 'silicone_hydrogel')>{{ __('Silicone Hydrogel') }}</option>
                </select>
            </div>
            <div>
                <label class="field-label" for="color">{{ __('Color') }}</label>
                <input type="text" id="color" name="color" value="{{ old('color', $contactLens->color) }}" class="input">
            </div>
            <div>
                <label class="field-label" for="diameter">{{ __('Diameter (mm)') }}</label>
                <input type="number" step="0.01" id="diameter" name="diameter" value="{{ old('diameter', $contactLens->diameter) }}" class="input">
            </div>
            <div>
                <label class="field-label" for="base_curve">{{ __('Base curve (mm)') }}</label>
                <input type="number" step="0.01" id="base_curve" name="base_curve" value="{{ old('base_curve', $contactLens->base_curve) }}" class="input">
            </div>
            <div>
                <label class="field-label" for="pack_size">{{ __('Pack size') }}</label>
                <input type="number" min="1" id="pack_size" name="pack_size" value="{{ old('pack_size', $contactLens->pack_size) }}" required class="input">
            </div>
            <div>
                <label class="field-label" for="expiry_months">{{ __('Wear expiry (months)') }}</label>
                <input type="number" min="1" id="expiry_months" name="expiry_months" value="{{ old('expiry_months', $contactLens->expiry_months) }}" class="input">
            </div>
            <div>
                <label class="field-label" for="price">{{ __('Price') }}</label>
                <input type="number" step="0.01" min="0" id="price" name="price" value="{{ old('price', $contactLens->price) }}" required class="input">
            </div>
            <div>
                <label class="field-label" for="stock">{{ __('Stock') }}</label>
                <input type="number" min="0" id="stock" name="stock" value="{{ old('stock', $contactLens->stock) }}" required class="input">
            </div>
        </div>

        <div>
            <label class="field-label" for="description">{{ __('Description') }}</label>
            <textarea id="description" name="description" rows="3" class="textarea">{{ old('description', $contactLens->description) }}</textarea>
        </div>

        @if ($contactLens->exists && $contactLens->image_path)
            <div class="tick-frame size-24 overflow-hidden bg-wash">
                <img src="{{ Storage::disk('public')->url($contactLens->image_path) }}" alt="" class="h-full w-full object-cover">
            </div>
        @endif
        <div>
            <label class="field-label" for="image">{{ __('Image') }}</label>
            <input type="file" id="image" name="image" accept="image/*" class="input !py-1.5 file:mr-3 file:rounded-[3px] file:border-0 file:bg-ink file:px-3 file:py-1.5 file:text-xs file:text-white">
        </div>

        <label class="flex items-center gap-2 text-sm text-ink-soft">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $contactLens->exists ? $contactLens->is_active : true)) class="checkbox">
            {{ __('Visible in store') }}
        </label>

        <button type="submit" class="btn-primary">{{ $contactLens->exists ? __('Save changes') : __('Create contact lens') }}</button>
    </form>
</x-admin-layout>
