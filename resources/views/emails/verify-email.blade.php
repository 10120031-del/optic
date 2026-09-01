<!doctype html>
<html>
<body style="font-family: sans-serif; color: #211d16; max-width: 600px; margin: 0 auto; padding: 24px;">
    <p style="font-family: monospace; font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; color: #8b8474; margin: 0 0 8px;">
        Confirm your address
    </p>

    <h1 style="font-size: 26px; line-height: 1.2; margin: 0 0 12px;">Welcome, {{ $user->first_name }}.</h1>

    <p style="font-size: 15px; line-height: 1.6; color: #55554f; margin: 0 0 24px;">
        Confirm {{ $user->email }} so we can send you order confirmations, delivery updates and
        prescription notices.
    </p>

    <p style="margin: 24px 0;">
        <a href="{{ $url }}" style="background: #113631; color: #fff; padding: 12px 20px; border-radius: 6px; text-decoration: none;">
            Confirm my email
        </a>
    </p>

    <p style="font-size: 14px; line-height: 1.6; color: #55554f; margin: 0 0 24px;">
        This link expires in {{ $expiresIn }} minutes. If you didn't create a {{ config('app.name') }}
        account, no further action is needed.
    </p>

    <p style="color: #8b8474; font-size: 12px; line-height: 1.6; word-break: break-all;">
        If the button doesn't work, paste this into your browser:<br>
        {{ $url }}
    </p>
</body>
</html>
