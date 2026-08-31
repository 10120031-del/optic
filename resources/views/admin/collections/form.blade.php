<x-admin-layout
    :title="$collection->exists ? 'Edit Collection' : 'New Collection'"
    :heading="$collection->exists ? $collection->name : __('New collection')">

    <nav class="mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('admin.collections.index') }}" class="hover:text-ink">{{ __('Collections') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ $collection->exists ? __('Edit') : __('New') }}</span>
    </nav>

    @error('announce')
        <div class="mb-6 border border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">{{ $message }}</div>
    @enderror

    {{--
        The announce panel sits above the form, not inside it: it is a
        separate POST to a separate route, and nesting one form in another is
        invalid HTML. It also reads as what it is — a one-way door, distinct
        from the everyday Save button at the bottom.
    --}}
    @if ($collection->exists && auth()->user()?->isOwner())
        <section class="tick-frame relative mb-10 border border-hairline bg-wash p-6">
            @if ($collection->isAnnounced())
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="eyebrow">{{ __('Announced') }}</p>
                        <p class="mt-1 text-sm text-ink">
                            {{ __('Went out on :date to :count customer(s).', [
                                'date' => $collection->announced_at->format('M j, Y'),
                                'count' => $collection->recipients_count ?? 0,
                            ]) }}
                        </p>
                        <p class="mt-1 text-xs text-ink-faint">
                            {{ __('Edits from here on are silent — customers are only ever told once.') }}
                        </p>
                    </div>
                    <a href="{{ route('collections.show', $collection) }}" class="btn-outline btn-sm">{{ __('View in store') }}</a>
                </div>
            @else
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="eyebrow">{{ __('Not announced yet') }}</p>
                        <h2 class="mt-1 font-display text-lg font-semibold text-ink">{{ __('Declare this a new collection') }}</h2>
                        <p class="mt-1 max-w-lg text-sm text-ink-soft">
                            {{ __('Publishes the collection to the storefront and tells every customer it has dropped — an inbox notification for all of them, plus an e-mail to newsletter subscribers. This cannot be undone, so save your changes first.') }}
                        </p>
                        <p class="mt-2 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
                            {{ trans_choice(':count product|:count products', $collection->itemCount()) }}
                            {{ __('currently in this collection') }}
                        </p>
                    </div>

                    <form method="POST"
                          action="{{ route('admin.collections.announce', $collection) }}"
                          onsubmit="return confirm('{{ __('Announce this collection to every customer? This cannot be undone.') }}')">
                        @csrf
                        <button type="submit" class="btn-accent" @disabled($collection->itemCount() === 0)>
                            {{ __('Announce collection') }}
                        </button>
                    </form>
                </div>
            @endif
        </section>
    @endif

    <form method="POST"
          action="{{ $collection->exists ? route('admin.collections.update', $collection) : route('admin.collections.store') }}"
          enctype="multipart/form-data"
          class="max-w-3xl space-y-10">
        @csrf
        @if ($collection->exists) @method('PUT') @endif

        <section>
            <p class="eyebrow mb-4">{{ __('Basics') }}</p>
            <div>
                <label class="field-label" for="name">{{ __('Name') }}</label>
                <input type="text" id="name" name="name" value="{{ old('name', $collection->name) }}" required class="input" placeholder="{{ __('Autumn 25') }}">
                @if ($collection->exists)
                    <p class="mt-1.5 font-mono text-[11px] text-ink-faint">/collections/{{ $collection->slug }}</p>
                @endif
            </div>
            <div class="mt-4">
                <label class="field-label" for="description">{{ __('Description') }}</label>
                <textarea id="description" name="description" rows="3" class="textarea" placeholder="{{ __('One or two lines — this is what customers read in the announcement.') }}">{{ old('description', $collection->description) }}</textarea>
            </div>
            <label class="mt-4 flex items-center gap-2 text-sm text-ink-soft">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $collection->exists ? $collection->is_active : true)) class="checkbox">
                {{ __('Visible in store once announced') }}
            </label>
        </section>

        <section class="hairline-top pt-8">
            <p class="eyebrow mb-4">{{ __('Cover image') }}</p>
            @if ($collection->cover_image)
                <div class="tick-frame relative mb-4 aspect-[3/1] max-w-md overflow-hidden bg-wash">
                    <img src="{{ Storage::disk('public')->url($collection->cover_image) }}" alt="" class="h-full w-full object-cover">
                </div>
            @endif
            <input type="file" name="cover_image" accept="image/*" class="input !py-1.5 file:mr-3 file:rounded-[3px] file:border-0 file:bg-ink file:px-3 file:py-1.5 file:text-xs file:text-white">
            <p class="mt-1.5 text-xs text-ink-faint">{{ __('Shown as the banner on the collection page. Leave empty to keep the current one.') }}</p>
        </section>

        <section class="hairline-top pt-8">
            <p class="eyebrow mb-4">{{ __('Frames in this collection') }}</p>
            @if ($frames->isEmpty())
                <p class="text-sm text-ink-faint">{{ __('No active frames to choose from.') }}</p>
            @else
                <div class="max-h-72 space-y-1 overflow-y-auto border border-hairline p-3">
                    @foreach ($frames as $frame)
                        <label class="flex cursor-pointer items-center gap-3 px-2 py-1.5 text-sm has-[:checked]:bg-accent-soft">
                            <input type="checkbox" name="frame_ids[]" value="{{ $frame->id }}" class="checkbox"
                                   @checked(in_array($frame->id, old('frame_ids', $selectedFrameIds)))>
                            <span class="flex-1 text-ink">{{ $frame->name }}</span>
                            <span class="font-mono text-[11px] text-ink-faint">{{ $frame->brand }}</span>
                            <span class="font-mono text-[11px] text-accent">${{ number_format($frame->price, 2) }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="hairline-top pt-8">
            <p class="eyebrow mb-4">{{ __('Contact lenses in this collection') }}</p>
            @if ($contactLenses->isEmpty())
                <p class="text-sm text-ink-faint">{{ __('No active contact lenses to choose from.') }}</p>
            @else
                <div class="max-h-72 space-y-1 overflow-y-auto border border-hairline p-3">
                    @foreach ($contactLenses as $lens)
                        <label class="flex cursor-pointer items-center gap-3 px-2 py-1.5 text-sm has-[:checked]:bg-accent-soft">
                            <input type="checkbox" name="contact_lens_ids[]" value="{{ $lens->id }}" class="checkbox"
                                   @checked(in_array($lens->id, old('contact_lens_ids', $selectedLensIds)))>
                            <span class="flex-1 text-ink">{{ $lens->name }}</span>
                            <span class="font-mono text-[11px] text-ink-faint">{{ $lens->brand }}</span>
                            <span class="font-mono text-[11px] text-accent">${{ number_format($lens->price, 2) }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="flex items-center gap-4">
            <button type="submit" class="btn-primary">
                {{ $collection->exists ? __('Save changes') : __('Create collection') }}
            </button>
            @if ($collection->exists)
                <button type="submit" form="delete-collection" class="btn-danger btn-sm">{{ __('Delete') }}</button>
            @endif
        </div>
    </form>

    @if ($collection->exists)
        <form id="delete-collection" method="POST" action="{{ route('admin.collections.destroy', $collection) }}" class="hidden"
              onsubmit="return confirm('{{ __('Delete this collection? The products themselves are not affected.') }}')">
            @csrf @method('DELETE')
        </form>
    @endif
</x-admin-layout>
