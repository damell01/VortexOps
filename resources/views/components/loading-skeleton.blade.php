@props([
    'type' => 'text', // text, line, card, table, avatar
    'count' => 1,
])

{{-- Loading Skeleton Component --}}

@if($type === 'text')
    @for($i = 0; $i < $count; $i++)
        <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse-skeleton mb-3 last:mb-0"
             style="width: {{ rand(60, 100) }}%"></div>
    @endfor

@elseif($type === 'line')
    @for($i = 0; $i < $count; $i++)
        <div class="h-2 bg-gray-300 dark:bg-gray-600 rounded-full animate-pulse-skeleton mb-2 last:mb-0"></div>
    @endfor

@elseif($type === 'card')
    @for($i = 0; $i < $count; $i++)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 mb-4">
            <div class="h-6 bg-gray-200 dark:bg-gray-700 rounded animate-pulse-skeleton mb-3" style="width: 40%"></div>
            <div class="space-y-2">
                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse-skeleton" style="width: 100%"></div>
                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse-skeleton" style="width: 95%"></div>
                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse-skeleton" style="width: 85%"></div>
            </div>
        </div>
    @endfor

@elseif($type === 'table')
    @for($i = 0; $i < $count; $i++)
        <div class="border-b border-gray-200 dark:border-gray-700 py-4 mb-4">
            <div class="flex gap-4">
                <div class="h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded animate-pulse-skeleton flex-shrink-0"></div>
                <div class="flex-grow space-y-2">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse-skeleton w-1/3"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse-skeleton w-1/2"></div>
                </div>
                <div class="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded animate-pulse-skeleton flex-shrink-0"></div>
            </div>
        </div>
    @endfor

@elseif($type === 'avatar')
    @for($i = 0; $i < $count; $i++)
        <div class="flex items-center gap-3 mb-3">
            <div class="h-12 w-12 bg-gray-200 dark:bg-gray-700 rounded-full animate-pulse-skeleton flex-shrink-0"></div>
            <div class="flex-grow">
                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse-skeleton mb-2" style="width: 60%"></div>
                <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded animate-pulse-skeleton" style="width: 40%"></div>
            </div>
        </div>
    @endfor

@endif

<style>
    @keyframes pulseSkeleton {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }

    .animate-pulse-skeleton {
        animation: pulseSkeleton 2s ease-in-out infinite;
    }
</style>
