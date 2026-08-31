<x-admin-layout title="Team" heading="Team accounts">
    <p class="-mt-4 mb-8 max-w-2xl text-sm text-ink-soft">
        {{ __('Promote a customer to staff or delivery, or move someone back to a normal shopper account. The same login and password stay in place — only what they can access changes.') }}
    </p>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-6 flex flex-wrap items-center gap-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Name or email…') }}" class="input max-w-xs">
        <select name="role" class="select max-w-xs" onchange="this.form.submit()">
            <option value="">{{ __('All roles') }}</option>
            @foreach ($roles as $role)
                <option value="{{ $role }}" @selected(request('role') === $role)>{{ str($role)->headline() }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-outline btn-sm">{{ __('Filter') }}</button>
    </form>

    @if ($users->isEmpty())
        <x-empty-state :title="__('No accounts found')" />
    @else
        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Joined') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $account)
                        <tr>
                            <td class="text-ink">{{ $account->first_name }} {{ $account->last_name }}</td>
                            <td>{{ $account->email }}</td>
                            <td><span class="badge-neutral">{{ str($account->role)->headline() }}</span></td>
                            <td>{{ $account->created_at->format('M j, Y') }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.users.show', $account) }}" class="text-xs text-ink underline hover:no-underline">{{ __('Manage') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $users->links() }}
    @endif
</x-admin-layout>
