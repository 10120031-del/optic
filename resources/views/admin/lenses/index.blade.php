<x-admin-layout title="Lens Packages" heading="Lens Packages">
    <div class="mb-6 flex justify-end">
        <a href="{{ route('admin.lenses.create') }}" class="btn-primary btn-sm">{{ __('New lens package') }}</a>
    </div>

    @if ($lenses->isEmpty())
        <x-empty-state :title="__('No lens packages yet')" :description="__('Create the lens options customers can pair with frames.')">
            <x-slot:action><a href="{{ route('admin.lenses.create') }}" class="btn-primary btn-sm">{{ __('New lens package') }}</a></x-slot:action>
        </x-empty-state>
    @else
        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Material') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th class="text-right">{{ __('Price') }}</th>
                        <th class="text-right">{{ __('Features') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lenses as $lens)
                        <tr>
                            <td class="text-ink">{{ $lens->name }}</td>
                            <td>{{ str($lens->material)->headline() }}</td>
                            <td>{{ str($lens->type)->headline() }}</td>
                            <td class="text-right font-mono text-ink">${{ number_format($lens->price, 2) }}</td>
                            <td class="text-right font-mono">{{ $lens->features_count }}</td>
                            <td>
                                @if ($lens->is_active)
                                    <span class="badge-signal">{{ __('Active') }}</span>
                                @else
                                    <span class="badge-neutral">{{ __('Hidden') }}</span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('admin.lenses.edit', $lens) }}" class="text-xs text-ink underline hover:no-underline">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.lenses.destroy', $lens) }}" class="ml-3 inline" onsubmit="return confirm('{{ __('Delete this lens package?') }}')">
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
