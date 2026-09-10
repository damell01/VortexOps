<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>VortexOps Pallet Receiving Report</title>
    <style>
        @page { margin: 22px 28px 28px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color:#172033; font-size:10px; line-height:1.35; margin:0; }
        .brandbar { background:#0f172a; color:#fff; padding:14px 16px; border-bottom:4px solid #7c3aed; }
        .brand-table { width:100%; border-collapse:collapse; margin:0; }
        .brand-table td { border:0; padding:0; vertical-align:middle; }
        .brand-logo { width:48px; height:48px; object-fit:contain; vertical-align:middle; }
        .brand-copy { display:inline-block; vertical-align:middle; margin-left:8px; }
        .brand-name { font-size:19px; font-weight:800; color:#fff; }
        .brand-sub { font-size:8px; color:#c4b5fd; text-transform:uppercase; letter-spacing:1.1px; }
        .report-title { text-align:right; font-size:15px; font-weight:800; color:#fff; }
        .report-meta { text-align:right; margin-top:3px; font-size:8px; color:#cbd5e1; }
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
        .item-col { width:28%; }
        .sku-col { width:17%; }
        .num-col { width:11%; }
        .cost-col { width:11%; }
        .right { text-align:right!important; }
        .center { text-align:center!important; }
        .totals td { background:#ede9fe!important; color:#4c1d95; font-weight:800; border-color:#ddd6fe!important; }
        .summary-cards { margin-top:12px; width:100%; border-collapse:separate; border-spacing:5px 0; }
        .summary-cards td { width:25%; padding:8px; border:1px solid #e5e7eb; background:#fafafa; }
        .metric-label { font-size:7.5px; color:#64748b; text-transform:uppercase; }
        .metric-value { margin-top:2px; font-size:12px; font-weight:800; color:#111827; }
        .invoice-total { margin-top:10px; padding:9px 10px; background:#f8fafc; border-left:4px solid #7c3aed; font-size:10px; }
        .invoice-total strong { font-size:12px; color:#4c1d95; }
        .footer { margin-top:16px; padding-top:7px; border-top:1px solid #e5e7eb; color:#94a3b8; font-size:8px; text-align:center; }
    </style>
</head>
<body>
    <div class="brandbar">
        <table class="brand-table">
            <tr>
                <td style="width:52%">
                    @if($logoData)
                        <img class="brand-logo" src="{{ $logoData }}" alt="VortexOps">
                    @endif
                    <span class="brand-copy"><span class="brand-name">VortexOps</span><br><span class="brand-sub">Inventory Operations</span></span>
                </td>
                <td style="width:48%">
                    <div class="report-title">Pallet Receiving Report</div>
                    <div class="report-meta">PO {{ $pallet->reference ?: '—' }} &nbsp;•&nbsp; {{ $generatedAt->format('M j, Y g:i A') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td class="label">Vendor</td><td class="value">{{ $pallet->vendor?->name ?? '—' }}</td>
                <td class="label">Status</td><td class="value">{{ ucfirst($pallet->status) }}</td>
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
                <th class="num-col right">Cases</th>
                <th class="num-col right">Units</th>
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
                    <td class="right">{{ number_format($line['cases']) }}</td>
                    <td class="right">{{ number_format($line['qty'], 0) }}</td>
                    <td class="right">${{ $line['unit_cost'] }}</td>
                    <td class="right">${{ $line['total_cost'] }}</td>
                    <td class="right">{{ $line['current_avg'] === '—' ? '—' : '$'.$line['current_avg'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="center">No received line items were recorded.</td></tr>
            @endforelse
            <tr class="totals">
                <td colspan="2">TOTALS</td>
                <td class="right">{{ number_format($totals['cases']) }}</td>
                <td class="right">{{ number_format($totals['qty'], 0) }}</td>
                <td></td>
                <td class="right">${{ $totals['total_cost'] }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <table class="summary-cards">
        <tr>
            <td><div class="metric-label">Total Cases</div><div class="metric-value">{{ number_format($totals['cases']) }}</div></td>
            <td><div class="metric-label">Total Units</div><div class="metric-value">{{ number_format($totals['qty'], 0) }}</div></td>
            <td><div class="metric-label">Calculated Cost</div><div class="metric-value">${{ $totals['total_cost'] }}</div></td>
            <td><div class="metric-label">Avg Unit Cost</div><div class="metric-value">${{ $totals['avg_cost'] }}</div></td>
        </tr>
    </table>

    @if((float)($pallet->total_cost ?? 0) > 0)
        <div class="invoice-total">Recorded pallet / invoice total: <strong>${{ number_format((float)$pallet->total_cost, 2) }}</strong></div>
    @endif

    <div class="footer">VortexOps • Pallet Receiving Record • Generated {{ $generatedAt->format('M j, Y') }}</div>
</body>
</html>
