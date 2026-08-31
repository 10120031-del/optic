<x-admin-layout title="Frames" heading="Frames">
    <div class="mb-6 flex items-center justify-between gap-4">
        <form method="GET" action="{{ route('admin.frames.index') }}" class="flex-1 max-w-sm">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search name or SKU…') }}" class="input">
        </form>
        <a href="{{ route('admin.frames.create') }}" class="btn-primary btn-sm whitespace-nowrap">{{ __('New frame') }}</a>
    </div>

    @if ($frames->isEmpty())
        <x-empty-state :title="__('No frames yet')" :description="__('Add your first frame to start selling.')">
            <x-slot:action><a href="{{ route('admin.frames.create') }}" class="btn-primary btn-sm">{{ __('New frame') }}</a></x-slot:action>
        </x-empty-state>
    @else
        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Brand') }}</th>
                        <th class="text-right">{{ __('Price') }}</th>
                        <th class="text-right">{{ __('Stock') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($frames as $frame)
                        <tr>
                            <td class="text-ink">{{ $frame->name }}</td>
                            <td class="font-mono text-xs">{{ $frame->sku }}</td>
                            <td>{{ $frame->brand }}</td>
                            <td class="text-right font-mono text-ink">${{ number_format($frame->price, 2) }}</td>
                            <td class="text-right font-mono {{ $frame->stock <= \App\Models\Frame::LOW_STOCK_THRESHOLD ? 'text-warn' : '' }}">{{ $frame->stock }}</td>
                            <td>
                                @if ($frame->is_active)
                                    <span class="badge-signal">{{ __('Active') }}</span>
                                @else
                                    <span class="badge-neutral">{{ __('Hidden') }}</span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('admin.frames.edit', $frame) }}" class="text-xs text-ink underline hover:no-underline">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.frames.destroy', $frame) }}" class="ml-3 inline" onsubmit="return confirm('{{ __('Delete this frame?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-danger hover:underline">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $frames->links() }}
    @endif
</x-admin-layout>
