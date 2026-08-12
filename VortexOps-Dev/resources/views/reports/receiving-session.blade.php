<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receiving Session Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            font-size: 12px;
            color: #666;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            font-size: 12px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 20%;
            padding: 5px;
            border-bottom: 1px solid #ddd;
        }
        .info-value {
            display: table-cell;
            width: 30%;
            padding: 5px;
            border-bottom: 1px solid #ddd;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 12px;
        }
        th {
            background-color: #f0f0f0;
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
        }
        td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .footer {
            margin-top: 20px;
            font-size: 10px;
            color: #666;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Receiving Session Report</h1>
        <p>Session ID: {{ $session->id }} | Generated: {{ date('M d, Y H:i:s') }}</p>
    </div>

    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Pallet Reference:</div>
            <div class="info-value">{{ $session->pallet->reference }}</div>
            <div class="info-label">Vendor:</div>
            <div class="info-value">{{ $session->pallet->vendor?->name ?? '—' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Scanned By:</div>
            <div class="info-value">{{ $session->user?->name ?? '—' }}</div>
            <div class="info-label">Mode:</div>
            <div class="info-value">{{ ucfirst($session->mode) }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Session Duration:</div>
            <div class="info-value">
                {{ $session->started_at?->format('M d, Y H:i') ?? '—' }}
                to
                {{ $session->ended_at?->format('H:i') ?? 'Ongoing' }}
            </div>
            <div class="info-label">Items Scanned:</div>
            <div class="info-value">{{ $session->items_scanned ?? 0 }}</div>
        </div>
    </div>

    <h3>Received Items</h3>
    <table>
        <thead>
            <tr>
                <th>Item Name</th>
                <th>SKU</th>
                <th style="text-align: right;">Cases</th>
                <th style="text-align: right;">Quantity</th>
                <th style="text-align: right;">Unit Cost</th>
                <th style="text-align: right;">Total Cost</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
            <tr>
                <td>{{ $item['name'] }}</td>
                <td>{{ $item['sku'] }}</td>
                <td class="text-right">{{ $item['cases'] }}</td>
                <td class="text-right">{{ number_format($item['qty'], 2) }}</td>
                <td class="text-right">${{ $item['unit_cost'] }}</td>
                <td class="text-right">${{ $item['total_cost'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center;">No items received</td>
            </tr>
            @endforelse
            <tr class="totals">
                <td colspan="2">TOTALS</td>
                <td class="text-right">{{ $totals['cases'] }}</td>
                <td class="text-right">{{ number_format($totals['qty'], 2) }}</td>
                <td class="text-right"></td>
                <td class="text-right">${{ $totals['total_cost'] }}</td>
            </tr>
        </tbody>
    </table>

    <div style="font-size: 12px; margin-top: 10px;">
        <strong>Summary:</strong>
        <ul style="margin: 5px 0;">
            <li>Total Cases: {{ $totals['cases'] }}</li>
            <li>Total Units: {{ number_format($totals['qty'], 2) }}</li>
            <li>Total Cost: ${{ $totals['total_cost'] }}</li>
            <li>Average Unit Cost: ${{ $totals['avg_cost'] }}</li>
        </ul>
    </div>

    <div class="footer">
        <p>This report was auto-generated and serves as a record of the receiving session.</p>
    </div>
</body>
</html>
