@php
    $money = fn ($v) => ($v < 0 ? '−$' : '$').number_format(abs((float) $v), 2);
    $t = $totals;
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $business->name }} — {{ $month }} statement</title>
@include('pdf.partials.statement-styles')
</head>
<body>
@include('pdf.partials.brand-header', [
    'reportTitle' => 'Monthly Statement',
    'reportSubtitle' => $business->name.' • '.$month,
    'generatedAt' => $generated_at,
])

<table class="tiles"><tr>
    <td><div class="tile-label">Shows</div><div class="tile-value">{{ $t['shows'] }}</div></td>
    <td><div class="tile-label">Whatnot net</div><div class="tile-value">{{ $money($t['net']) }}</div></td>
    <td><div class="tile-label">Product cost</div><div class="tile-value">{{ $money($t['cogs']) }}</div></td>
    <td><div class="tile-label">Pay</div><div class="tile-value">{{ $money($t['pay']) }}</div></td>
    <td><div class="tile-label">Transfer</div><div class="tile-value">{{ $money($t['transfer']['transfer']) }}</div></td>
</tr></table>

<div class="section">
    <div class="section-title">Week by week</div>
    <table class="grid">
        <thead><tr><th>Week</th><th class="num">Shows</th><th class="num">Sales</th><th class="num">Whatnot net</th><th class="num">Product cost</th><th class="num">Pay</th><th class="num">Profit</th><th class="num">Transfer</th></tr></thead>
        <tbody>
        @forelse ($weeks as $w)
            <tr>
                <td>{{ $w['label'] }}</td><td class="num">{{ $w['shows'] }}</td><td class="num">{{ $money($w['sales']) }}</td>
                <td class="num">{{ $money($w['net']) }}</td><td class="num">{{ $money($w['cogs']) }}</td><td class="num">{{ $money($w['pay']) }}</td>
                <td class="num {{ $w['profit'] < 0 ? 'neg' : '' }}">{{ $money($w['profit']) }}</td><td class="num">{{ $money($w['transfer']['transfer']) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">No shows this month.</td></tr>
        @endforelse
        </tbody>
        <tfoot><tr class="total">
            <td>{{ $month }}</td><td class="num">{{ $t['shows'] }}</td><td class="num">{{ $money($t['sales']) }}</td><td class="num">{{ $money($t['net']) }}</td>
            <td class="num">{{ $money($t['cogs']) }}</td><td class="num">{{ $money($t['pay']) }}</td><td class="num">{{ $money($t['profit']) }}</td><td class="num">{{ $money($t['transfer']['transfer']) }}</td>
        </tr></tfoot>
    </table>
</div>

<div class="section">
    <div class="section-title">Business account transfer for the month</div>
    <table class="grid narrow">
        <tr><td>Product cost of these shows</td><td class="num">{{ $money($t['transfer']['product_cost']) }}</td></tr>
        <tr><td>Whatnot net</td><td class="num">{{ $money($t['transfer']['net']) }}</td></tr>
        <tr><td>Less pay for these shows (tips aside)</td><td class="num">{{ $money(-$t['transfer']['payouts']) }}</td></tr>
        <tr><td>Business revenue</td><td class="num">{{ $money($t['transfer']['business_revenue']) }}</td></tr>
        <tr class="total"><td>Transfer</td><td class="num">{{ $money($t['transfer']['transfer']) }}</td></tr>
    </table>
</div>

@if ($runs->isNotEmpty())
<div class="section">
    <div class="section-title">Pay runs that covered this month</div>
    <table class="grid">
        <thead><tr><th>Pay run</th><th>Dates</th><th>Status</th><th class="num">Total</th></tr></thead>
        <tbody>
        @foreach ($runs as $r)
            <tr><td>{{ $r->pay_run_type_name ?: 'Pay run #'.$r->id }}</td><td>{{ $r->week_start?->format('M j') }} – {{ $r->week_end?->format('M j') }}</td>
                <td>{{ str($r->status)->replace('_', ' ')->headline() }}</td><td class="num">{{ $money($r->total_payout) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@if ($shows->isNotEmpty())
<div class="section page-break">
    <div class="section-title">Every show</div>
    <table class="grid">
        <thead><tr><th>Show</th><th class="num">Sales</th><th class="num">Whatnot net</th><th class="num">Product cost</th><th class="num">Pay</th><th class="num">Profit</th></tr></thead>
        <tbody>
        @foreach ($shows as $s)
            <tr>
                <td>{{ \Illuminate\Support\Str::limit($s['title'], 60) }}<div class="sub">{{ $s['date']->format('D M j') }} · {{ $s['streamer'] }}{{ $s['incomplete'] ? ' · '.implode(', ', $s['incomplete']) : '' }}</div></td>
                <td class="num">{{ $money($s['sales']) }}</td><td class="num">{{ $money($s['whatnot_net']) }}</td><td class="num">{{ $money($s['cogs']) }}</td>
                <td class="num">{{ $money($s['payouts']) }}</td><td class="num {{ $s['profit'] < 0 ? 'neg' : '' }}">{{ $money($s['profit']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

<p class="note">
    Figures are the same as the Profit &amp; Loss page for {{ $business->name }}'s streamers.
    Transfer = product cost + (Whatnot net − pay, tips aside), the same as on each pay run.
    @if ($t['incomplete']) {{ $t['incomplete'] }} {{ \Illuminate\Support\Str::plural('show', $t['incomplete']) }} still missing earnings, cost or final pay; those numbers may change. @endif
</p>

@include('pdf.partials.brand-footer', ['footerLabel' => 'Monthly Statement'])
</body>
</html>
