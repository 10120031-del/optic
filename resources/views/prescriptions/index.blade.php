<x-layout title="Prescriptions">
    <div class="mb-8 flex items-end justify-between">
        <div>
            <p class="eyebrow">{{ __('Your account') }}</p>
            <h1 class="mt-1 font-display text-3xl font-semibold text-ink">{{ __('Prescriptions') }}</h1>
        </div>
        <a href="{{ route('prescriptions.create') }}" class="btn-primary btn-sm">{{ __('Add prescription') }}</a>
    </div>

    @if ($prescriptions->isEmpty())
        <x-empty-state :title="__('No prescriptions on file')" :description="__('Save your numbers once and reuse them at checkout.')">
            <x-slot:action>
                <a href="{{ route('prescriptions.create') }}" class="btn-primary btn-sm">{{ __('Add your first prescription') }}</a>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            @foreach ($prescriptions as $prescription)
                <div class="panel p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium text-ink">{{ $prescription->doctor_name ?? __('Unnamed prescription') }}</p>
                            @if ($prescription->clinic_name)
                                <p class="text-xs text-ink-faint">{{ $prescription->clinic_name }}</p>
                            @endif
                        </div>
                        @if ($prescription->is_verified)
                            <span class="badge-signal">{{ __('Verified') }}</span>
                        @else
                            <span class="badge-neutral">{{ __('Unverified') }}</span>
                        @endif
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-1 font-mono text-xs text-ink-soft">
                        <div>OS: {{ $prescription->left_sphere ?? '—' }} / {{ $prescription->left_cylinder ?? '—' }} &times; {{ $prescription->left_axis ?? '—' }}</div>
                        <div>OD: {{ $prescription->right_sphere ?? '—' }} / {{ $prescription->right_cylinder ?? '—' }} &times; {{ $prescription->right_axis ?? '—' }}</div>
                        @if ($prescription->pd)
                            <div>{{ __('PD') }}: {{ $prescription->pd }}</div>
                        @endif
                    </dl>

                    @if ($prescription->expires_at)
                        <p class="mt-2 text-xs {{ $prescription->isExpired() ? 'text-danger' : 'text-ink-faint' }}">
                            {{ $prescription->isExpired() ? __('Expired') : __('Expires') }} {{ $prescription->expires_at->format('M j, Y') }}
                        </p>
                    @endif

                    <div class="hairline-top mt-4 flex items-center justify-between pt-4">
                        @if ($prescription->file_path)
                            <a href="{{ route('prescriptions.file', $prescription) }}" target="_blank" class="text-xs text-ink underline hover:no-underline">{{ __('View file') }}</a>
                        @else
                            <span></span>
                        @endif
                        <form method="POST" action="{{ route('prescriptions.destroy', $prescription) }}" onsubmit="return confirm('{{ __('Remove this prescription?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-danger hover:underline">{{ __('Delete') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layout>
