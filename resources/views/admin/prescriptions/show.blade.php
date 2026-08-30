<x-admin-layout title="Prescription" heading="Prescription review">
    <nav class="-mt-4 mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('admin.prescriptions.index') }}" class="hover:text-ink">{{ __('Prescriptions') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">#{{ $prescription->id }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-10 lg:grid-cols-[1fr_320px]">
        <div class="space-y-6">
            <div class="panel p-5">
                <p class="eyebrow">{{ __('Customer') }}</p>
                <p class="mt-1 text-sm text-ink">{{ $prescription->user->first_name }} {{ $prescription->user->last_name }}</p>
                <p class="text-xs text-ink-faint">{{ $prescription->user->email }}</p>
            </div>

            <div class="panel p-5">
                <p class="eyebrow mb-3">{{ __('Prescriber') }}</p>
                <p class="text-sm text-ink">{{ $prescription->doctor_name ?? __('Not provided') }}</p>
                <p class="text-xs text-ink-faint">{{ $prescription->clinic_name }}</p>
                @if ($prescription->issued_at)
                    <p class="mt-2 text-xs text-ink-soft">{{ __('Issued') }} {{ $prescription->issued_at->format('M j, Y') }} @if($prescription->expires_at) &middot; {{ __('Expires') }} {{ $prescription->expires_at->format('M j, Y') }} @endif</p>
                @endif
            </div>

            <div class="panel p-5">
                <p class="eyebrow mb-3">{{ __('Numbers') }}</p>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-1 font-mono text-sm text-ink-soft">
                    <div>OS: {{ $prescription->left_sphere ?? '—' }} / {{ $prescription->left_cylinder ?? '—' }} &times; {{ $prescription->left_axis ?? '—' }} {{ $prescription->left_add ? '+'.$prescription->left_add : '' }}</div>
                    <div>OD: {{ $prescription->right_sphere ?? '—' }} / {{ $prescription->right_cylinder ?? '—' }} &times; {{ $prescription->right_axis ?? '—' }} {{ $prescription->right_add ? '+'.$prescription->right_add : '' }}</div>
                    @if ($prescription->pd)
                        <div>{{ __('PD') }}: {{ $prescription->pd }}</div>
                    @endif
                </dl>
                @if ($prescription->notes)
                    <p class="mt-3 text-sm text-ink-soft">{{ $prescription->notes }}</p>
                @endif
            </div>

            @if ($prescription->file_path)
                <div class="panel p-5">
                    <p class="eyebrow mb-3">{{ __('Uploaded file') }}</p>
                    <a href="{{ Storage::url($prescription->file_path) }}" target="_blank" class="text-sm text-ink underline hover:no-underline">{{ __('Open file') }}</a>
                </div>
            @endif
        </div>

        <div class="panel h-fit p-6">
            <p class="eyebrow mb-3">{{ __('Verification') }}</p>
            @if ($prescription->is_verified)
                <p class="badge-signal">{{ __('Verified') }}</p>
                <p class="mt-3 text-xs text-ink-faint">{{ __('Verified') }} {{ $prescription->verified_at?->format('M j, Y g:ia') }}</p>
            @else
                <p class="mb-4 text-sm text-ink-soft">{{ __('Confirm the numbers above match the uploaded file before verifying.') }}</p>
                <form method="POST" action="{{ route('admin.prescriptions.verify', $prescription) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn-primary btn-sm w-full">{{ __('Mark as verified') }}</button>
                </form>
            @endif
        </div>
    </div>
</x-admin-layout>
