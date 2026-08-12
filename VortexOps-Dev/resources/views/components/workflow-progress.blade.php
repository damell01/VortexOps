@props([
    'current' => 1,
    'total' => 4,
    'steps' => [],
])

<div class="space-y-3">
    {{-- Progress Bar --}}
    <div class="flex items-center gap-3">
        <div class="flex-grow">
            <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-blue-500 to-cyan-500 transition-all duration-300"
                     style="width: {{ ($current / $total) * 100 }}%"></div>
            </div>
        </div>
        <span class="text-sm font-medium text-gray-600 dark:text-gray-400 whitespace-nowrap">
            {{ $current }} of {{ $total }}
        </span>
    </div>

    {{-- Step Labels --}}
    @if(count($steps) > 0)
        <div class="flex justify-between text-xs font-medium text-gray-600 dark:text-gray-400">
            @foreach($steps as $index => $step)
                <div class="flex items-center gap-1">
                    <div class="flex items-center justify-center w-6 h-6 rounded-full
                              {{ $index < $current
                                  ? 'bg-green-500 text-white'
                                  : ($index === $current - 1
                                      ? 'bg-blue-500 text-white'
                                      : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400') }}">
                        @if($index < $current)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                            </svg>
                        @else
                            {{ $index + 1 }}
                        @endif
                    </div>
                    <span class="text-xs">{{ $step }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
