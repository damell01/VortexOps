@props([
    'label' => '',
    'value' => '',
    'removable' => true,
])

<span class="fi-ta-filter-badge">
    <span class="fi-filter-badge-label">
        @if($label)
            <strong>{{ $label }}:</strong>
        @endif
        {{ $value }}
    </span>
    @if($removable)
        <button
            type="button"
            class="fi-ta-filter-badge-close"
            title="Remove filter"
            aria-label="Remove {{ $label }} filter"
        >
            ✕
        </button>
    @endif
</span>
