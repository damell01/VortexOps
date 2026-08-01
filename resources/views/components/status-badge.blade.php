@php
    use App\Support\StatusDisplay;

    $icon = StatusDisplay::getIcon($status);
    $color = StatusDisplay::getColor($status);
    $description = StatusDisplay::getDescription($status);

    $colorClasses = match($color) {
        'green' => 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border-green-300 dark:border-green-700',
        'red' => 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border-red-300 dark:border-red-700',
        'amber' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-700',
        'blue' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700',
        'indigo' => 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-800 dark:text-indigo-200 border-indigo-300 dark:border-indigo-700',
        'gray' => 'bg-gray-100 dark:bg-gray-900/30 text-gray-800 dark:text-gray-200 border-gray-300 dark:border-gray-700',
        default => 'bg-gray-100 dark:bg-gray-900/30 text-gray-800 dark:text-gray-200 border-gray-300 dark:border-gray-700',
    };

    $label = $label ?? ucfirst(str_replace('_', ' ', $status));
@endphp

<span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium border rounded-full {{ $colorClasses }} cursor-help group relative transition-all duration-200 hover:shadow-md"
      title="{{ $description }}">
    <span class="text-base">{{ $icon }}</span>
    <span>{{ $label }}</span>

    {{-- Tooltip --}}
    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 dark:bg-gray-700 text-white text-xs rounded-lg whitespace-nowrap shadow-lg opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-20">
        {{ $description }}
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900 dark:border-t-gray-700"></div>
    </div>
</span>
