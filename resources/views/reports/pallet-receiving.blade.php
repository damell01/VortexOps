<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>VortexOps Pallet Receiving Report</title>
    <style>
        @page { margin: 22px 28px 28px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color:#172033; font-size:10px; line-height:1.35; margin:0; }
        .summary { margin:14px 0 16px; border:1px solid #e5e7eb; }
        .summary table { width:100%; border-collapse:collapse; margin:0; }
        .summary td { padding:7px 8px; border-bottom:1px solid #eef2f7; }
        .summary tr:last-child td { border-bottom:0; }
        .label { width:17%; font-weight:700; color:#64748b; }
        .value { width:33%; font-weight:700; color:#111827; }
        h3 { margin:16px 0 7px; font-size:13px; color:#111827; }
        table.lines { width:100%; border-collapse:collapse; margin:0; font-size:8.8px; table-layout:fixed; }
        table.lines th { background:#f5f3ff; color:#4c1d95; padding:6px 5px; text-align:left; border:1px solid #ddd6fe; font-weight:800; }
        table.lines td { padding:6px 5px; border:1px solid #e5e7eb; vertical-align:top; }
        table.lines tr:nth-child(even) td { background:#fafafa; }
        .item-col { width:29%; }
        .sku-col { width:16%; }
        .qty-col { width:12%; }
        .cost-col { width:11%; }
        .right { text-align:right!important; }
        .center { text-align:center!important; }
        .muted { color:#64748b; font-size:8px; }
        .totals td { background:#ede9fe!important; color:#4c1d95; font-weight:800; border-color:#ddd6fe!important; }
        .summary-cards { margin-top:12px; width:100%; border-collapse:separate; border-spacing:5px 0; }
        .summary-cards td { width:25%; padding:8px; border:1px solid #e5e7eb; background:#fafafa; }
        .metric-label { font-size:7.5px; color:#64748b; text-transform:uppercase; }
        .metric-value { margin-top:2px; font-size:12px; font-weight:800; color:#111827; }
        .invoice-total { margin-top:10px; padding:9px 10px; background:#f8fafc; border-left:4px solid #7c3aed; font-size:10px; }
        .invoice-total strong { font-size:12px; color:#4c1d95; }
    </style>
</head>
<body>
    @include('pdf.partials.brand-header', [
        'reportTitle' => 'Pallet Receiving Report',
        'reportSubtitle' => 'PO / Reference ' . ($pallet->reference ?: '—'),
        'generatedAt' => $generatedAt,
    ])

    <div class="summary">
        <table>
            <tr>
                <td class="label">Vendor</td><td class="value">{{ $pallet->vendor?->name ?? '—' }}</td>
                <td class="label">Status</td><td class="value">{{ $pallet->status === 'received' ? 'Received / Complete' : ucfirst($pallet->status) }}</td>
            </tr>
            <tr>
                <td class="label">Received</td><td class="value">{{ $pallet->received_date?->format('M j, Y') ?? 'Pending' }}</td>
                <td class="label">Reference</td><td class="value">{{ $pallet->reference ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">Line Items</td><td class="value">{{ count($lines) }}</td>
                <td class="label">Packing Slips</td><td class="value">{{ count($pallet->packingSlips) }}</td>
            </tr>
        </table>
    </div>

    <h3>Received Line Items</h3>
    <table class="lines">
        <thead>
            <tr>
                <th class="item-col">Item</th>
                <th class="sku-col">SKU</th>
                <th class="qty-col right">Qty / Pack</th>
                <th class="qty-col right">Total Units</th>
                <th class="cost-col right">Unit Cost</th>
                <th class="cost-col right">Line Total</th>
                <th class="cost-col right">Current Avg</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                <tr>
                    <td>{{ $line['item_name'] }}</td>
                    <td>{{ $line['sku'] }}</td>
                    <td class="right">
                        {{ number_format($line['display_quantity'], 0) }} {{ $line['quantity_label'] }}
                        @if($line['pack_size'])<div class="muted">{{ number_format($line['pack_size'], 0) }} units / case</div>@endif
                    </td>
                    <td class="right">{{ number_format($line['total_units'], 0) }}</td>
                    <td class="right">${{ $line['unit_cost'] }}</td>
                    <td class="right">${{ $line['total_cost'] }}</td>
                    <td class="right">{{ $line['current_avg'] === '—' ? '—' : '$'.$line['current_avg'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="center">No received line items were recorded.</td></tr>
            @endforelse
            <tr class="totals">
                <td colspan="3">TOTALS</td>
                <td class="right">{{ number_format($totals['qty'], 0) }}</td>
                <td></td>
                <td class="right">${{ $totals['total_cost'] }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <table class="summary-cards">
        <tr>
            <td><div class="metric-label">Manifest Qty</div><div class="metric-value">{{ number_format($totals['package_qty'], 0) }}</div></td>
            <td><div class="metric-label">Total Units</div><div class="metric-value">{{ number_format($totals['qty'], 0) }}</div></td>
            <td><div class="metric-label">Calculated Cost</div><div class="metric-value">${{ $totals['total_cost'] }}</div></td>
            <td><div class="metric-label">Avg Unit Cost</div><div class="metric-value">${{ $totals['avg_cost'] }}</div></td>
        </tr>
    </table>

    @if((float)($pallet->total_cost ?? 0) > 0)
        <div class="invoice-total">Recorded pallet / invoice total: <strong>${{ number_format((float)$pallet->total_cost, 2) }}</strong></div>
    @endif

    @include('pdf.partials.brand-footer', ['footerLabel' => 'Pallet Receiving Record', 'generatedAt' => $generatedAt])
</body>
</html>
