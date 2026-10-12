@php
    $record = $getRecord();
    $complete = in_array($record->status, ['received','processed'], true);
    $expected = (int) $record->expected_cases_sum;
    $received = (int) $record->received_cases_count;
    $locations = $record->lines->map(fn ($line) => $line->location?->name)->filter()->unique()->implode(', ');
@endphp
<article class="vx-receipt-card">
    <div class="vx-receipt-top"><span class="vx-receipt-badge {{ $complete ? 'is-complete' : 'is-progress' }}">{{ $complete ? 'Completed' : 'In progress' }}</span><time>{{ $record->received_date?->format('M j, Y') ?? $record->receiving_started_at?->copy()->timezone(\App\Support\BusinessTime::zone())->format('M j, Y') ?? 'Not completed' }}</time></div>
    <h3>{{ $record->displayName() }}</h3>
    <p class="vx-receipt-vendor">{{ $record->vendor?->name ?? 'No vendor assigned' }}</p>
    <p class="vx-receipt-reference">{{ $record->reference ?: 'No reference recorded' }}</p>
    <div class="vx-receipt-counts">
        <div><span>Expected</span><strong>{{ number_format($expected) }}</strong></div>
        <div><span>Received</span><strong>{{ number_format($received) }}</strong></div>
        <div><span>Remaining</span><strong class="{{ $expected > $received ? 'vx-outstanding' : '' }}">{{ number_format(max(0,$expected-$received)) }}</strong></div>
    </div>
    <div class="vx-receipt-footer"><span title="{{ $locations }}">{{ $locations ?: 'Location not assigned' }}</span><span>{{ (int)$record->attachments_count }} files</span></div>
    @if($record->missing_items_count)<p class="vx-receipt-warning">{{ number_format($record->missing_items_count) }} missing-item {{ (int)$record->missing_items_count === 1 ? 'report' : 'reports' }} to review</p>@endif
</article>