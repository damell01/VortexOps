<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>VortexOps Pallet Receiving Report</title>
    <style>
        @page { margin: 20px 26px 28px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color:#172033; font-size:10px; line-height:1.35; margin:0; background:#fff; }
        .hero { width:100%; border-collapse:collapse; margin:0 0 14px; }
        .hero td { border:0; vertical-align:top; }
        .info-card { border:1px solid #e5e7eb; border-radius:8px; background:#fff; padding:10px 12px; }
        .info-grid { width:100%; border-collapse:collapse; }
        .info-grid td { width:25%; padding:5px 8px; border:0; }
        .eyebrow { color:#64748b; font-size:7.5px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
        .info-value { margin-top:2px; color:#111827; font-size:10.5px; font-weight:800; }
        .status { display:inline-block; padding:2px 7px; border-radius:999px; background:#dcfce7; color:#166534; font-size:8px; font-weight:800; }
        h3 { margin:16px 0 4px; font-size:14px; color:#111827; }
        .section-sub { margin:0 0 8px; color:#64748b; font-size:8.5px; }
        table.lines { width:100%; border-collapse:collapse; margin:0; font-size:8.4px; table-layout:fixed; }
        table.lines th { background:#172033; color:#fff; padding:7px 5px; text-align:left; border:1px solid #273449; font-weight:800; }
        table.lines td { padding:7px 5px; border:1px solid #e5e7eb; vertical-align:top; }
        table.lines tr:nth-child(even) td { background:#fafafa; }
        .item-col { width:29%; }
        .sku-col { width:15%; }
        .package-col { width:13%; }
        .qty-col { width:10%; }
        .cost-col { width:11%; }
        .right { text-align:right!important; }
        .center { text-align:center!important; }
        .muted { color:#64748b; font-size:7.4px; margin-top:1px; }
        .totals td { background:#ede9fe!important; color:#4c1d95; font-weight:800; border-color:#ddd6fe!important; }
        .summary-cards { margin-top:12px; width:100%; border-collapse:separate; border-spacing:5px 0; }
        .summary-cards td { width:25%; padding:9px; border:1px solid #e5e7eb; background:#fafafa; vertical-align:top; }
        .summary-cards td.primary { background:#f5f3ff; border-color:#ddd6fe; }
        .metric-label { font-size:7.2px; color:#64748b; text-transform:uppercase; font-weight:700; }
        .metric-value { margin-top:2px; font-size:13px; font-weight:800; color:#111827; }
        .primary .metric-value { color:#5b21b6; }
        .invoice-total { margin-top:10px; padding:9px 10px; background:#f8fafc; border-left:4px solid #7c3aed; font-size:9px; }
        .invoice-total strong { font-size:11px; color:#4c1d95; }
        .note { margin-top:7px; color:#64748b; font-size:7.5px; }
    </style>
</head>
<body>
    @include('pdf.partials.brand-header', [
        'reportTitle' => 'Pallet Receiving Report',
        'reportSubtitle' => 'PO ' . ($pallet->reference ?: '—') . '  •  ' . ($pallet->status === 'received' ? 'RECEIVED / COMPLETE' : strtoupper((string)$pallet->status)),
        'generatedAt' => $generatedAt,
    ])

    <div class="info-card">
        <table class="info-grid">
            <tr>
                <td><div class="eyebrow">Vendor</div><div class="info-value">{{ $pallet->vendor?->name ?? '—' }}</div></td>
                <td><div class="eyebrow">Status</div><div class="info-value"><span class="status">{{ $pallet->status === 'received' ? 'Received' : ucfirst($pallet->status) }}</span></div></td>
                <td><div class="eyebrow">Received Date</div><div class="info-value">{{ $pallet->received_date?->format('M j, Y') ?? 'Pending' }}</div></td>
                <td><div class="eyebrow">Reference</div><div class="info-value">{{ $pallet->reference ?: '—' }}</div></td>
            </tr>
        </table>
    </div>

    <h3>Received Line Items</h3>
    <div class="section-sub">Quantities use the same packaging terminology as the pallet manifest.</div>

    <table class="lines">
        <thead>
            <tr>
                <th class="item-col">Item</th>
                <th class="sku-col">SKU</th>
                <th class="package-col">Packaging</th>
                <th class="qty-col right">Quantity</th>
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
                    <td>
                        {{ $line['quantity_label'] === 'Single Units' ? 'Single item' : 'Case' }}
                        @if($line['pack_size'])
                            <div class="muted">{{ number_format($line['pack_size'], 0) }} units / case</div>
                        @endif
                    </td>
                    <td class="right">{{ number_format($line['display_quantity'], 0) }}</td>
                    <td class="right">${{ $line['unit_cost'] }}</td>
                    <td class="right">${{ $line['total_cost'] }}</td>
                    <td class="right">{{ $line['current_avg'] === '—' ? '—' : '$'.$line['current_avg'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="center">No received line items were recorded.</td></tr>
            @endforelse
            <tr class="totals">
                <td colspan="5">CALCULATED PALLET TOTAL</td>
                <td class="right">${{ $totals['total_cost'] }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <table class="summary-cards">
        <tr>
            <td class="primary"><div class="metric-label">Line Items</div><div class="metric-value">{{ number_format(count($lines)) }}</div></td>
            <td><div class="metric-label">Inventory Units Received</div><div class="metric-value">{{ number_format($totals['qty'], 0) }}</div></td>
            <td><div class="metric-label">Calculated Cost</div><div class="metric-value">${{ $totals['total_cost'] }}</div></td>
            <td><div class="metric-label">Avg Unit Cost</div><div class="metric-value">${{ $totals['avg_cost'] }}</div></td>
        </tr>
    </table>

    @if((float)($pallet->total_cost ?? 0) > 0)
        <div class="invoice-total">
            Recorded pallet / invoice total: <strong>${{ number_format((float)$pallet->total_cost, 2) }}</strong>
            @if(abs((float)$pallet->total_cost - (float)str_replace(',', '', $totals['total_cost'])) >= .01)
                <span style="color:#64748b;"> · Difference from calculated line total: ${{ number_format((float)$pallet->total_cost - (float)str_replace(',', '', $totals['total_cost']), 2) }}</span>
            @endif
        </div>
    @endif

    <div class="note">For case-pack items, Quantity is the number of cases from the manifest and the package size appears below Packaging. Inventory Units Received reflects the actual unit count credited to inventory.</div>

    @include('pdf.partials.brand-footer', ['footerLabel' => 'Pallet Receiving Record', 'generatedAt' => $generatedAt])
</body>
</html>
