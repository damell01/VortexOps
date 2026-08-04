<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            font-size: 11px;
        }
        .header {
            border-bottom: 3px solid #1e40af;
            margin-bottom: 20px;
            padding-bottom: 15px;
        }
        .header h1 {
            font-size: 28px;
            color: #1e40af;
            margin-bottom: 5px;
        }
        .header p {
            color: #666;
            font-size: 12px;
        }
        .metrics {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .metric-box {
            border: 1px solid #e5e7eb;
            padding: 12px;
            border-radius: 6px;
            background: #f9fafb;
        }
        .metric-label {
            font-size: 10px;
            color: #6b7280;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .metric-value {
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
        }
        .metric-subtext {
            font-size: 9px;
            color: #9ca3af;
            margin-top: 3px;
        }
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #1e40af;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th {
            background: #f3f4f6;
            color: #1f2937;
            padding: 8px;
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            border-bottom: 1px solid #d1d5db;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        .alert-box {
            border-left: 4px solid #dc2626;
            background: #fef2f2;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .alert-box.warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }
        .alert-title {
            font-weight: bold;
            color: #7f1d1d;
            margin-bottom: 5px;
            font-size: 11px;
        }
        .alert-box.warning .alert-title {
            color: #92400e;
        }
        .empty-state {
            text-align: center;
            color: #9ca3af;
            padding: 15px;
            font-style: italic;
        }
        .breakdown-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 12px;
        }
        .breakdown-item {
            border: 1px solid #e5e7eb;
            padding: 10px;
            border-radius: 4px;
            background: #f9fafb;
        }
        .breakdown-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 11px;
        }
        .breakdown-value {
            color: #1e40af;
            font-weight: bold;
            margin-top: 3px;
        }
        .breakdown-qty {
            color: #6b7280;
            font-size: 9px;
            margin-top: 2px;
        }
        .page-break {
            page-break-after: always;
        }
        .footer {
            text-align: center;
            color: #9ca3af;
            font-size: 9px;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Inventory Report</h1>
        <p>Generated on {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>

    <!-- Key Metrics -->
    <div class="metrics">
        <div class="metric-box">
            <div class="metric-label">Total Inventory Value</div>
            <div class="metric-value">${{ number_format($currentSnapshot->total_value, 2) }}</div>
        </div>
        <div class="metric-box">
            <div class="metric-label">Total SKUs</div>
            <div class="metric-value">{{ $currentSnapshot->total_items }}</div>
        </div>
        <div class="metric-box">
            <div class="metric-label">Total Units</div>
            <div class="metric-value">{{ number_format($currentSnapshot->total_quantity) }}</div>
        </div>
        <div class="metric-box">
            <div class="metric-label">Stock Alerts</div>
            <div class="metric-value">{{ count($currentSnapshot->stock_outs ?? []) }}</div>
        </div>
    </div>

    <!-- Location Breakdown -->
    @if($currentSnapshot->location_breakdown)
        <div class="section">
            <div class="section-title">Inventory by Location</div>
            <table>
                <thead>
                    <tr>
                        <th>Location</th>
                        <th style="text-align: right;">Value</th>
                        <th style="text-align: right;">Units</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($currentSnapshot->location_breakdown as $locId => $loc)
                        <tr>
                            <td>{{ $loc['name'] ?? "Location $locId" }}</td>
                            <td style="text-align: right;">${{ number_format($loc['value'], 2) }}</td>
                            <td style="text-align: right;">{{ number_format($loc['quantity']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty-state">No location data available</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <!-- Item Type Breakdown -->
    @if($currentSnapshot->item_type_breakdown)
        <div class="section">
            <div class="section-title">Inventory by Type</div>
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th style="text-align: right;">Value</th>
                        <th style="text-align: right;">Units</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($currentSnapshot->item_type_breakdown as $type => $data)
                        <tr>
                            <td>{{ $type }}</td>
                            <td style="text-align: right;">${{ number_format($data['value'], 2) }}</td>
                            <td style="text-align: right;">{{ number_format($data['quantity']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty-state">No type data available</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <!-- Slow Moving Items -->
    @if($currentSnapshot->slow_moving_items && count($currentSnapshot->slow_moving_items) > 0)
        <div class="section">
            <div class="section-title">Slow-Moving Items (30+ days)</div>
            <div class="alert-box warning">
                <div class="alert-title">⚠️ Items with no movement in the last 30 days</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th style="text-align: right;">Quantity</th>
                        <th style="text-align: right;">Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($currentSnapshot->slow_moving_items as $item)
                        <tr>
                            <td>{{ $item['name'] }}</td>
                            <td style="text-align: right;">{{ $item['quantity'] }}</td>
                            <td style="text-align: right;">${{ number_format($item['value'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Stock Outs -->
    @if($currentSnapshot->stock_outs && count($currentSnapshot->stock_outs) > 0)
        <div class="section">
            <div class="section-title">Out of Stock Items</div>
            <div class="alert-box">
                <div class="alert-title">🔴 Items at zero quantity</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Item Name</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($currentSnapshot->stock_outs as $item)
                        <tr>
                            <td>{{ $item['sku'] }}</td>
                            <td>{{ $item['name'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Detailed Item Listing -->
    <div class="page-break"></div>
    <div class="section">
        <div class="section-title">Detailed Item Inventory</div>
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Item Name</th>
                    <th>Location</th>
                    <th style="text-align: right;">Qty</th>
                    <th style="text-align: right;">Unit Cost</th>
                    <th style="text-align: right;">Total Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse($itemDetails as $item)
                    <tr>
                        <td>{{ $item['sku'] }}</td>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['location'] }}</td>
                        <td style="text-align: right;">{{ number_format($item['quantity']) }} {{ $item['is_low_stock'] ? '⚠️' : '' }}</td>
                        <td style="text-align: right;">${{ number_format($item['unit_cost'], 2) }}</td>
                        <td style="text-align: right;"><strong>${{ number_format($item['total_value'], 2) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No inventory items found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>This is an automated inventory report. For questions or corrections, contact your inventory manager.</p>
    </div>
</body>
</html>
