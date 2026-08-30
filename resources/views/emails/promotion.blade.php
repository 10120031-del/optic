<!doctype html>
<html>
<body style="font-family: sans-serif; color: #211d16; max-width: 600px; margin: 0 auto; padding: 24px;">
    <p>{!! nl2br(e($campaign->body)) !!}</p>

    <p style="margin: 24px 0;">
        <a href="{{ url('/') }}" style="background: #2b6e63; color: #fff; padding: 12px 20px; border-radius: 6px; text-decoration: none;">
            Shop now
        </a>
    </p>

    <p style="color: #8b8474; font-size: 12px;">
        You're receiving this because you opted in to promotional email from {{ config('app.name') }}.
    </p>
</body>
</html>
