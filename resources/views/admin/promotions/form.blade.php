<x-admin-layout title="New Campaign" heading="New promotional campaign">
    <nav class="-mt-4 mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('admin.promotions.index') }}" class="hover:text-ink">{{ __('Promotions') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ __('New') }}</span>
    </nav>

    <form method="POST" action="{{ route('admin.promotions.store') }}" class="max-w-2xl space-y-6" onsubmit="return confirm('{{ __('Send this campaign now? This cannot be undone.') }}')">
        @csrf

        <div>
            <label class="field-label" for="title">{{ __('Internal title') }}</label>
            <input type="text" id="title" name="title" value="{{ old('title') }}" required class="input" placeholder="{{ __('e.g. Autumn frames sale') }}">
        </div>

        <div>
            <label class="field-label" for="audience">{{ __('Audience') }}</label>
            <select id="audience" name="audience" class="select" required>
                <option value="newsletter_subscribers">{{ __('Newsletter subscribers') }}</option>
                <option value="customers">{{ __('Customers only (subscribed)') }}</option>
                <option value="all">{{ __('All subscribed users') }}</option>
            </select>
        </div>

        <div>
            <label class="field-label" for="subject">{{ __('Email subject') }}</label>
            <input type="text" id="subject" name="subject" value="{{ old('subject') }}" required class="input">
        </div>

        <div>
            <label class="field-label" for="body">{{ __('Email body') }}</label>
            <textarea id="body" name="body" rows="10" required maxlength="10000" class="textarea">{{ old('body') }}</textarea>
            <p class="mt-1.5 text-xs text-ink-faint">{{ __('Plain text, sent as-is inside our email template.') }}</p>
        </div>

        <button type="submit" class="btn-primary">{{ __('Send campaign') }}</button>
    </form>
</x-admin-layout>
