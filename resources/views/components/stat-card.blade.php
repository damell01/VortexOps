@props([
    'label' => '',
    'value' => '',
    'status' => 'neutral',
    'trend' => null,
    'trendValue' => null,
    'timestamp' => null,
    'icon' => null,
    'color' => null,
])

@php
    $statusClass = match($status) {
        'good' => 'fi-stat-good',
        'warning' => 'fi-stat-warning',
        'critical' => 'fi-stat-critical',
        default => 'fi-stat-neutral',
    };

    $accentColor = match($status) {
        'good' => '#10b981',
        'warning' => '#f59e0b',
        'critical' => '#f43f5e',
        default => '#7c3aed',
    };

    if ($color) {
        $accentColor = $color;
    }
@endphp

<div class="fi-wi-stats-overview-stat-content {{ $statusClass }}"
     style="--card-accent-color: {{ $accentColor }}">

    @if($icon)
        <div class="fi-stat-icon">{{ $icon }}</div>
    @endif

    <div class="fi-stat-header">
        <p class="fi-wi-stats-overview-stat-label">{{ $label }}</p>
    </div>

    <div class="fi-stat-value-wrapper">
        <p class="fi-wi-stats-overview-stat-value">{{ $value }}</p>

        @if($trend && $trendValue)
            <span class="fi-stat-trend fi-stat-trend-{{ $trend }}">
                {{ $trendValue }}
            </span>
        @endif
    </div>

    @if($timestamp)
        <div class="fi-stat-timestamp">
            Updated: <time datetime="{{ $timestamp }}">{{ $this->formatTime($timestamp) ?? date('M d, g:i A', strtotime($timestamp)) }}</time>
        </div>
    @endif

    {{ $slot }}
</div>
