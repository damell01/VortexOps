<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Show P&amp;L — {{ $show->title ?? 'Show #'.$show->id }}</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #111; margin: 0; padding: 32px; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; border-bottom: 2px solid #7C3AED; padding-bottom: 18px; }
  .brand { font-size: 22px; font-weight: bold; color: #7C3AED; } .brand-sub { font-size: 12px; color: #666; margin-top: 2px; }
  .doc-title { font-size: 14px; font-weight: bold; text-align: right; } .doc-meta { font-size: 11px; color: #666; text-align: right; margin-top: 4px; }
  .section { margin-bottom: 22px; } .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .08em; color: #7C3AED; margin-bottom: 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
  .meta-grid { display: flex; gap: 32px; flex-wrap: wrap; margin-bottom: 22px; } .meta-item { min-width: 140px; }
  .meta-label,.pl-label { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .06em; color: #9ca3af; margin-bottom: 2px; }
  .meta-value { font-size: 13px; font-weight: 600; color: #111; } table { width: 100%; border-collapse: collapse; }
  th { text-align: left; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; padding: 6px 8px; background: #f5f3ff; border-bottom: 1px solid #ddd; }
  td { padding: 7px 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; font-size: 12px; } .amount,.right { text-align: right; }
  .pl-grid { display: flex; gap: 0; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; margin-bottom: 22px; }
  .pl-cell { flex: 1; padding: 14px 16px; border-right: 1px solid #e5e7eb; } .pl-cell:last-child { border-right: none; }
  .pl-value { font-size: 18px; font-weight: 800; color: #111; } .pl-value.green { color:#059669; } .pl-value.red { color:#dc2626; } .pl-value.orange { color:#d97706; } .pl-value.purple { color:#7c3aed; }
  .pl-sub { font-size: 11px; color:#6b7280; margin-top:2px; } .margin-pos { color:#059669; } .margin-neg { color:#dc2626; }
  .badge { display:inline-block; padding:2px 7px; border-radius:99px; font-size:10px; font-weight:bold; } .badge-success { background:#dcfce7;color:#166534; } .badge-warning { background:#fef9c3;color:#854d0e; } .badge-info { background:#dbeafe;color:#1e40af; }
  .total-row td { font-weight:bold; background:#f5f3ff; } .footer { margin-top:36px; padding-top:14px; border-top:1px solid #e5e7eb; font-size:11px; color:#9ca3af; text-align:center; }
  .stage-alias { background:#d1fae5;color:#065f46; } .stage-fuzzy { background:#dbeafe;color:#1e40af; } .stage-embedding { background:#ede9fe;color:#5b21b6; } .stage-llm { background:#fef3c7;color:#92400e; } .stage-manual { background:#f3f4f6;color:#374151; }
</style>
</head>
<body>
<div class="header"><div><div class="brand">{{ config('app.name', 'VortexOps') }}</div><div class="brand-sub">Show P&amp;L Report</div></div><div><div class="doc-title">{{ $show->title ?? 'Show #'.$show->id }}</div><div class="doc-meta">Generated {{ now()->format('M j, Y g:i A') }}</div></div></div>
<div class="meta-grid">
  <div class="meta-item"><div class="meta-label">Show Date</div><div class="meta-value">{{ $show->show_date?->format('M j, Y') ?? '—' }}</div></div>
  <div class="meta-item"><div class="meta-label">Channel</div><div class="meta-value">{{ $show->channel?->name ?? '—' }}</div></div>
  <div class="meta-item"><div class="meta-label">Streamers</div><div class="meta-value">{{ $show->streamers->pluck('name')->join(', ') ?: '—' }}</div></div>
  <div class="meta-item"><div class="meta-label">Units Sold</div><div class="meta-value">{{ number_format((int) $show->units_sold) }}</div></div>
  <div class="meta-item"><div class="meta-label">Status</div><div class="meta-value">{{ \App\Models\Show::statusLabels()[$show->status] ?? $show->status }}</div></div>
</div>
@php
  $gross=(float)($show->gross_revenue??0); $net=(float)($show->whatnot_net??0); $tips=(float)($show->tips??0);
  $cogs=(float)($show->latestDeductionRequest?->lines?->sum('line_total')??0); $payouts=(float)($show->payouts?->sum('calculated_payout')??0);
  $margin=($net+$tips)-$cogs-$payouts; $base=$net+$tips; $pct=$base>0?round(($margin/$base)*100,1):0; $f=fn(float $v)=>'$'.number_format($v,2);
@endphp
<div class="section"><div class="section-title">P&amp;L Summary</div><div class="pl-grid">
  <div class="pl-cell"><div class="pl-label">Gross Revenue</div><div class="pl-value">{{ $f($gross) }}</div><div class="pl-sub">Whatnot Estimated Sales</div></div>
  <div class="pl-cell"><div class="pl-label">Estimated Net Earnings</div><div class="pl-value purple">{{ $f($net) }}</div><div class="pl-sub">Whatnot Total Estimated Earnings</div></div>
  <div class="pl-cell"><div class="pl-label">Tips</div><div class="pl-value green">{{ $f($tips) }}</div></div>
  <div class="pl-cell"><div class="pl-label">COGS</div><div class="pl-value red">{{ $f($cogs) }}</div></div>
  <div class="pl-cell"><div class="pl-label">Payouts</div><div class="pl-value orange">{{ $f($payouts) }}</div></div>
  <div class="pl-cell"><div class="pl-label">Net Margin</div><div class="pl-value {{ $margin>=0?'green':'red' }}">{{ $f($margin) }}</div><div class="pl-sub {{ $margin>=0?'margin-pos':'margin-neg' }}">{{ ($margin>=0?'+':'').$pct }}%</div></div>
</div></div>
@if ($show->payouts->isNotEmpty())
<div class="section"><div class="section-title">Streamer Payouts</div><table><thead><tr><th>Streamer</th><th>Payout Type</th><th class="right">Calculated</th><th>Status</th></tr></thead><tbody>
@foreach ($show->payouts as $payout)<tr><td>{{ $payout->streamer?->name ?? '—' }}</td><td>{{ \App\Models\Streamer::payoutTypeLabels()[$payout->payout_type] ?? $payout->payout_type }}</td><td class="amount">${{ number_format((float)$payout->calculated_payout,2) }}</td><td>@php $st=$payout->status??'draft'; @endphp<span class="badge badge-{{ $st==='paid'?'success':($st==='approved'?'info':'warning') }}">{{ ucfirst($st) }}</span></td></tr>@endforeach
</tbody></table></div>
@endif
@php $dr=$show->latestDeductionRequest; @endphp
@if ($dr && $dr->lines->isNotEmpty())
<div class="section"><div class="section-title">COGS Lines ({{ $dr->lines->count() }} items)</div><table><thead><tr><th>Description</th><th>Item</th><th>Stage</th><th class="right">Qty</th><th class="right">Unit Cost</th><th class="right">Line Total</th></tr></thead><tbody>
@foreach ($dr->lines as $line)<tr><td style="font-size:11px;color:#6b7280">{{ \Illuminate\Support\Str::limit($line->raw_description??'—',40) }}</td><td>{{ $line->inventoryItem?->name??'—' }}</td><td>@if($line->match_stage)<span class="badge stage-{{ $line->match_stage }}">{{ ucfirst($line->match_stage) }}</span>@else — @endif</td><td class="amount">{{ number_format((float)$line->quantity_approved,0) }}</td><td class="amount">${{ number_format((float)$line->unit_cost_snapshot,2) }}</td><td class="amount">${{ number_format((float)$line->line_total,2) }}</td></tr>@endforeach
<tr class="total-row"><td colspan="5">Total COGS</td><td class="amount">${{ number_format($cogs,2) }}</td></tr></tbody></table></div>
@endif
@if ($show->notes)<div class="section"><div class="section-title">Notes</div><p style="font-size:12px;color:#374151;white-space:pre-wrap;margin:0">{{ $show->notes }}</p></div>@endif
<div class="footer">{{ config('app.name','VortexOps') }} &mdash; Confidential &mdash; {{ now()->format('M j, Y') }}</div>
</body></html>
