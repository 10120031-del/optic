{{--
    Shared shell for error pages.

    Deliberately standalone: no <x-layout>, no @vite, no database. The main
    layout builds a cart (a session + DB round trip) and pulls the Vite
    manifest, so a 500 caused by a downed database — or a 503 raised while
    public/build is mid-swap during a deploy — would fail a second time trying
    to render its own error page. The styling below is inlined from the same
    tokens as resources/css/app.css so these pages still look like the shop.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} — Lucent Optics</title>
    <style>
        :root {
            --paper: #ffffff;
            --ink: #0a0a0a;
            --ink-soft: #55554f;
            --ink-faint: #8c8c85;
            --signal: #0d8f83;
            --hairline: #e6e6e1;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
            padding: 2rem 1.5rem;
            background: var(--paper);
            color: var(--ink);
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            text-align: center;
        }

        .mark {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            font-weight: 600;
            font-size: 0.9375rem;
            letter-spacing: -0.01em;
            color: var(--ink);
            text-decoration: none;
        }

        .code {
            font-family: ui-monospace, 'JetBrains Mono', 'SFMono-Regular', monospace;
            font-size: 0.65rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--signal);
        }

        h1 {
            margin: 0;
            max-width: 30ch;
            font-family: 'Bricolage Grotesque', 'Inter', ui-sans-serif, system-ui, sans-serif;
            font-size: clamp(1.5rem, 4vw, 2rem);
            font-weight: 600;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        p {
            margin: 0;
            max-width: 46ch;
            font-size: 0.9375rem;
            line-height: 1.6;
            color: var(--ink-soft);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
            margin-top: 0.5rem;
        }

        a.btn {
            display: inline-flex;
            align-items: center;
            border-radius: 3px;
            padding: 0.625rem 1.125rem;
            font-size: 0.8125rem;
            text-decoration: none;
            border: 1px solid var(--ink);
            background: var(--ink);
            color: var(--paper);
        }

        a.btn.ghost {
            background: transparent;
            color: var(--ink);
            border-color: var(--hairline);
        }

        a.btn:hover { opacity: 0.85; }

        footer {
            margin-top: 1rem;
            font-size: 0.75rem;
            color: var(--ink-faint);
        }
    </style>
</head>
<body>
    <a class="mark" href="{{ url('/') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="22" height="22" aria-hidden="true">
            <circle cx="8" cy="12" r="5.2" />
            <circle cx="16" cy="12" r="5.2" />
            <path d="M13.2 12h-2.4" />
        </svg>
        Lucent Optics
    </a>

    <p class="code">{{ $code }}</p>

    <h1>{{ $title }}</h1>

    <p>{{ $message }}</p>

    <div class="actions">
        <a class="btn" href="{{ url('/') }}">{{ __('Back to the shop') }}</a>
        @if ($code !== '503')
            <a class="btn ghost" href="{{ url('/frames') }}">{{ __('Browse eyeglasses') }}</a>
        @endif
    </div>

    <footer>&copy; {{ date('Y') }} Lucent Optics</footer>
</body>
</html>
