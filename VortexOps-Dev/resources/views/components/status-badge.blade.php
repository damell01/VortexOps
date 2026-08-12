@props([
    'status' => 'neutral',
    'label' => '',
])

@php
    $statusClasses = [
        'success' => 'fi-ta-cell-status fi-ta-cell-status-success',
        'warning' => 'fi-ta-cell-status fi-ta-cell-status-warning',
        'danger' => 'fi-ta-cell-status fi-ta-cell-status-danger',
        'neutral' => 'fi-ta-cell-status',
    ];

    $statusIcons = [
        'success' => '✓',
        'warning' => '⚠',
        'danger' => '✕',
        'neutral' => '–',
    ];

    $class = $statusClasses[$status] ?? $statusClasses['neutral'];
    $icon = $statusIcons[$status] ?? $statusIcons['neutral'];
@endphp

<span class="{{ $class }}" title="{{ $label }}">
    <span class="fi-status-icon">{{ $icon }}</span>
    <span class="fi-status-label">{{ $label ?: ucfirst($status) }}</span>
</span>
