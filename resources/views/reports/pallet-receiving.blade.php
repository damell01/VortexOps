<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>VortexOps Pallet Receiving Report</title>
    <style>
        @page { margin: 26px 30px 30px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color:#1f2937; font-size:11px; line-height:1.4; margin:0; }
        .brandbar { background:#111827; color:#fff; padding:14px 16px; border-bottom:4px solid #7c3aed; }
        .brand-table { width:100%; border-collapse:collapse; margin:0; }
        .brand-table td { border:0; padding:0; vertical-align:middle; }
        .brand-logo { width:42px; height:42px; object-fit:contain; margin-right:10px; }
        .brand-name { font-size:18px; font-weight:800; color:#fff; }
        .brand-sub { font-size:9px; color:#c4b5fd; text-transform:uppercase; letter-spacing:1px; }
        .report-title { text-align:right; font-size:16px; font-weight:800; color:#fff; }
        .report-meta { text-align:right; margin-top:3px; font-size:9px; color:#cbd5e1; }
        .summary { margin:16px 0; border:1px solid #e5e7eb; border-radius:6px; }
        .summary table { width:100%; border-collapse:collapse; margin:0; }
        .summary td { padding:7px 9px; border:0; border-bottom:1px solid #eef2f7; }
        .summary tr:last-child td { border-bottom:0; }
        .label { width:18%; font-weight:700; color:#64748b; }
        .value { width:32%; font-weight:600; }
        h3 { margin:18px 0 8px; font-size:14px; color:#111827; }
        table.lines { width:100%; border-collapse:collapse; margin:0; font-size:9.5px; }
        table.lines th { background:#f5f3ff; color:#4c1d95; padding:7px 6px; text-align:left; border:1px solid #ddd6fe; font-weight:800; }
        table.lines td { padding:7px 6px; border:1px solid #e5e7eb; }
        table.lines tr:nth-child(even) td { background:#fafafa; }
        .right { text-align:right!important; }
        .center { text-align:center!important; }
        .totals td { background:#ede9fe!important; color:#4c1d95; font-weight:800; border-color:#ddd6fe!important; }
        .summary-cards { margin-top:12px; width:100%; border-collapse:separate; border-spacing:6px 0; }
        .summary-cards td { width:25%; padding:9px; border:1px solid #e5e7eb; background:#fafafa; }
        .metric-label { font-size:8px; color:#64748b; text-transform:uppercase; }
        .metric-value { margin-top:3px; font-size:13px; font-weight:800; color:#111827; }
        .footer { margin-top:18px; padding-top:8px; border-top:1px solid #e5e7eb; color:#94a3b8; font-size:8.5px; text-align:center; }
    </style>
</head>
<body>
    <div class="brandbar">
        <table class="brand-table">
            <tr>
                <td style="width:55%">
                    @if(file_exists(public_path('images/vb-logo-sidebar.svg')))
                        <img class="brand-logo" src="{{ public_path('images/vb-logo-sidebar.svg') }}" alt="VortexOps">
                    @endif
                    <span class="brand-name">VortexOps</span><br>
                    <span class="brand-sub">Inventory Operations</span>
                </td>
                <td style="width:45%">
                    <div class="report-title">Pallet Receiving Report</div>
                    <div class="report-meta">PO {{ $pallet->reference ?: '—' }} &nbsp;•&nbsp; Generated {{ date('M j, Y g:i A') }}</div>
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
                <td class="label">Sessions</td><td class="value">{{ count($sessions) }}</td>
                <td class="label">Packing Slips</td><td class="value">{{ count($pallet->packingSlips) }}</td>
            </tr>
        </table>
    </div>

    <h3>Received Line Items</h3>
    <table class="lines">
        <thead>
            <tr>
                <th>Item</th>
                <th>SKU</th>
                <th class="right">Cases</th>
                <th class="right">Quantity</th>
                <th class="right">Unit Cost</th>
                <th class="right">Total Cost</th>
                <th class="right">Current Avg</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                <tr>
                    <td>{{ $line['item_name'] }}</td>
                    <td>{{ $line['sku'] }}</td>
                    <td class="right">{{ $line['cases'] }}</td>
                    <td class="right">{{ number_format($line['qty'], 2) }}</td>
                    <td class="right">${{ $line['unit_cost'] }}</td>
                    <td class="right">${{ $line['total_cost'] }}</td>
                    <td class="right">${{ $line['current_avg'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="center">No received line items were recorded.</td></tr>
            @endforelse
            <tr class="totals">
                <td colspan="2">TOTALS</td>
                <td class="right">{{ $totals['cases'] }}</td>
                <td class="right">{{ number_format($totals['qty'], 2) }}</td>
                <td></td>
                <td class="right">${{ $totals['total_cost'] }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <table class="summary-cards">
        <tr>
            <td><div class="metric-label">Total Cases</div><div class="metric-value">{{ $totals['cases'] }}</div></td>
            <td><div class="metric-label">Total Units</div><div class="metric-value">{{ number_format($totals['qty'], 2) }}</div></td>
            <td><div class="metric-label">Total Cost</div><div class="metric-value">${{ $totals['total_cost'] }}</div></td>
            <td><div class="metric-label">Avg Unit Cost</div><div class="metric-value">${{ $totals['avg_cost'] }}</div></td>
        </tr>
    </table>

    @if(count($sessions) > 0)
        <h3>Receiving Sessions</h3>
        <table class="lines">
            <thead><tr><th>Session</th><th>Received By</th><th>Started</th><th>Ended</th><th class="right">Items Scanned</th></tr></thead>
            <tbody>
                @foreach($sessions as $session)
                    <tr>
                        <td>#{{ $session->id }}</td>
                        <td>{{ $session->user?->name ?? '—' }}</td>
                        <td>{{ $session->started_at?->format('M j, g:i A') ?? '—' }}</td>
                        <td>{{ $session->ended_at?->format('M j, g:i A') ?? '—' }}</td>
                        <td class="right">{{ $session->items_scanned ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">VortexOps • Pallet Receiving Record • Generated automatically from inventory receiving data</div>
</body>
</html>
