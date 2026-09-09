@php
use Filament\Support\Enums\MaxWidth;
@endphp

<x-filament-panels::page>
    <style>
        .vx-streamer-log-page { min-width: 0; max-width: 100%; overflow: hidden; }
        .vx-streamer-log-page .fi-ta { min-width: 0; max-width: 100%; }
        .vx-streamer-log-page .fi-ta-content { max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .vx-streamer-log-page .fi-ta-table { width: 100%; min-width: 980px; }
        .vx-streamer-log-page .fi-ta-cell,
        .vx-streamer-log-page .fi-ta-header-cell { min-width: 0; }
        .vx-streamer-log-page .fi-ta-text-item,
        .vx-streamer-log-page .fi-badge { max-width: 100%; white-space: normal !important; overflow-wrap: anywhere; word-break: break-word; }
        .vx-streamer-log-page .fi-ta-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .25rem; max-width: 12rem; }
        .vx-streamer-log-page .fi-ta-actions .fi-btn { max-width: 100%; min-width: 0; padding-inline: .55rem; }
        .vx-streamer-log-page .fi-ta-actions .fi-btn-label { white-space: normal; line-height: 1.15; text-align: center; }
        .vx-streamer-log-page .fi-dropdown-trigger button,
        .vx-streamer-log-page .fi-icon-btn { flex: 0 0 auto; }
        .vx-streamer-log-page .vx-col-title { min-width: 14rem; max-width: 20rem; }
        .vx-streamer-log-page .vx-col-tight { min-width: 7rem; max-width: 10rem; }
        .vx-streamer-log-page td { vertical-align: middle; }

        @media (max-width: 767px) {
            .vx-streamer-log-page { overflow: visible; }
            .vx-streamer-log-page .fi-ta-content { margin-inline: -1rem; padding-inline: 1rem; }
            .vx-streamer-log-page .fi-ta-table { min-width: 860px; }
            .vx-streamer-log-page .fi-ta-actions { max-width: 8rem; }
            .vx-streamer-log-page .fi-ta-actions .fi-btn { min-height: 2.25rem; font-size: .72rem; }
            .vx-streamer-log-page .fi-ta-text-item { line-height: 1.3; }
        }
    </style>

    <div class="vx-streamer-log-page">
        <x-kpi-row :stats="$this->getStats()" />

        {{-- Keep the table inside its own scroll region on narrow screens so
             long show names, statuses, and row actions never push the whole
             Filament page wider than the viewport. --}}
        {{ $this->table }}
    </div>

    <!-- Floating action button for quick navigation -->
    <x-floating-action-button label="Menu" icon="⋮">
        <a href="{{ route('filament.admin.resources.shows.index') }}"
           class="floating-action-menu-item">
            🎬 View Shows
        </a>
        <a href="{{ route('filament.admin.resources.streamers.index') }}"
           class="floating-action-menu-item">
            🎤 View Streamers
        </a>
    </x-floating-action-button>
</x-filament-panels::page>
