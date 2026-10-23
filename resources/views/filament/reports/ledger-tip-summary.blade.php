<section class="vxw-card" aria-label="Ledger tip totals">
    <div class="vxw-card-head"><h2 class="vxw-h2">Ledger tip totals</h2></div>
    <div class="vxw-card-body grid gap-4">
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach (['credits' => 'Tip credits', 'debits' => 'Tip debits / corrections', 'net' => 'Net ledger tips'] as $key => $label)
                <div><span class="vxw-meta">{{ $label }}</span><p class="text-xl font-semibold">${{ number_format($tipReport['total'][$key] / 100, 2) }}</p></div>
            @endforeach
        </div>
        <p class="vxw-meta">{{ $tipReport['total']['entries'] }} transactions · {{ $tipReport['total']['pending'] }} pending / processing · {{ $tipReport['total']['missing_amounts'] }} missing amounts. Totals include all imported statuses and signed corrections.</p>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($tipReport['rows'] as $row)
                <div class="vxw-card p-4">
                    <strong>{{ $row['channel'] }}</strong><p class="vxw-meta">{{ $row['business'] }}</p>
                    <p class="text-lg font-semibold">${{ number_format($row['net'] / 100, 2) }}</p>
                    <p class="vxw-meta">Credits ${{ number_format($row['credits'] / 100, 2) }} · Debits ${{ number_format($row['debits'] / 100, 2) }}</p>
                </div>
            @empty
                <p class="vxw-meta">No imported tips attributed to these filters.</p>
            @endforelse
        </div>
        @if ($tipReport['unassigned_total']['entries'])
            <div class="vxw-alert vxw-alert--warn">
                <div>
                    <strong>Unassigned business: ${{ number_format($tipReport['unassigned_total']['net'] / 100, 2) }}</strong>
                    <p>Not included in this business's total. Review these tips before allocating them.</p>
                    <p>{{ $tipReport['unassigned_total']['pending'] }} pending / processing · {{ $tipReport['unassigned_total']['missing_amounts'] }} missing amounts.</p>
                    @foreach ($tipReport['unassigned'] as $row)
                        <p>{{ $row['channel'] }} · ${{ number_format($row['net'] / 100, 2) }} · {{ $row['entries'] }} transactions</p>
                    @endforeach
                </div>
            </div>
        @endif
        <p class="vxw-meta">Channels are shared. A business is identified only through an exact order match to one show whose streamers all belong to that business. Other tips remain unassigned. These live totals do not allocate tips or change payroll; missing imports can make them incomplete.</p>
    </div>
</section>
