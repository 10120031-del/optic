{{--
    A row of recommended products. The collection is mixed on purpose —
    App\Services\Recommender ranks frames and contact lenses against each
    other, so a "customers also bought" rail under a frame can and should
    surface a box of lenses.

    Renders nothing at all when there is nothing to recommend, so a caller
    can pass the recommender's output straight through without guarding it.
--}}
@props([
    'products',
    'title',
    'eyebrow' => null,
    'note' => null,
    'spacing' => 'mt-20',
])

@if ($products->isNotEmpty())
    <section class="hairline-top {{ $spacing }} pt-12">
        <div class="mb-8 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                @if ($eyebrow)
                    <p class="eyebrow">{{ $eyebrow }}</p>
                @endif
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink sm:text-3xl">{{ $title }}</h2>
                @if ($note)
                    <p class="mt-2 max-w-prose text-sm text-ink-soft">{{ $note }}</p>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($products as $product)
                @if ($product instanceof \App\Models\Frame)
                    <x-frame-card :frame="$product" />
                @else
                    <x-contact-lens-card :lens="$product" />
                @endif
            @endforeach
        </div>
    </section>
@endif
