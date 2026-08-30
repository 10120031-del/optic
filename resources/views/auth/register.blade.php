<x-layout title="Create Account">
    <div class="mx-auto max-w-sm py-8">
        <div class="mb-8 text-center">
            <span class="tick-frame mx-auto flex size-10 items-center justify-center">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="size-5">
                    <circle cx="8" cy="12" r="5.2" /><circle cx="16" cy="12" r="5.2" /><path d="M13.2 12h-2.4" />
                </svg>
            </span>
            <h1 class="mt-4 font-display text-2xl font-semibold text-ink">{{ __('Create your account') }}</h1>
            <p class="mt-1 text-sm text-ink-soft">{{ __('Save prescriptions, track orders, and check out faster.') }}</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="field-label" for="first_name">{{ __('First name') }}</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required autofocus class="input">
                </div>
                <div>
                    <label class="field-label" for="last_name">{{ __('Last name') }}</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required class="input">
                </div>
            </div>
            <div>
                <label class="field-label" for="email">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required class="input">
            </div>
            <div>
                <label class="field-label" for="phone_number">{{ __('Phone (optional)') }}</label>
                <input type="tel" id="phone_number" name="phone_number" value="{{ old('phone_number') }}" class="input">
            </div>
            <div>
                <label class="field-label" for="password">{{ __('Password') }}</label>
                <input type="password" id="password" name="password" required class="input">
                <p class="mt-1 text-xs text-ink-faint">{{ __('At least 8 characters.') }}</p>
            </div>
            <div>
                <label class="field-label" for="password_confirmation">{{ __('Confirm password') }}</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required class="input">
            </div>
            <label class="flex items-center gap-2 text-sm text-ink-soft">
                <input type="checkbox" name="newsletter_opt_in" value="1" checked class="checkbox">
                {{ __('Send me offers and new arrivals') }}
            </label>
            <button type="submit" class="btn-accent w-full">{{ __('Create account') }}</button>
        </form>

        <p class="mt-6 text-center text-sm text-ink-soft">
            {{ __('Already have an account?') }} <a href="{{ route('login') }}" class="text-accent underline hover:no-underline">{{ __('Sign in') }}</a>
        </p>
    </div>
</x-layout>
