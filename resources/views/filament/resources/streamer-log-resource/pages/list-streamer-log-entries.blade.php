@php
use Filament\Support\Enums\MaxWidth;
@endphp

<x-filament-panels::page>
    <style>
        .vx-streamer-log-page{min-width:0;max-width:100%}
        .vx-streamer-log-page .fi-ta-content{overflow:visible}
        .vx-streamer-log-page .fi-ta-table{min-width:0!important}
        .vx-streamer-log-page .fi-ta-record{border:1px solid rgb(229 231 235);border-radius:16px;background:#fff;overflow:hidden;box-shadow:0 1px 2px rgba(15,23,42,.04)}
        .dark .vx-streamer-log-page .fi-ta-record{border-color:#263248;background:#101827}
        .vx-streamer-log-page .fi-ta-cell{padding:.7rem .9rem!important}
        .vx-streamer-log-page .fi-ta-cell:first-child{padding-top:1rem!important}
        .vx-streamer-log-page .fi-ta-cell:last-child{padding-bottom:1rem!important}
        .vx-streamer-log-page .fi-ta-text-item,.vx-streamer-log-page .fi-badge{max-width:100%;white-space:normal!important;overflow-wrap:anywhere}
        .vx-streamer-log-page .fi-ta-actions{display:flex;flex-wrap:wrap;justify-content:flex-start;gap:.35rem;padding:.75rem .9rem!important;border-top:1px solid rgb(243 244 246)}
        .dark .vx-streamer-log-page .fi-ta-actions{border-color:#1f2937}
        .vx-streamer-log-page .fi-ta-header-cell{display:none}
        @media(max-width:767px){.vx-streamer-log-page .fi-ta-record{border-radius:14px}.vx-streamer-log-page .fi-ta-cell{padding:.55rem .75rem!important}}
    </style>

    <div class="vx-streamer-log-page">
        <x-kpi-row :stats="$this->getStats()" />

        {{-- Operational queue uses responsive cards so show names/status/actions stay readable
             without a wide horizontal table. Filament still provides search, filters and pagination. --}}
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
