@props(['title' => null, 'rows' => []])

<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
    @if($title)
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">{{ $title }}</h3>
    @endif
    <dl class="space-y-2.5 text-sm">
        @foreach($rows as [$term, $definition])
            <div class="sm:flex sm:gap-3">
                <dt class="font-semibold text-gray-900 dark:text-gray-100 sm:w-52 sm:flex-shrink-0">{{ $term }}</dt>
                <dd class="text-gray-500 dark:text-gray-400">{{ $definition }}</dd>
            </div>
        @endforeach
    </dl>
</div>
