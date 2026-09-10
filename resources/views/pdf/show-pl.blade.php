<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Show P&amp;L — {{ $show->title ?? 'Show #'.$show->id }}</title>
<style>
  @page { margin:22px 28px 28px; }
  body { font-family:DejaVu Sans, sans-serif; font-size:11px; color:#172033; margin:0; }
  .section { margin-bottom:20px; }
  .section-title { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:#6d28d9; margin-bottom:7px; border-bottom:1px solid #e5e7eb; padding-bottom:4px; }
  .meta-grid { width:100%; border-collapse:separate; border-spacing:5px 0; margin-bottom:18px; }
  .meta-grid td { width:20%; border:1px solid #e5e7eb; background:#fafafa; padding:8px; }
  .meta-label,.pl-label { font-size:8px; font-weight:800; text-transform:uppercase; color:#64748b; }
  .meta-value { font-size:10px; font-weight:700; color:#111827; margin-top:2px; }
  table { width:100%; border-collapse:collapse; }
  th { text-align:left; font-size:8.5px; font-weight:800; text-transform:uppercase; color:#4c1d95; padding:6px 7px; background:#f5f3ff; border:1px solid #ddd6fe; }
  td { padding:7px; border:1px solid #e5e7eb; vertical-align:top; font-size:10px; }
  .amount,.right { text-align:right; }
  .pl-grid { width:100%; border-collapse:separate; border-spacing:4px 0; margin-bottom:18px; }
  .pl-grid td { width:16.66%; background:#fafafa; border:1px solid #e5e7eb; padding:9px; }
  .pl-value { margin-top:2px; font-size:13px; font-weight:800; color:#111827; }
  .green { color:#059669; } .red { color:#dc2626; } .orange { color:#d97706; } .purple { color:#7c3aed; }
  .pl-sub { font-size:8px; color:#64748b; margin-top:2px; }
  .badge { display:inline-block; padding:2px 6px; border-radius:10px; font-size:8px; font-weight:800; }
  .badge-success { background:#dcfce7;color:#166534; } .badge-warning { background:#fef9c3;color:#854d0e; } .badge-info { background:#dbeafe;color:#1e40af; }
  .stage-alias { background:#d1fae5;color:#065f46; } .stage-fuzzy { background:#dbeafe;color:#1e40af; } .stage-embedding { background:#ede9fe;color:#5b21b6; } .stage-llm { background:#fef3c7;color:#92400e; } .stage-manual { background:#f3f4f6;color:#374151; }
  .total-row td { font-weight:800; background:#ede9fe; color:#4c1d95; }
</style>
</head>
<body>
@include('pdf.partials.brand-header', [
    'reportTitle' => 'Show P&L Report',
    'reportSubtitle' => $show->title ?? ('Show #'.$show->id),
])

<table class="meta-grid"><tr>
  <td><div class="meta-label">Show Date</div><div class="meta-value">{{ $show->show_date?->format('M j, Y') ?? '—' }}</div></td>
  <td><div class="meta-label">Channel</div><div class="meta-value">{{ $show->channel?->name ?? '—' }}</div></td>
  <td><div class="meta-label">Streamers</div><div class="meta-value">{{ $show->streamers->pluck('name')->join(', ') ?: '—' }}</div></td>
  <td><div class="meta-label">Units Sold</div><div class="meta-value">{{ number_format((int) $show->units_sold) }}</div></td>
  <td><div class="meta-label">Status</div><div class="meta-value">{{ \App\Models\Show::statusLabels()[$show->status] ?? $show->status }}</div></td>
</tr></table>

@php
  $gross=(float)($show->gross_revenue??0); $net=(float)($show->whatnot_net??0); $tips=(float)($show->tips??0);
  $cogs=(float)($show->latestDeductionRequest?->lines?->sum('line_total')??0); $payouts=(float)($show->payouts?->sum('calculated_payout')??0);
  $margin=($net+$tips)-$cogs-$payouts; $base=$net+$tips; $pct=$base>0?round(($margin/$base)*100,1):0; $f=fn(float $v)=>'$'.number_format($v,2);
@endphp

<div class="section"><div class="section-title">P&amp;L Summary</div>
<table class="pl-grid"><tr>
  <td><div class="pl-label">Gross Revenue</div><div class="pl-value">{{ $f($gross) }}</div><div class="pl-sub">Whatnot estimated sales</div></td>
  <td><div class="pl-label">Estimated Net</div><div class="pl-value purple">{{ $f($net) }}</div><div class="pl-sub">Estimated earnings</div></td>
  <td><div class="pl-label">Tips</div><div class="pl-value green">{{ $f($tips) }}</div></td>
  <td><div class="pl-label">COGS</div><div class="pl-value red">{{ $f($cogs) }}</div></td>
  <td><div class="pl-label">Payouts</div><div class="pl-value orange">{{ $f($payouts) }}</div></td>
  <td><div class="pl-label">Net Margin</div><div class="pl-value {{ $margin>=0?'green':'red' }}">{{ $f($margin) }}</div><div class="pl-sub">{{ ($margin>=0?'+':'').$pct }}%</div></td>
</tr></table></div>

@if ($show->payouts->isNotEmpty())
<div class="section"><div class="section-title">Streamer Payouts</div><table><thead><tr><th>Streamer</th><th>Payout Type</th><th class="right">Calculated</th><th>Status</th></tr></thead><tbody>
@foreach ($show->payouts as $payout)<tr><td>{{ $payout->streamer?->name ?? '—' }}</td><td>{{ \App\Models\Streamer::payoutTypeLabels()[$payout->payout_type] ?? $payout->payout_type }}</td><td class="amount">${{ number_format((float)$payout->calculated_payout,2) }}</td><td>@php $st=$payout->status??'draft'; @endphp<span class="badge badge-{{ $st==='paid'?'success':($st==='approved'?'info':'warning') }}">{{ ucfirst($st) }}</span></td></tr>@endforeach
</tbody></table></div>
@endif

@php $dr=$show->latestDeductionRequest; @endphp
@if ($dr && $dr->lines->isNotEmpty())
<div class="section"><div class="section-title">COGS Lines ({{ $dr->lines->count() }} items)</div><table><thead><tr><th>Description</th><th>Item</th><th>Stage</th><th class="right">Qty</th><th class="right">Unit Cost</th><th class="right">Line Total</th></tr></thead><tbody>
@foreach ($dr->lines as $line)<tr><td>{{ \Illuminate\Support\Str::limit($line->raw_description??'—',40) }}</td><td>{{ $line->inventoryItem?->name??'—' }}</td><td>@if($line->match_stage)<span class="badge stage-{{ $line->match_stage }}">{{ ucfirst($line->match_stage) }}</span>@else — @endif</td><td class="amount">{{ number_format((float)$line->quantity_approved,0) }}</td><td class="amount">${{ number_format((float)$line->unit_cost_snapshot,2) }}</td><td class="amount">${{ number_format((float)$line->line_total,2) }}</td></tr>@endforeach
<tr class="total-row"><td colspan="5">Total COGS</td><td class="amount">${{ number_format($cogs,2) }}</td></tr></tbody></table></div>
@endif

@if ($show->notes)<div class="section"><div class="section-title">Notes</div><p>{{ $show->notes }}</p></div>@endif
@include('pdf.partials.brand-footer', ['footerLabel' => 'Show P&L Report'])
</body></html>
