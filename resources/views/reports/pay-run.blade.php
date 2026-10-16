<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $report['title'] }} · Vortex Ops</title>@include('reports.styles')</head><body>
@php($money = fn ($v) => ($v < 0 ? '-$' : '$').number_format(abs((float) $v), 2))
<main class="report"><div class="brand">VORTEX OPS / PAYROLL</div><h1>{{ $report['title'] }}</h1><p class="muted">{{ $report['business'] }} · {{ $report['kind'] }} · {{ $report['period'] }}</p>
<span class="badge">{{ $report['status'] }} · {{ $report['snapshot'] ? 'Saved snapshot' : 'Current figures' }}</span>
<div class="toolbar no-print">
<a class="button" href="{{ $report['back_url'] }}">Back to pay run</a>
<a class="button" href="{{ request()->fullUrlWithQuery(['format' => 'pdf', 'download' => 0]) }}" target="_blank" rel="noopener">Open PDF</a>
<a class="button primary" href="{{ request()->fullUrlWithQuery(['format' => 'pdf', 'download' => 1]) }}">Download PDF</a>
@foreach($report['snapshots'] as $stage)<a class="button" href="{{ request()->fullUrlWithQuery(['stage' => $stage, 'format' => null, 'download' => null]) }}">{{ ucfirst($stage) }} copy</a>@endforeach
</div>
@if($report['historical_unavailable'])<p class="notice">This run predates saved report snapshots. These are current recorded figures, not a reconstructed historical copy.</p>@endif
@if(!$report['snapshot'] && $report['status'] === 'Draft')<p class="notice">Draft preview. Amounts can change until the pay run is finalized.</p>@endif
<table class="totals"><tr><td><small>Total pay</small><strong>{{ $money($report['total']) }}</strong></td><td><small>People</small><strong>{{ count($report['people']) }}</strong></td><td><small>Payout entries</small><strong>{{ count($report['lines']) }}</strong></td></tr></table>
<h2>Pay by person</h2><div class="table-wrap"><table class="details"><thead><tr><th>Team member</th><th class="number">Entries</th><th class="number">Pay</th></tr></thead><tbody>@forelse($report['people'] as $person)<tr><td>{{ $person['name'] }}</td><td class="number">{{ $person['entries'] }}</td><td class="number">{{ $money($person['total']) }}</td></tr>@empty<tr><td colspan="3">No payouts in this run.</td></tr>@endforelse</tbody></table></div>
@if($report['transfer'])
<h2>Business transfer estimate</h2><div class="formula">Product cost {{ $money($report['transfer']['product_cost']) }} + business revenue {{ $money($report['transfer']['business_revenue']) }} = <strong>{{ $money($report['transfer']['transfer']) }}</strong><br><span class="muted">Existing transfer rule: product cost + Whatnot net - all non-tip pay for these shows, including pay assigned to other runs. This is an estimate, not confirmation of a bank transfer.</span></div>
@else<p class="muted">No separate business transfer applies to this run.</p>@endif
<h2>Payout detail</h2><div class="table-wrap"><table class="details"><thead><tr><th>Person</th><th>Show / work</th><th>Date</th><th>Pay type</th><th class="number">Hours</th><th class="number">Pay</th><th>Status</th></tr></thead><tbody>@foreach($report['lines'] as $line)<tr><td>{{ $line['person'] }}</td><td>{{ $line['show'] }}</td><td>{{ $line['date'] ?: '—' }}</td><td>{{ str_replace('_', ' ', $line['type'] ?? '') }}</td><td class="number">{{ number_format($line['hours'],2) }}</td><td class="number">{{ $money($line['amount']) }}</td><td>{{ ucfirst($line['status']) }}</td></tr>@endforeach</tbody></table></div>
@if($report['notes'])<h2>Notes</h2><p>{{ $report['notes'] }}</p>@endif
<div class="footer">Generated {{ $report['generated_at'] }} · Private business report · Vortex Ops</div></main></body></html>
