@php($shortcuts = \App\Support\DashboardShortcuts::forCurrentUser())
@if(count($shortcuts))
<section aria-label="Quick actions" class="mb-5">
    <h2 class="mb-3 text-base font-bold text-gray-950 dark:text-white">Quick actions</h2>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($shortcuts as $shortcut)
        <a href="{{ $shortcut['url'] }}" class="flex min-h-24 items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary-400 dark:border-gray-700 dark:bg-gray-900">
            <x-dynamic-component :component="$shortcut['icon']" class="mt-1 h-6 w-6 shrink-0 text-primary-600" />
            <div><span class="block text-sm font-bold text-gray-950 dark:text-white">{{ $shortcut['label'] }}</span><span class="mt-1 block text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $shortcut['description'] }}</span></div>
        </a>
        @endforeach
    </div>
</section>
@endif
