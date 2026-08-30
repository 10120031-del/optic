<x-admin-layout :title="$feature->exists ? 'Edit Feature' : 'New Feature'" :heading="$feature->exists ? $feature->name : __('New feature')">
    <nav class="mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('admin.lens-features.index') }}" class="hover:text-ink">{{ __('Lens Features') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ $feature->exists ? __('Edit') : __('New') }}</span>
    </nav>

    <form method="POST" action="{{ $feature->exists ? route('admin.lens-features.update', $feature) : route('admin.lens-features.store') }}" class="max-w-lg space-y-6">
        @csrf
        @if ($feature->exists) @method('PUT') @endif

        <div>
            <label class="field-label" for="name">{{ __('Name') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name', $feature->name) }}" required class="input" placeholder="{{ __('e.g. Anti-blue light coating') }}">
        </div>

        <div>
            <label class="field-label" for="description">{{ __('Description') }}</label>
            <textarea id="description" name="description" rows="3" class="textarea">{{ old('description', $feature->description) }}</textarea>
        </div>

        <div>
            <label class="field-label" for="price">{{ __('Add-on price') }}</label>
            <input type="number" step="0.01" min="0" id="price" name="price" value="{{ old('price', $feature->price) }}" required class="input">
        </div>

        <label class="flex items-center gap-2 text-sm text-ink-soft">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $feature->exists ? $feature->is_active : true)) class="checkbox">
            {{ __('Available for selection') }}
        </label>

        <button type="submit" class="btn-primary">{{ $feature->exists ? __('Save changes') : __('Create feature') }}</button>
    </form>
</x-admin-layout>
