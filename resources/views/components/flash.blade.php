@if (session('status'))
    <div class="panel border-signal/30 bg-signal-soft mb-6 flex items-start gap-3 px-4 py-3" role="status">
        <svg class="mt-0.5 size-4 shrink-0 text-signal" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
            <circle cx="10" cy="10" r="7.5" />
            <path d="M6.8 10.2l2.1 2.1 4.3-4.6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <p class="text-sm text-ink">{{ session('status') }}</p>
    </div>
@endif

@if ($errors->any())
    <div class="panel border-danger/30 bg-danger-soft mb-6 flex items-start gap-3 px-4 py-3" role="alert">
        <svg class="mt-0.5 size-4 shrink-0 text-danger" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
            <circle cx="10" cy="10" r="7.5" />
            <path d="M10 6v4.5" stroke-linecap="round" />
            <circle cx="10" cy="13.4" r="0.6" fill="currentColor" stroke="none" />
        </svg>
        <div class="text-sm text-ink">
            <p class="font-medium">{{ __('Please fix the following:') }}</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-4 text-ink-soft">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
