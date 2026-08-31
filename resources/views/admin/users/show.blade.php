<x-admin-layout :title="$user->first_name.' '.$user->last_name" :heading="$user->first_name.' '.$user->last_name">
    <nav class="-mt-4 mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('admin.users.index') }}" class="hover:text-ink">{{ __('Team') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ $user->first_name }} {{ $user->last_name }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-10 lg:grid-cols-[1fr_340px]">
        <div class="space-y-6">
            <div class="panel p-6">
                <p class="eyebrow mb-4">{{ __('Account details') }}</p>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-soft">{{ __('Email') }}</dt>
                        <dd class="text-ink">{{ $user->email }}</dd>
                    </div>
                    @if ($user->phone_number)
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-soft">{{ __('Phone') }}</dt>
                            <dd class="text-ink">{{ $user->phone_number }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-soft">{{ __('Joined') }}</dt>
                        <dd class="text-ink">{{ $user->created_at->format('M j, Y') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-soft">{{ __('Orders placed') }}</dt>
                        <dd class="font-mono text-ink">{{ $user->orders_count }}</dd>
                    </div>
                </dl>
            </div>

            <div class="panel p-6 text-sm text-ink-soft">
                <p class="eyebrow mb-2">{{ __('What each role means') }}</p>
                <ul class="space-y-2">
                    <li><span class="text-ink">{{ __('Customer') }}</span> — {{ __('shops, places orders, and uses prescriptions as normal.') }}</li>
                    <li><span class="text-ink">{{ __('Staff') }}</span> — {{ __('uses the admin console to update stock, orders, returns, and prescriptions.') }}</li>
                    <li><span class="text-ink">{{ __('Delivery') }}</span> — {{ __('sees only assigned orders and can update their fulfilment status.') }}</li>
                </ul>
            </div>
        </div>

        <div class="panel p-6">
            <p class="eyebrow mb-4">{{ __('Role') }}</p>
            <p class="mb-4 text-sm text-ink-soft">
                {{ __('Current role:') }} <span class="font-medium text-ink">{{ str($user->role)->headline() }}</span>
            </p>

            <form method="POST" action="{{ route('admin.users.role', $user) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <select name="role" class="select">
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected($user->role === $role)>{{ str($role)->headline() }}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn-primary btn-sm w-full">{{ __('Save role') }}</button>
            </form>
        </div>
    </div>
</x-admin-layout>
