@php
    $money = fn ($v) => ($v < 0 ? '−$' : '$').number_format(abs((float) $v), 2);
    $t = $totals;
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $name }} — {{ $dates }}</title>
@include('pdf.partials.statement-styles')
</head>
<body>
@include('pdf.partials.brand-header', [
    'reportTitle' => 'Pay Run Report',
    'reportSubtitle' => $name.' • '.$dates.($business ? ' • '.$business : ''),
    'generatedAt' => $generated_at,
])

<table class="tiles"><tr>
    <td><div class="tile-label">Total pay</div><div class="tile-value">{{ $money($t['total']) }}</div></td>
    <td><div class="tile-label">People</div><div class="tile-value">{{ $t['people'] }}</div></td>
    <td><div class="tile-label">Shows</div><div class="tile-value">{{ $t['shows'] }}</div></td>
    <td><div class="tile-label">Hours</div><div class="tile-value">{{ rtrim(rtrim(number_format($t['hours'], 2), '0'), '.') }}</div></td>
    <td><div class="tile-label">Status</div><div class="tile-value small">{{ $status }}</div></td>
</tr></table>

<div class="section">
    <div class="section-title">Pay by person</div>
    <table class="grid">
        <thead><tr>
            <th>Person</th><th class="num">Shows</th><th class="num">Hours</th><th class="num">Env / Pkg</th>
            <th class="num">Pay</th><th class="num">Wages</th><th class="num">Tips</th><th class="num">Total</th>
        </tr></thead>
        <tbody>
        @forelse ($people as $p)
            <tr>
                <td><strong>{{ $p['name'] }}</strong><div class="sub">{{ $p['pay_type'] }}</div></td>
                <td class="num">{{ $p['shows'] }}</td>
                <td class="num">{{ $p['hours'] ? rtrim(rtrim(number_format($p['hours'], 2), '0'), '.') : '—' }}</td>
                <td class="num">{{ $p['envelopes'] || $p['packages'] ? $p['envelopes'].' / '.$p['packages'] : '—' }}</td>
                <td class="num">{{ $p['main'] ? $money($p['main']) : '—' }}</td>
                <td class="num">{{ $p['wages'] ? $money($p['wages']) : '—' }}</td>
                <td class="num">{{ $p['tips'] ? $money($p['tips']) : '—' }}</td>
                <td class="num"><strong>{{ $money($p['total']) }}</strong></td>
            </tr>
            @if (count($p['show_lines']) > 0)
                <tr class="detail"><td colspan="8">
                    @foreach ($p['show_lines'] as $s)<span class="chip">{{ $s['date'] }} · {{ \Illuminate\Support\Str::limit($s['title'], 48) }} · {{ $money($s['amount']) }}</span>@endforeach
                </td></tr>
            @endif
        @empty
            <tr><td colspan="8" class="muted">No pay lines on this run.</td></tr>
        @endforelse
        </tbody>
        <tfoot><tr class="total">
            <td>Total</td><td class="num">{{ $t['shows'] }}</td><td class="num">{{ rtrim(rtrim(number_format($t['hours'], 2), '0'), '.') }}</td><td></td>
            <td class="num">{{ $money($t['main']) }}</td><td class="num">{{ $money($t['wages']) }}</td><td class="num">{{ $money($t['tips']) }}</td><td class="num">{{ $money($t['total']) }}</td>
        </tr></tfoot>
    </table>
</div>

@if ($transfer)
<div class="section">
    <div class="section-title">Business account transfer</div>
    <table class="grid narrow">
        <tr><td>Product cost of these shows</td><td class="num">{{ $money($transfer['product_cost']) }}</td></tr>
        <tr><td>Whatnot net</td><td class="num">{{ $money($transfer['net']) }}</td></tr>
        <tr><td>Less pay for these shows (tips aside)</td><td class="num">{{ $money(-$transfer['payouts']) }}</td></tr>
        <tr><td>Business revenue</td><td class="num">{{ $money($transfer['business_revenue']) }}</td></tr>
        <tr class="total"><td>Transfer to the business account</td><td class="num">{{ $money($transfer['transfer']) }}</td></tr>
    </table>
</div>
@endif

<p class="note">
    {{ $status }}@if ($run->finalized_at) · finalized {{ $run->finalized_at->timezone(\App\Support\BusinessTime::zone())->format('M j, Y g:i A') }}@if ($finalized_by) by {{ $finalized_by }}@endif @endif.
    Pay = each person's main pay line (sales split, profit share, hourly…); Wages = hourly and shipments paid on their own line; Tips = tips paid on this run.
</p>

@include('pdf.partials.brand-footer', ['footerLabel' => 'Pay Run Report'])
</body>
</html>
