<x-layout :title="$faceShape->name.' Match'">
    <nav class="mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('face-match.create') }}" class="hover:text-ink">{{ __('AI Face Match') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ $faceShape->name }}</span>
    </nav>

    <div class="mb-10">
        <p class="eyebrow">{{ $scan ? __('Measured for') : __('Curated for') }}</p>
        <h1 class="mt-1 font-display text-3xl font-semibold text-ink">{{ $faceShape->name }} {{ __('faces') }}</h1>
        @if ($faceShape->description)
            <p class="mt-2 max-w-xl text-sm text-ink-soft">{{ $faceShape->description }}</p>
        @endif
        <a href="{{ route('face-match.create') }}" class="mt-3 inline-block text-xs text-ink-soft underline hover:text-ink">{{ __('Not your shape? Scan again or choose manually') }}</a>
    </div>

    @if ($scan)
        <div class="panel mb-10 p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="eyebrow">{{ __('Your measurements') }}</p>
                <p class="font-mono text-[10.5px] uppercase tracking-[0.06em] text-ink-faint">{{ __('Estimated on your device') }}</p>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4">
                <div>
                    <dt class="text-xs text-ink-faint">{{ __('Pupillary distance') }}</dt>
                    <dd class="font-mono text-lg text-ink">{{ $scan['pd_mm'] }}<span class="text-xs text-ink-faint">mm</span></dd>
                    <dd class="font-mono text-[11px] text-ink-faint">{{ $scan['pd_right_mm'] }} / {{ $scan['pd_left_mm'] }} {{ __('per eye') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-ink-faint">{{ __('Face width') }}</dt>
                    <dd class="font-mono text-lg text-ink">{{ $scan['cheekbone_width_mm'] }}<span class="text-xs text-ink-faint">mm</span></dd>
                </div>
                <div>
                    <dt class="text-xs text-ink-faint">{{ __('Face length') }}</dt>
                    <dd class="font-mono text-lg text-ink">{{ $scan['face_length_mm'] }}<span class="text-xs text-ink-faint">mm</span></dd>
                </div>
                <div>
                    <dt class="text-xs text-ink-faint">{{ __('Jaw width') }}</dt>
                    <dd class="font-mono text-lg text-ink">{{ $scan['jaw_width_mm'] }}<span class="text-xs text-ink-faint">mm</span></dd>
                </div>
            </dl>

            <p class="hairline-top mt-5 pt-4 text-xs leading-relaxed text-ink-soft">
                {{ __('Frames below are ordered by how well they fit you — how closely each frame\'s lens centres line up with your pupils, and how its width compares to yours.') }}
                <span class="text-ink-faint">{{ __('These are estimates from a photo. For lenses that get ground to a prescription, have your PD confirmed by an optician.') }}</span>
            </p>
        </div>
    @endif

    @if ($frames->isEmpty())
        <x-empty-state :title="__('No frames curated for this shape yet')" :description="__('Check back soon, or browse the full catalog.')">
            <x-slot:action>
                <a href="{{ route('frames.index') }}" class="btn-outline btn-sm">{{ __('Browse all frames') }}</a>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-3">
            @foreach ($frames as $frame)
                <div>
                    <x-frame-card :frame="$frame" />

                    @if ($scan)
                        @php
                            // Frame PD (the distance between the lenses'
                            // geometric centres) is lens width + bridge width.
                            // How far it sits from the wearer's own PD is how
                            // far the lenses must be decentered to line up.
                            $framePd = (float) $frame->lens_width + (float) $frame->bridge_width;
                            $offset = abs($framePd - (float) $scan['pd_mm']);
                        @endphp
                        {{-- Under ~3mm the decentration is negligible; past ~6mm it
                             starts to matter optically, more so on a strong Rx. --}}
                        <p class="mt-2 px-3 font-mono text-[10.5px] uppercase tracking-[0.06em]
                            {{ $offset <= 3 ? 'text-signal' : ($offset <= 6 ? 'text-ink-soft' : 'text-ink-faint') }}">
                            @if ($offset <= 3)
                                {{ __('Excellent fit') }}
                            @elseif ($offset <= 6)
                                {{ __('Good fit') }}
                            @else
                                {{ __('Looser fit') }}
                            @endif
                            <span class="normal-case tracking-normal text-ink-faint">
                                · {{ __('frame PD') }} {{ rtrim(rtrim(number_format($framePd, 1), '0'), '.') }}mm
                            </span>
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        {{ $frames->links() }}
    @endif
</x-layout>
