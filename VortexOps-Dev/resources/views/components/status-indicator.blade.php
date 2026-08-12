@props([
    'status' => '',
    'label' => '',
    'animated' => true,
])

@php
    $colors = [
        'pending' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-700',
        'active' => 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border-green-300 dark:border-green-700',
        'success' => 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border-green-300 dark:border-green-700',
        'warning' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-700',
        'error' => 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border-red-300 dark:border-red-700',
        'danger' => 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border-red-300 dark:border-red-700',
        'info' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700',
        'gray' => 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 border-gray-300 dark:border-gray-700',
    ];

    $icons = [
        'pending' => '⏳',
        'active' => '✓',
        'success' => '✓',
        'warning' => '⚠️',
        'error' => '❌',
        'danger' => '❌',
        'info' => 'ℹ️',
        'gray' => '—',
    ];

    $colorClass = $colors[$status] ?? $colors['gray'];
    $icon = $icons[$status] ?? $icons['gray'];
@endphp

<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border
    {{ $colorClass }}
    {{ $animated ? 'animate-pulse' : '' }}
" title="{{ $label ?: ucfirst($status) }}">
    <span>{{ $icon }}</span>
    <span>{{ $label ?: ucfirst($status) }}</span>
</span>
