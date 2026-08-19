@props(['title' => null, 'steps' => []])

<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
    @if($title)
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">{{ $title }}</h3>
    @endif
    <ol class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
        @foreach($steps as $i => [$heading, $body])
            <li class="flex gap-3">
                {{-- Numbered because these are genuinely sequential; the order
                     carries information the reader needs. --}}
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-violet-100 dark:bg-violet-900/50 text-violet-700 dark:text-violet-300 text-xs font-bold grid place-items-center">{{ $i + 1 }}</span>
                <span>
                    <strong class="text-gray-900 dark:text-gray-100">{{ $heading }}</strong>
                    <span class="block text-gray-500 dark:text-gray-400">{{ $body }}</span>
                </span>
            </li>
        @endforeach
    </ol>
</div>
