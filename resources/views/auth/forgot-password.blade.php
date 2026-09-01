<x-layout title="Reset Password">
    <div class="mx-auto max-w-sm py-8">
        <div class="mb-8 text-center">
            <span class="tick-frame mx-auto flex size-10 items-center justify-center">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="size-5">
                    <circle cx="8" cy="12" r="5.2" /><circle cx="16" cy="12" r="5.2" /><path d="M13.2 12h-2.4" />
                </svg>
            </span>
            <h1 class="mt-4 font-display text-2xl font-semibold text-ink">{{ __('Forgot your password?') }}</h1>
            <p class="mt-1 text-sm text-ink-soft">{{ __('Give us the address on your account and we\'ll email you a link to set a new password.') }}</p>
        </div>

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf
            <div>
                <label class="field-label" for="email">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus class="input">
            </div>
            <button type="submit" class="btn-accent w-full">{{ __('Email me a reset link') }}</button>
        </form>

        <p class="mt-6 text-center text-sm text-ink-soft">
            {{ __('Remembered it?') }} <a href="{{ route('login') }}" class="text-accent underline hover:no-underline">{{ __('Back to sign in') }}</a>
        </p>
    </div>
</x-layout>
