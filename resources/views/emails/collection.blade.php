<!doctype html>
<html>
<body style="font-family: sans-serif; color: #211d16; max-width: 600px; margin: 0 auto; padding: 24px;">
    <p style="font-family: monospace; font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; color: #8b8474; margin: 0 0 8px;">
        New collection
    </p>

    <h1 style="font-size: 26px; line-height: 1.2; margin: 0 0 12px;">{{ $collection->name }}</h1>

    @if ($collection->description)
        <p style="font-size: 15px; line-height: 1.6; color: #55554f; margin: 0 0 24px;">
            {{ $collection->description }}
        </p>
    @endif

    @if ($products->isNotEmpty())
        {{--
            Tables, not flexbox: Outlook has no CSS grid and inconsistent
            flex support, so a plain two-column table is the only layout
            that survives every client.
        --}}
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0 0 24px;">
            @foreach ($products->chunk(2) as $row)
                <tr>
                    @foreach ($row as $product)
                        <td width="50%" valign="top" style="padding: 0 8px 16px 0;">
                            <p style="font-family: monospace; font-size: 10px; letter-spacing: 1px; text-transform: uppercase; color: #8b8474; margin: 0 0 4px;">
                                {{ $product->brand }}
                            </p>
                            <p style="font-size: 14px; font-weight: 600; margin: 0 0 4px;">{{ $product->name }}</p>
                            <p style="font-family: monospace; font-size: 13px; color: #113631; margin: 0;">
                                ${{ number_format($product->price, 2) }}
                            </p>
                        </td>
                    @endforeach

                    {{-- Keep the last row's cells the same width when the count is odd. --}}
                    @if ($row->count() === 1)
                        <td width="50%"></td>
                    @endif
                </tr>
            @endforeach
        </table>
    @endif

    <p style="margin: 24px 0;">
        <a href="{{ route('collections.show', $collection) }}" style="background: #113631; color: #fff; padding: 12px 20px; border-radius: 6px; text-decoration: none;">
            See the collection
        </a>
    </p>

    <p style="color: #8b8474; font-size: 12px;">
        You're receiving this because you opted in to promotional email from {{ config('app.name') }}.
    </p>
</body>
</html>
