@php
    $p = $preview;
    $money = fn ($value) => '$' . number_format((float) $value, 2);
@endphp
<div class="vxw space-y-4">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        What each team member will receive if you finalize this pay run now, after loan
        repayments. Nothing is saved — this is a preview.
    </p>

    <div class="grid gap-3" aria-label="Payout preview by person">
        @forelse ($p['rows'] as $row)
            <article class="vxw-card">
                <div class="vxw-card-body">
                    <h3 class="vxw-h2 break-words">{{ $row['streamer'] }}</h3>
                    <dl class="grid grid-cols-2 gap-3 mt-3">
                        <div><dt class="vxw-fig-label">Gross pay</dt><dd class="vxw-fig-value">{{ $money($row['gross']) }}</dd></div>
                        <div><dt class="vxw-fig-label">Loan repayment</dt><dd class="vxw-fig-value {{ $row['loan'] > 0 ? 'vxw-tone-warn' : '' }}">{{ $row['loan'] > 0 ? '−' . $money($row['loan']) : '—' }}</dd></div>
                        <div class="col-span-2"><dt class="vxw-fig-label">Net pay</dt><dd class="vxw-stat-value">{{ $money($row['net']) }}</dd></div>
                    </dl>
                </div>
            </article>
        @empty
            <p class="vxw-empty">No payouts in this batch yet.</p>
        @endforelse
    </div>

    @if (! empty($p['rows']))
        <section class="vxw-card" aria-label="Payout preview totals">
            <div class="vxw-card-body">
                <h3 class="vxw-h2">Total ({{ count($p['rows']) }})</h3>
                <dl class="vxw-dl mt-2">
                    <div><dt>Gross pay</dt><dd>{{ $money($p['gross']) }}</dd></div>
                    <div><dt>Loan repayment</dt><dd>{{ $p['loan'] > 0 ? '−' . $money($p['loan']) : '—' }}</dd></div>
                    <div><dt><strong>Net pay</strong></dt><dd><strong>{{ $money($p['net']) }}</strong></dd></div>
                </dl>
            </div>
        </section>
    @endif
</div>
