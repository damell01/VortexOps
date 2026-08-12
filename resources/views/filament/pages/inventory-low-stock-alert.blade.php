<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #333;
            line-height: 1.5;
            padding: 20mm;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #dc2626;
        }

        .header h1 {
            font-size: 28px;
            color: #dc2626;
            margin-bottom: 5px;
        }

        .header p {
            color: #6b7280;
            font-size: 13px;
        }

        .alert-box {
            background: #fee2e2;
            border: 2px solid #dc2626;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .alert-box p {
            color: #991b1b;
            font-size: 12px;
            line-height: 1.6;
        }

        .summary {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }

        .summary-card {
            border: 1px solid #d1d5db;
            padding: 12px;
            background: #f9fafb;
            border-radius: 6px;
            text-align: center;
        }

        .summary-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .summary-value {
            font-size: 20px;
            font-weight: bold;
            color: #1f2937;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 20px;
        }

        table thead {
            background: #dc2626;
            color: white;
        }

        table th {
            padding: 10px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #dc2626;
        }

        table td {
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
        }

        table tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-semibold {
            font-weight: 600;
        }

        .critical {
            color: #dc2626;
            font-weight: 600;
        }

        .warning {
            color: #d97706;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
        }

        .action-box {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 6px;
            padding: 12px;
            margin: 15px 0;
            font-size: 11px;
            color: #92400e;
        }

        .action-box strong {
            color: #78350f;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚠️ Low Stock Alert</h1>
        <p>{{ $date }} at {{ $time }}</p>
        <p style="font-size: 12px; margin-top: 5px;">Immediate action required on {{ $count }} items</p>
    </div>

    <div class="alert-box">
        <p>
            <strong>URGENT:</strong> The following items are below their reorder levels and require immediate attention to prevent stockouts.
            Process purchase orders immediately to replenish inventory.
        </p>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="summary-label">Items to Reorder</div>
            <div class="summary-value critical">{{ $count }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Total Units Short</div>
            <div class="summary-value">{{ number_format(collect($items)->sum('current_qty')) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Tied Up Value</div>
            <div class="summary-value">${{ number_format($totalValue, 2) }}</div>
        </div>
    </div>

    <div class="action-box">
        <strong>🎯 Action Items:</strong>
        <ul style="margin-left: 15px; margin-top: 5px;">
            <li>Review suggested reorder quantities below</li>
            <li>Contact vendors immediately for expedited shipping</li>
            <li>Update warehouse staff on critical items</li>
            <li>Monitor shipments for delivery status</li>
        </ul>
    </div>

    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Item Name</th>
                <th class="text-right">Current Qty</th>
                <th class="text-right">Reorder Level</th>
                <th class="text-right">Short By</th>
                <th class="text-right">Suggested Qty</th>
                <th class="text-right">Avg Cost</th>
                <th class="text-right">Est. Cost</th>
                <th>Priority</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                @php
                    $shortBy = $item['reorder_level'] - $item['current_qty'];
                    $estCost = $item['suggested_qty'] * $item['avg_cost'];
                    $priority = $shortBy > ($item['reorder_level'] * 0.5) ? 'CRITICAL' : 'HIGH';
                @endphp
                <tr>
                    <td class="font-semibold">{{ $item['sku'] }}</td>
                    <td>{{ $item['name'] }}</td>
                    <td class="text-right critical">{{ number_format($item['current_qty']) }}</td>
                    <td class="text-right">{{ number_format($item['reorder_level']) }}</td>
                    <td class="text-right warning">{{ number_format($shortBy) }}</td>
                    <td class="text-right font-semibold">{{ number_format($item['suggested_qty']) }}</td>
                    <td class="text-right">${{ number_format($item['avg_cost'], 4) }}</td>
                    <td class="text-right font-semibold">${{ number_format($estCost, 2) }}</td>
                    <td class="text-center">
                        <span style="display: inline-block; padding: 3px 8px; border-radius: 3px; font-weight: 600;
                            {{ $priority === 'CRITICAL' ? 'background: #dc2626; color: white;' : 'background: #fcd34d; color: #92400e;' }}">
                            {{ $priority }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <div class="summary-card">
            <div class="summary-label">Total Reorder Cost</div>
            <div class="summary-value">${{ number_format(collect($items)->sum(fn($i) => $i['suggested_qty'] * $i['avg_cost']), 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Avg Days to Zero</div>
            <div class="summary-value">{{ number_format(collect($items)->avg('current_qty') / max(1, collect($items)->count()), 1) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Status</div>
            <div class="summary-value" style="color: #dc2626;">⚠️ ACTION</div>
        </div>
    </div>

    <div class="action-box">
        <strong>📋 Recommended Next Steps:</strong>
        <ol style="margin-left: 20px; margin-top: 5px;">
            <li>Sort items by Priority (CRITICAL first)</li>
            <li>Group by vendor for bulk ordering</li>
            <li>Negotiate expedited shipping if available</li>
            <li>Set daily monitoring for most critical items</li>
            <li>Review and adjust reorder levels based on sales velocity</li>
        </ol>
    </div>

    <div class="footer">
        <p>This is an automated alert from VortexOps Inventory Management System.</p>
        <p>For questions, please check the system or contact inventory management team.</p>
        <p style="margin-top: 10px; font-size: 9px;">Report generated: {{ $date }} at {{ $time }}</p>
    </div>
</body>
</html>
