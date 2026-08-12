<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inventory Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.4;
        }

        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .meta {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #666;
            margin-top: 10px;
        }

        .summary {
            display: flex;
            gap: 30px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 4px;
        }

        .summary-item {
            flex: 1;
        }

        .summary-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            font-weight: bold;
        }

        .summary-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-top: 3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th {
            background: #333;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            border: 1px solid #333;
        }

        td {
            padding: 10px 8px;
            border: 1px solid #ddd;
            font-size: 11px;
        }

        tr:nth-child(even) {
            background: #f9f9f9;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .sku {
            font-weight: bold;
            font-family: 'Courier New', monospace;
        }

        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            color: #999;
            text-align: center;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Inventory Report</h1>
        <div class="meta">
            <span>Generated on {{ $exportDate }} at {{ $exportTime }}</span>
            <span>Total Items: {{ $totalItems }}</span>
        </div>
    </div>

    <div class="summary">
        <div class="summary-item">
            <div class="summary-label">Total Items</div>
            <div class="summary-value">{{ $totalItems }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Inventory Value</div>
            <div class="summary-value">${{ number_format($totalValue, 2) }}</div>
        </div>
    </div>

    @if($items->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th class="text-right">Unit Cost</th>
                    <th class="text-right">Avg Cost</th>
                    <th class="text-center">Qty</th>
                    <th class="text-right">Value</th>
                    <th>Locations</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td class="sku">{{ $item->sku }}</td>
                        <td><strong>{{ $item->name }}</strong></td>
                        <td>{{ $item->category ?? '—' }}</td>
                        <td class="text-right">${{ number_format($item->unit_cost, 2) }}</td>
                        <td class="text-right">${{ number_format($item->average_cost, 2) }}</td>
                        <td class="text-center">
                            @php
                                $totalQty = $item->stock->sum('quantity') ?? 0;
                            @endphp
                            {{ number_format($totalQty, 2) }}
                        </td>
                        <td class="text-right">
                            ${{ number_format(($totalQty * ($item->average_cost ?? 0)), 2) }}
                        </td>
                        <td>
                            @php
                                $locations = $item->stock->pluck('location.name')->unique()->join(', ');
                            @endphp
                            {{ $locations ?: '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="no-data">
            No items found
        </div>
    @endif

    <div class="footer">
        <p>This is an automatically generated inventory report. For questions, contact administration.</p>
    </div>
</body>
</html>
