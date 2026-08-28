<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->roleCards() as $role)
            <a href="{{ $role['url'] }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-400 hover:shadow dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-3">
                    <div><div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Role</div><div class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ $role['label'] }}</div></div>
                    @if ($role['core']) <span class="rounded-full bg-primary-50 px-2 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">Core</span> @endif
                </div>
                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-lg bg-success-50 p-2 dark:bg-success-500/10"><div class="text-lg font-bold text-success-700 dark:text-success-300">{{ $role['manage'] }}</div><div class="text-[11px] text-gray-500">Manage</div></div>
                    <div class="rounded-lg bg-warning-50 p-2 dark:bg-warning-500/10"><div class="text-lg font-bold text-warning-700 dark:text-warning-300">{{ $role['view'] }}</div><div class="text-[11px] text-gray-500">View only</div></div>
                    <div class="rounded-lg bg-gray-100 p-2 dark:bg-white/5"><div class="text-lg font-bold text-gray-700 dark:text-gray-200">{{ $role['hidden'] }}</div><div class="text-[11px] text-gray-500">Hidden</div></div>
                </div>
                <div class="mt-4 flex items-center justify-between text-sm text-gray-500"><span>{{ $role['users'] }} users</span><span class="font-semibold text-primary-600">Edit access →</span></div>
            </a>
        @endforeach
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><div class="font-semibold text-gray-950 dark:text-white">Role directory</div><div class="text-sm text-gray-500">Open a role to manage page access and detailed permissions.</div></div>
            <a href="{{ \App\Filament\Pages\NavigationManager::getUrl() }}" class="text-sm font-semibold text-primary-600">Open Navigation Manager / Preview As →</a>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
