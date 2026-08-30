@props(['status'])

@php
    $signal = ['delivered', 'paid', 'completed', 'approved', 'verified', 'exchanged', 'item_received'];
    $warn = ['pending', 'processing', 'shipped', 'requested'];
    $danger = ['cancelled', 'rejected', 'refunded', 'failed'];

    $variant = match (true) {
        in_array($status, $signal, true) => 'badge-signal',
        in_array($status, $warn, true) => 'badge-warn',
        in_array($status, $danger, true) => 'badge-danger',
        default => 'badge-neutral',
    };
@endphp

<span class="{{ $variant }}">
    <span class="size-1.5 rounded-full bg-current"></span>
    {{ str($status)->replace('_', ' ') }}
</span>
