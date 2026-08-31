<x-admin-layout title="Collections" :heading="__('Collections')">
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
        <p class="max-w-xl text-sm text-ink-soft">
            {{ __('Group frames and contact lenses into a named drop. Nothing is published and nobody is notified until you announce it.') }}
        </p>
        <a href="{{ route('admin.collections.create') }}" class="btn-primary btn-sm">{{ __('New collection') }}</a>
    </div>

    @if ($collections->isEmpty())
        <x-empty-state
            :title="__('No collections yet')"
            :description="__('Create one, add the products that belong to it, then announce it to your customers.')">
            <x-slot:action>
                <a href="{{ route('admin.collections.create') }}" class="btn-primary btn-sm">{{ __('New collection') }}</a>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Products') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Announced') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($collections as $collection)
                        <tr>
                            <td>
                                <a href="{{ route('admin.collections.edit', $collection) }}" class="font-medium text-ink hover:text-accent">
                                    {{ $collection->name }}
                                </a>
                                <span class="block font-mono text-[10.5px] text-ink-faint">/{{ $collection->slug }}</span>
                            </td>
                            <td class="font-mono text-xs">
                                {{ $collection->frames_count }} {{ __('frames') }},
                                {{ $collection->contact_lenses_count }} {{ __('lenses') }}
                            </td>
                            <td>
                                @if (! $collection->is_active)
                                    <span class="badge-neutral">{{ __('Hidden') }}</span>
                                @elseif ($collection->isAnnounced())
                                    <span class="badge-signal">{{ __('Live') }}</span>
                                @else
                                    <span class="badge-warn">{{ __('Draft') }}</span>
                                @endif
                            </td>
                            <td class="font-mono text-xs">
                                @if ($collection->isAnnounced())
                                    {{ $collection->announced_at->format('M j, Y') }}
                                    <span class="block text-ink-faint">
                                        {{ trans_choice(':count customer|:count customers', $collection->recipients_count ?? 0) }}
                                    </span>
                                @else
                                    <span class="text-ink-faint">{{ __('Not yet') }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($collection->isAnnounced())
                                        <a href="{{ route('collections.show', $collection) }}" class="btn-ghost btn-sm">{{ __('View') }}</a>
                                    @endif
                                    <a href="{{ route('admin.collections.edit', $collection) }}" class="btn-outline btn-sm">{{ __('Edit') }}</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $collections->links() }}</div>
    @endif
</x-admin-layout>
