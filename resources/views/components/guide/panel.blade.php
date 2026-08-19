@props(['title' => null, 'tone' => 'plain'])

@php
    // One place to change how a callout looks, rather than the same three
    // classes copied into every section of the guide.
    $tones = [
        'plain'  => ['border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900', 'text-gray-900 dark:text-gray-100', 'text-gray-500 dark:text-gray-400'],
        'violet' => ['border-violet-200 dark:border-violet-800 bg-violet-50 dark:bg-violet-950/40', 'text-violet-900 dark:text-violet-200', 'text-violet-800 dark:text-violet-300'],
        'amber'  => ['border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40', 'text-amber-900 dark:text-amber-200', 'text-amber-800 dark:text-amber-300'],
        'green'  => ['border-green-300 dark:border-green-800 bg-green-50 dark:bg-green-950/40', 'text-green-900 dark:text-green-200', 'text-green-800 dark:text-green-300'],
    ];
    [$box, $heading, $body] = $tones[$tone] ?? $tones['plain'];
@endphp

<div {{ $attributes->class(['rounded-xl border px-6 py-5', $box]) }}>
    @if($title)
        <h3 class="text-sm font-semibold mb-1 {{ $heading }}">{{ $title }}</h3>
    @endif
    <div class="text-sm {{ $body }}">{{ $slot }}</div>
</div>
