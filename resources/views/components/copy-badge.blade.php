@props([
    'value' => '',
    'display' => '',
    'class' => 'text-sm font-mono',
])

<span class="flex items-center gap-1 group" data-copy="{{ $value }}" data-copy-label="Copied!">
    <span class="{{ $class }}">{{ $display ?: $value }}</span>
    <button type="button" class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            onclick="event.preventDefault(); event.stopPropagation(); window.copyToClipboard('{{ addslashes($value) }}');"
            title="Copy to clipboard">
        📋
    </button>
</span>
