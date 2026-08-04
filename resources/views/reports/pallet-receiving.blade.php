<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pallet Receiving Report</title>
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
            font-size: 11px;
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
        .text-center {
            text-align: center;
        }
        .totals {
            background-color: #e8e8e8;
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
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        .status-received {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Pallet Receiving Report</h1>
        <p>PO: {{ $pallet->reference }} | Generated: {{ date('M d, Y H:i:s') }}</p>
    </div>

    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Vendor:</div>
            <div class="info-value">{{ $pallet->vendor?->name ?? '—' }}</div>
            <div class="info-label">Status:</div>
            <div class="info-value">{{ ucfirst($pallet->status) }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Received Date:</div>
            <div class="info-value">{{ $pallet->received_date?->format('M d, Y') ?? 'Pending' }}</div>
            <div class="info-label">Staged At:</div>
            <div class="info-value">{{ $pallet->staged_at?->format('M d, Y H:i') ?? '—' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Receiving Sessions:</div>
            <div class="info-value">{{ count($sessions) }} session(s)</div>
            <div class="info-label">Packing Slips:</div>
            <div class="info-value">{{ count($pallet->packingSlips) }}</div>
        </div>
    </div>

    <h3>Received Line Items</h3>
    <table>
        <thead>
            <tr>
                <th>Item Name</th>
                <th>SKU</th>
                <th style="text-align: right;">Cases</th>
                <th style="text-align: right;">Quantity</th>
                <th style="text-align: right;">Unit Cost</th>
                <th style="text-align: right;">Total Cost</th>
                <th style="text-align: center;">Confidence</th>
                <th>Current Avg</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
            <tr>
                <td>{{ $line['item_name'] }}</td>
                <td>{{ $line['sku'] }}</td>
                <td class="text-right">{{ $line['cases'] }}</td>
                <td class="text-right">{{ number_format($line['qty'], 2) }}</td>
                <td class="text-right">${{ $line['unit_cost'] }}</td>
                <td class="text-right">${{ $line['total_cost'] }}</td>
                <td class="text-center"><span class="status-badge status-received">{{ $line['confidence'] }}</span></td>
                <td class="text-right">${{ $line['current_avg'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align: center;">No items received</td>
            </tr>
            @endforelse
            <tr class="totals">
                <td colspan="2">TOTALS</td>
                <td class="text-right">{{ $totals['cases'] }}</td>
                <td class="text-right">{{ number_format($totals['qty'], 2) }}</td>
                <td class="text-right"></td>
                <td class="text-right">${{ $totals['total_cost'] }}</td>
                <td colspan="2"></td>
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

    @if(count($sessions) > 0)
    <h3>Receiving Sessions</h3>
    <table>
        <thead>
            <tr>
                <th>Session ID</th>
                <th>Scanned By</th>
                <th>Started</th>
                <th>Ended</th>
                <th style="text-align: right;">Items Scanned</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sessions as $session)
            <tr>
                <td>{{ $session->id }}</td>
                <td>{{ $session->user?->name ?? '—' }}</td>
                <td>{{ $session->started_at?->format('M d H:i') ?? '—' }}</td>
                <td>{{ $session->ended_at?->format('M d H:i') ?? '—' }}</td>
                <td class="text-right">{{ $session->items_scanned ?? 0 }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        <p>This report was auto-generated and serves as a formal record of the pallet receiving.</p>
    </div>
</body>
</html>
