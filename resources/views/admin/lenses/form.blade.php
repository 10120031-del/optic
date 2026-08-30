<x-admin-layout :title="$lens->exists ? 'Edit Lens Package' : 'New Lens Package'" :heading="$lens->exists ? $lens->name : __('New lens package')">
    <nav class="mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('admin.lenses.index') }}" class="hover:text-ink">{{ __('Lens Packages') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ $lens->exists ? __('Edit') : __('New') }}</span>
    </nav>

    <form method="POST" action="{{ $lens->exists ? route('admin.lenses.update', $lens) : route('admin.lenses.store') }}" class="max-w-2xl space-y-8">
        @csrf
        @if ($lens->exists) @method('PUT') @endif

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="field-label" for="name">{{ __('Name') }}</label>
                <input type="text" id="name" name="name" value="{{ old('name', $lens->name) }}" required class="input">
            </div>
            <div>
                <label class="field-label" for="price">{{ __('Price') }}</label>
                <input type="number" step="0.01" min="0" id="price" name="price" value="{{ old('price', $lens->price) }}" required class="input">
            </div>
            <div>
                <label class="field-label" for="material">{{ __('Material') }}</label>
                <select id="material" name="material" class="select" required>
                    @foreach (['plastic', 'polycarbonate', 'high_index', 'trivex', 'glass'] as $option)
                        <option value="{{ $option }}" @selected(old('material', $lens->material) === $option)>{{ str($option)->headline() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label" for="type">{{ __('Type') }}</label>
                <select id="type" name="type" class="select" required>
                    @foreach (['plano', 'single_vision', 'bifocal', 'progressive', 'reading'] as $option)
                        <option value="{{ $option }}" @selected(old('type', $lens->type) === $option)>{{ str($option)->headline() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label" for="refractive_index">{{ __('Refractive index') }}</label>
                <input type="number" step="0.01" id="refractive_index" name="refractive_index" value="{{ old('refractive_index', $lens->refractive_index) }}" class="input">
            </div>
            <label class="mt-6 flex items-center gap-2 text-sm text-ink-soft">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $lens->exists ? $lens->is_active : true)) class="checkbox">
                {{ __('Visible in store') }}
            </label>
        </div>

        <div>
            <label class="field-label" for="description">{{ __('Description') }}</label>
            <textarea id="description" name="description" rows="3" class="textarea">{{ old('description', $lens->description) }}</textarea>
        </div>

        <div class="hairline-top pt-6">
            <p class="eyebrow mb-4">{{ __('Available features') }}</p>
            @if ($features->isEmpty())
                <p class="text-sm text-ink-faint">{{ __('No lens features yet — ') }}<a href="{{ route('admin.lens-features.create') }}" class="underline">{{ __('add one') }}</a>.</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($features as $feature)
                        <label class="flex cursor-pointer items-center gap-2 rounded-full border border-hairline-strong px-3 py-1.5 text-sm has-[:checked]:border-ink has-[:checked]:bg-wash">
                            <input type="checkbox" name="feature_ids[]" value="{{ $feature->id }}" class="checkbox" @checked($lens->relationLoaded('features') && $lens->features->pluck('id')->contains($feature->id))>
                            {{ $feature->name }} <span class="font-mono text-xs text-ink-faint">+${{ number_format($feature->price, 2) }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <button type="submit" class="btn-primary">{{ $lens->exists ? __('Save changes') : __('Create lens package') }}</button>
    </form>
</x-admin-layout>
