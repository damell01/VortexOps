@php
use Filament\Support\Enums\MaxWidth;
@endphp

<x-filament-panels::page>
    <x-kpi-row :stats="$this->getStats()" />

    {{-- Rendered directly rather than behind a skeleton swap: the two
         wrappers were flex siblings, so the hidden one still spent a gap
         between the tiles and the table. --}}
    {{ $this->table }}

    {{--
        The bulk Approve / Reject / Export bar that used to sit here was dead:
        it read a data-log-id attribute nothing sets, and posted to
        /admin/api/streamer-logs/{approve,reject}-bulk, two routes that were
        never registered. Every button either said "Please select logs" or
        404'd, and all of its feedback came through browser dialogs.

        Real bulk actions live on the table itself now (see
        StreamerLogResource::table), which brings proper modals, a required
        reason field, and notifications instead of browser dialogs.
    --}}

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
