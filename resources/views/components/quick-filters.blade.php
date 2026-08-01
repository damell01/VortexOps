@props(['filters' => []])

<div class="flex flex-wrap gap-2 mb-4">
    @foreach($filters as $filter)
        <a href="{{ $filter['url'] }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg transition
                  {{ request()->fullUrlWithQuery(['tableFilters[' . $filter['key'] . '][value]' => $filter['value']]) === url()->full()
                     ? 'bg-blue-500 text-white shadow-md'
                     : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
            <span>{{ $filter['icon'] ?? '' }}</span>
            <span>{{ $filter['label'] }}</span>
            @if(isset($filter['count']))
                <span class="ml-1 inline-flex items-center justify-center w-5 h-5 text-xs font-bold rounded-full
                           {{ request()->fullUrlWithQuery(['tableFilters[' . $filter['key'] . '][value]' => $filter['value']]) === url()->full()
                              ? 'bg-white/30'
                              : 'bg-gray-300 dark:bg-gray-600' }}">
                    {{ $filter['count'] }}
                </span>
            @endif
        </a>
    @endforeach
</div>
