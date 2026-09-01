{{--
    Floating WhatsApp chat launcher.

    Deliberately provider-free: the panel is our own composer, and "Open
    WhatsApp" hands the finished message to https://wa.me/<number>?text=...
    which WhatsApp itself resolves — the mobile app if one is installed,
    web.whatsapp.com otherwise. No token, no webhook, no vendor SDK, and
    nothing to break if the shop changes phones. The trade-off is that the
    conversation lives in the shop's WhatsApp, not on this page.
--}}

@props(['message' => null])

@php
    // Digits only: wa.me rejects "+", spaces and dashes.
    $number = preg_replace('/\D/', '', (string) config('whatsapp.number'));

    // Sent as the opening line. Carrying the current URL saves the customer
    // from describing which frame they are looking at.
    $prefill = $message ?? __('Hi :shop, I have a question about this page: :url', [
        'shop' => config('whatsapp.label'),
        'url' => url()->current(),
    ]);
@endphp

@if (config('whatsapp.enabled') && $number !== '')
<div class="fixed bottom-5 right-5 z-50 flex flex-col items-end gap-3 print:hidden" data-whatsapp>

    <div
        id="whatsapp-panel"
        class="hidden w-[19rem] origin-bottom-right overflow-hidden rounded-[4px] border border-hairline bg-white shadow-[6px_6px_0_var(--color-hairline)] sm:w-[21rem]"
        data-whatsapp-panel
    >
        <div class="flex items-start justify-between gap-3 bg-accent px-4 py-3 text-white">
            <div>
                <p class="font-display text-sm font-semibold">{{ config('whatsapp.label') }}</p>
                <p class="mt-0.5 flex items-center gap-1.5 font-mono text-[10px] uppercase tracking-[0.08em] text-white/70">
                    <span class="size-1.5 animate-pulse-dot rounded-full bg-white"></span>
                    {{ config('whatsapp.hours') }}
                </p>
            </div>
            <button type="button" class="-mr-1 -mt-1 p-1 text-white/70 transition-colors hover:text-white" data-whatsapp-close aria-label="{{ __('Close chat') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="size-4">
                    <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <div class="bg-wash px-4 py-4">
            <p class="max-w-[85%] rounded-[4px] rounded-tl-none border border-hairline bg-white px-3 py-2 text-[13px] leading-relaxed text-ink-soft">
                {{ config('whatsapp.greeting') }}
            </p>
        </div>

        <form class="hairline-top space-y-3 p-4" data-whatsapp-form>
            <label class="field-label" for="whatsapp-message">{{ __('Your message') }}</label>
            <textarea
                id="whatsapp-message"
                rows="3"
                class="textarea text-[13px]"
                data-whatsapp-input
            >{{ $prefill }}</textarea>

            <button type="submit" class="btn-accent w-full !bg-[#25D366] !text-ink hover:!bg-[#1eb457]">
                <svg viewBox="0 0 24 24" fill="currentColor" class="size-4" aria-hidden="true">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.174.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347Z" />
                    <path d="M12.05 2C6.5 2 2 6.5 2 12.05c0 1.77.46 3.5 1.34 5.02L2 22l5.06-1.32a10 10 0 0 0 4.99 1.32h.01c5.55 0 10.05-4.5 10.05-10.05C22.1 6.5 17.6 2 12.05 2Zm0 18.32h-.01a8.3 8.3 0 0 1-4.24-1.16l-.3-.18-3.15.82.84-3.07-.2-.32a8.3 8.3 0 1 1 7.06 3.91Z" />
                </svg>
                {{ __('Open WhatsApp') }}
            </button>

            <p class="text-center font-mono text-[10px] leading-relaxed text-ink-faint">
                {{ __('Opens in WhatsApp. We never see your number until you send.') }}
            </p>
        </form>
    </div>

    <button
        type="button"
        class="flex size-13 items-center justify-center rounded-full bg-[#25D366] text-white shadow-[3px_3px_0_var(--color-ink)] transition-transform duration-150 hover:-translate-y-0.5 active:translate-y-0"
        data-whatsapp-toggle
        aria-controls="whatsapp-panel"
        aria-expanded="false"
        aria-label="{{ __('Chat with us on WhatsApp') }}"
    >
        <svg viewBox="0 0 24 24" fill="currentColor" class="size-7" aria-hidden="true">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.174.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347Z" />
            <path d="M12.05 2C6.5 2 2 6.5 2 12.05c0 1.77.46 3.5 1.34 5.02L2 22l5.06-1.32a10 10 0 0 0 4.99 1.32h.01c5.55 0 10.05-4.5 10.05-10.05C22.1 6.5 17.6 2 12.05 2Zm0 18.32h-.01a8.3 8.3 0 0 1-4.24-1.16l-.3-.18-3.15.82.84-3.07-.2-.32a8.3 8.3 0 1 1 7.06 3.91Z" />
        </svg>
    </button>
</div>

@push('scripts')
<script>
    (function () {
        const root = document.querySelector('[data-whatsapp]');
        if (!root) return;

        const panel = root.querySelector('[data-whatsapp-panel]');
        const toggle = root.querySelector('[data-whatsapp-toggle]');
        const input = root.querySelector('[data-whatsapp-input]');
        const number = @json($number);

        function setOpen(open) {
            panel.classList.toggle('hidden', !open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) input.focus();
        }

        toggle.addEventListener('click', () => setOpen(panel.classList.contains('hidden')));
        root.querySelector('[data-whatsapp-close]').addEventListener('click', () => {
            setOpen(false);
            toggle.focus();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !panel.classList.contains('hidden')) {
                setOpen(false);
                toggle.focus();
            }
        });

        root.querySelector('[data-whatsapp-form]').addEventListener('submit', (e) => {
            e.preventDefault();
            // wa.me hands off to the installed app on mobile and to
            // web.whatsapp.com on desktop — no detection needed on our side.
            const url = 'https://wa.me/' + number + '?text=' + encodeURIComponent(input.value.trim());
            window.open(url, '_blank', 'noopener');
            setOpen(false);
        });
    })();
</script>
@endpush
@endif
