<x-layout title="Choose a New Password">
    <div class="mx-auto max-w-sm py-8">
        <div class="mb-8 text-center">
            <span class="tick-frame mx-auto flex size-10 items-center justify-center">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="size-5">
                    <circle cx="8" cy="12" r="5.2" /><circle cx="16" cy="12" r="5.2" /><path d="M13.2 12h-2.4" />
                </svg>
            </span>
            <h1 class="mt-4 font-display text-2xl font-semibold text-ink">{{ __('Choose a new password') }}</h1>
            <p class="mt-1 text-sm text-ink-soft">{{ __('Pick something you haven\'t used here before.') }}</p>
        </div>

        <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
            @csrf
            {{-- Both halves come from the emailed link; the token is single-use. --}}
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label class="field-label" for="email">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required readonly class="input bg-wash">
            </div>
            <div>
                <label class="field-label" for="password">{{ __('New password') }}</label>
                <input type="password" id="password" name="password" required autofocus autocomplete="new-password" class="input">
                <p class="mt-1 text-xs text-ink-faint">{{ __('At least 8 characters.') }}</p>
            </div>
            <div>
                <label class="field-label" for="password_confirmation">{{ __('Confirm new password') }}</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" class="input">
            </div>
            <button type="submit" class="btn-accent w-full">{{ __('Save new password') }}</button>
        </form>
    </div>
</x-layout>
