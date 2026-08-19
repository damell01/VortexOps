@props(['cards' => []])

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    @foreach($cards as [$title, $body])
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">{{ $title }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $body }}</p>
        </div>
    @endforeach
</div>
