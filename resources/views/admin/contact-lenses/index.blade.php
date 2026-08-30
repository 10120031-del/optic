<x-admin-layout title="Contact Lenses" heading="Contact Lenses">
    <div class="mb-6 flex items-center justify-between gap-4">
        <form method="GET" action="{{ route('admin.contact-lenses.index') }}" class="flex-1 max-w-sm">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search name…') }}" class="input">
        </form>
        <a href="{{ route('admin.contact-lenses.create') }}" class="btn-primary btn-sm whitespace-nowrap">{{ __('New contact lens') }}</a>
    </div>

    @if ($lenses->isEmpty())
        <x-empty-state :title="__('No contact lenses yet')" :description="__('Add your first product to start selling.')">
            <x-slot:action><a href="{{ route('admin.contact-lenses.create') }}" class="btn-primary btn-sm">{{ __('New contact lens') }}</a></x-slot:action>
        </x-empty-state>
    @else
        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Brand') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th class="text-right">{{ __('Price') }}</th>
                        <th class="text-right">{{ __('Stock') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lenses as $lens)
                        <tr>
                            <td class="text-ink">{{ $lens->name }}</td>
                            <td>{{ $lens->brand }}</td>
                            <td>{{ str($lens->type)->headline() }}</td>
                            <td class="text-right font-mono text-ink">${{ number_format($lens->price, 2) }}</td>
                            <td class="text-right font-mono {{ $lens->stock <= 10 ? 'text-warn' : '' }}">{{ $lens->stock }}</td>
                            <td>
                                @if ($lens->is_active)
                                    <span class="badge-signal">{{ __('Active') }}</span>
                                @else
                                    <span class="badge-neutral">{{ __('Hidden') }}</span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('admin.contact-lenses.edit', $lens) }}" class="text-xs text-ink underline hover:no-underline">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.contact-lenses.destroy', $lens) }}" class="ml-3 inline" onsubmit="return confirm('{{ __('Delete this product?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-danger hover:underline">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $lenses->links() }}
    @endif
</x-admin-layout>
