<x-layout title="Sign In">
    <div class="mx-auto max-w-sm py-8">
        <div class="mb-8 text-center">
            <span class="tick-frame mx-auto flex size-10 items-center justify-center">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="size-5">
                    <circle cx="8" cy="12" r="5.2" /><circle cx="16" cy="12" r="5.2" /><path d="M13.2 12h-2.4" />
                </svg>
            </span>
            <h1 class="mt-4 font-display text-2xl font-semibold text-ink">{{ __('Welcome back') }}</h1>
            <p class="mt-1 text-sm text-ink-soft">{{ __('Sign in to track orders and check out faster.') }}</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label class="field-label" for="email">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus class="input">
            </div>
            <div>
                <label class="field-label" for="password">{{ __('Password') }}</label>
                <input type="password" id="password" name="password" required class="input">
            </div>
            <div class="flex items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-sm text-ink-soft">
                    <input type="checkbox" name="remember" class="checkbox">
                    {{ __('Keep me signed in') }}
                </label>
                <a href="{{ route('password.request') }}" class="text-sm text-ink-soft underline hover:text-ink hover:no-underline">{{ __('Forgot password?') }}</a>
            </div>
            <button type="submit" class="btn-accent w-full">{{ __('Sign in') }}</button>
        </form>

        <p class="mt-6 text-center text-sm text-ink-soft">
            {{ __('New here?') }} <a href="{{ route('register') }}" class="text-accent underline hover:no-underline">{{ __('Create an account') }}</a>
        </p>
    </div>
</x-layout>
