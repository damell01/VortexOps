@php
    /** @var \App\Models\StreamerLogEntry|null $log */
    $log = $this->getLog();

    $money = fn ($v) => '$' . number_format((float) ($v ?? 0), 2);
    $num   = fn ($v) => number_format((float) ($v ?? 0), 0);

    $statusColour = match ((string) ($log?->status ?? '')) {
        'approved', 'reviewed' => 'success',
        'pending', 'pending_review', 'submitted' => 'warning',
        'rejected' => 'danger',
        default => 'gray',
    };
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-m-clipboard-document-list"
        :collapsible="filled($log)"
    >
        <x-slot name="heading">Streamer Log</x-slot>

        <x-slot name="description">
            @if ($log)
                Submitted by {{ $log->streamer?->name ?? 'Unknown streamer' }}
                @if ($log->submitted_at)
                    on {{ $log->submitted_at->format('M j, Y g:i A') }}
                @endif
            @else
                No streamer log has been submitted for this show yet.
            @endif
        </x-slot>

        @if ($log)
            <x-slot name="afterHeader">
                <div class="vx-log-header-end">
                    <x-filament::badge :color="$statusColour">
                        {{ str($log->status ?? 'unknown')->replace('_', ' ')->title() }}
                    </x-filament::badge>

                    <x-filament::button
                        tag="a"
                        href="{{ \App\Filament\Resources\StreamerLogResource::getUrl('edit', ['record' => $log]) }}"
                        size="xs"
                        color="gray"
                        icon="heroicon-m-arrow-top-right-on-square"
                    >
                        Open log
                    </x-filament::button>
                </div>
            </x-slot>

            <dl class="vx-log-grid">
                @foreach ([
                    ['Hours Streamed',  $num($log->hours_streamed)],
                    ['Shipments',       $num($log->number_of_shipments)],
                    ['PWE Count',       $num($log->pwe_count)],
                    ['Label Count',     $num($log->label_count)],
                    ['Gross Revenue',   $money($log->gross_revenue)],
                    ['Product Cost',    $money($log->product_cost)],
                    ['Profit Share',    $money($log->profit_share_amount)],
                    ['Total Due',       $money($log->total_due)],
                ] as [$label, $value])
                    <div class="vx-log-cell">
                        <dt>{{ $label }}</dt>
                        <dd>{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if (filled($log->notes))
                <div class="vx-log-notes">
                    <span class="vx-log-notes-label">Notes</span>
                    <p>{{ $log->notes }}</p>
                </div>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
