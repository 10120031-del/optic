<x-admin-layout title="Lens Features" heading="Lens Features">
    <div class="mb-6 flex justify-end">
        <a href="{{ route('admin.lens-features.create') }}" class="btn-primary btn-sm">{{ __('New feature') }}</a>
    </div>

    @if ($features->isEmpty())
        <x-empty-state :title="__('No lens features yet')" :description="__('Features like anti-blue coating or UV-darkening plug into lens packages.')">
            <x-slot:action><a href="{{ route('admin.lens-features.create') }}" class="btn-primary btn-sm">{{ __('New feature') }}</a></x-slot:action>
        </x-empty-state>
    @else
        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th class="text-right">{{ __('Price') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($features as $feature)
                        <tr>
                            <td class="text-ink">{{ $feature->name }}</td>
                            <td class="max-w-xs truncate">{{ $feature->description }}</td>
                            <td class="text-right font-mono text-ink">${{ number_format($feature->price, 2) }}</td>
                            <td>
                                @if ($feature->is_active)
                                    <span class="badge-signal">{{ __('Active') }}</span>
                                @else
                                    <span class="badge-neutral">{{ __('Hidden') }}</span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('admin.lens-features.edit', $feature) }}" class="text-xs text-ink underline hover:no-underline">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.lens-features.destroy', $feature) }}" class="ml-3 inline" onsubmit="return confirm('{{ __('Delete this feature?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-danger hover:underline">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $features->links() }}
    @endif
</x-admin-layout>
