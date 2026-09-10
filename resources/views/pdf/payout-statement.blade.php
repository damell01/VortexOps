<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>VortexOps Payout Statement</title>
<style>
  @page { margin:22px 28px 28px; }
  body { font-family:DejaVu Sans, sans-serif; font-size:11px; color:#172033; margin:0; }
  .section { margin-bottom:22px; }
  .section-title { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:#6d28d9; margin-bottom:7px; }
  table { width:100%; border-collapse:collapse; }
  th { text-align:left; font-size:9px; font-weight:800; text-transform:uppercase; color:#4c1d95; padding:7px 8px; background:#f5f3ff; border:1px solid #ddd6fe; }
  td { padding:8px; border:1px solid #e5e7eb; vertical-align:top; }
  .amount { text-align:right; }
  .total-row td { font-weight:800; background:#ede9fe; font-size:13px; color:#4c1d95; }
  .deduction { color:#dc2626; }
</style>
</head>
<body>
@include('pdf.partials.brand-header', [
    'reportTitle' => 'Payout Statement',
    'reportSubtitle' => ($payout->streamer?->name ?? 'Streamer') . ' • ' . ($payout->show?->title ?? 'Show'),
])

<div class="section">
  <div class="section-title">Show Details</div>
  <table>
    <tr><td style="width:50%"><strong>Show</strong><br>{{ $payout->show?->title ?? '—' }}</td><td><strong>Date</strong><br>{{ $payout->show?->show_date?->format('F j, Y') ?? '—' }}</td></tr>
    <tr><td><strong>Streamer</strong><br>{{ $payout->streamer?->name ?? '—' }}</td><td><strong>Payout Type</strong><br>{{ ucfirst(str_replace('_', ' ', $payout->payout_type)) }}</td></tr>
    @if($payout->routing_bank_label)<tr><td colspan="2"><strong>Routing / Bank Label</strong><br>{{ $payout->routing_bank_label }}</td></tr>@endif
  </table>
</div>

<div class="section">
  <div class="section-title">Earnings Breakdown</div>
  <table>
    <thead><tr><th>Description</th><th class="amount">Amount</th></tr></thead>
    <tbody>
      @if((float)$payout->base_pay)<tr><td>Base Pay</td><td class="amount">${{ number_format($payout->base_pay, 2) }}</td></tr>@endif
      @if((float)$payout->tips_pay)<tr><td>Tips</td><td class="amount">${{ number_format($payout->tips_pay, 2) }}</td></tr>@endif
      @if((float)$payout->deductions_total)<tr><td class="deduction">Deductions</td><td class="amount deduction">−${{ number_format($payout->deductions_total, 2) }}</td></tr>@endif
      @if($payout->notes)<tr><td colspan="2" style="font-size:9px;color:#64748b">Note: {{ $payout->notes }}</td></tr>@endif
    </tbody>
    <tfoot><tr class="total-row"><td>Net Pay</td><td class="amount">${{ number_format($payout->net_pay, 2) }}</td></tr></tfoot>
  </table>
</div>

@if($payout->show && (float)$payout->show->gross_revenue)
<div class="section">
  <div class="section-title">Show Performance</div>
  <table><tr>
    <td style="width:33%"><strong>Gross Revenue</strong><br>${{ number_format($payout->show->gross_revenue, 2) }}</td>
    <td style="width:33%"><strong>Units Sold</strong><br>{{ $payout->show->units_sold ?? '—' }}</td>
    <td><strong>Buyers</strong><br>{{ $payout->show->buyers_count ?? '—' }}</td>
  </tr></table>
</div>
@endif

@include('pdf.partials.brand-footer', ['footerLabel' => 'Payout Statement'])
</body>
</html>
