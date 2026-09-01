<!doctype html>
<html>
<body style="font-family: sans-serif; color: #211d16; max-width: 600px; margin: 0 auto; padding: 24px;">
    <p style="font-family: monospace; font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; color: #8b8474; margin: 0 0 8px;">
        Password reset
    </p>

    <h1 style="font-size: 26px; line-height: 1.2; margin: 0 0 12px;">Hi {{ $user->first_name }},</h1>

    <p style="font-size: 15px; line-height: 1.6; color: #55554f; margin: 0 0 24px;">
        Someone asked to reset the password for the {{ config('app.name') }} account registered to
        {{ $user->email }}. Use the button below to choose a new one.
    </p>

    <p style="margin: 24px 0;">
        <a href="{{ $url }}" style="background: #113631; color: #fff; padding: 12px 20px; border-radius: 6px; text-decoration: none;">
            Reset my password
        </a>
    </p>

    <p style="font-size: 14px; line-height: 1.6; color: #55554f; margin: 0 0 24px;">
        This link expires in {{ $expiresIn }} minutes. If you didn't ask for a reset you can ignore this
        message — nothing changes until the link is used, and your current password still works.
    </p>

    {{--
        Some clients strip or rewrite the button; the plain URL underneath is
        the fallback, and word-break keeps a long signed link from blowing out
        the 600px column.
    --}}
    <p style="color: #8b8474; font-size: 12px; line-height: 1.6; word-break: break-all;">
        If the button doesn't work, paste this into your browser:<br>
        {{ $url }}
    </p>
</body>
</html>
