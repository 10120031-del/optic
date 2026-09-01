<x-layout title="Confirm Your Email">
    <div class="mx-auto max-w-sm py-8">
        <div class="mb-8 text-center">
            <span class="tick-frame mx-auto flex size-10 items-center justify-center">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="size-5">
                    <path d="M3.5 7.5h17v11h-17z" /><path d="M3.5 8l8.5 6 8.5-6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <h1 class="mt-4 font-display text-2xl font-semibold text-ink">{{ __('Check your inbox') }}</h1>
            <p class="mt-1 text-sm text-ink-soft">
                {{ __('We sent a confirmation link to :email. Click it to finish setting up your account.', ['email' => auth()->user()->email]) }}
            </p>
        </div>

        <div class="space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn-accent w-full">{{ __('Send it again') }}</button>
            </form>

            <a href="{{ route('home') }}" class="btn-ghost w-full">{{ __('Keep browsing for now') }}</a>
        </div>

        <p class="mt-6 text-center text-sm text-ink-soft">
            {{ __('Wrong address, or not seeing it?') }}
            <a href="{{ route('logout') }}"
               class="text-accent underline hover:no-underline"
               onclick="event.preventDefault(); document.getElementById('verify-logout').submit();">{{ __('Sign out') }}</a>
        </p>
        <form id="verify-logout" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
    </div>
</x-layout>
