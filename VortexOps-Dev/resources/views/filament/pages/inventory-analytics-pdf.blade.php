<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; line-height: 1.5; }
        .page { page-break-after: always; margin-bottom: 40px; }

        .header {
            border-bottom: 3px solid #7c3aed;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 { font-size: 28px; color: #7c3aed; margin-bottom: 5px; }
        .header p { font-size: 12px; color: #666; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            background: #f9fafb;
        }
        .stat-label { font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; }
        .stat-value { font-size: 22px; font-weight: bold; color: #7c3aed; margin-top: 8px; }

        .section { margin-bottom: 30px; }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #111;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background: #f3f4f6;
            padding: 10px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            border-bottom: 2px solid #e5e7eb;
            color: #374151;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
        }
        tr:nth-child(even) { background: #f9fafb; }

        .alert-high { color: #dc2626; font-weight: bold; }
        .alert-medium { color: #ea580c; font-weight: bold; }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 10px;
            color: #666;
            text-align: center;
        }

        @media print {
            .page { page-break-after: always; }
        }
    </style>
</head>
<body>
    <!-- Summary Page -->
    <div class="page">
        <div class="header">
            <h1>{{ $title }}</h1>
            <p>Generated on {{ $date }} at {{ $time }}</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Inventory Value</div>
                <div class="stat-value">${{ number_format($summary['total_value'], 0) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Units</div>
                <div class="stat-value">{{ number_format($summary['total_units']) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Items</div>
                <div class="stat-value">{{ $summary['total_items'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Low Stock</div>
                <div class="stat-value" style="color: {{ $summary['low_stock_count'] > 0 ? '#dc2626' : '#16a34a' }}">
                    {{ $summary['low_stock_count'] }}
                </div>
            </div>
        </div>

        <!-- Low Stock Items -->
        @if(!empty($lowStock))
        <div class="section">
            <div class="section-title">⚠️ Low Stock Items</div>
            <table>
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>SKU</th>
                        <th>Current</th>
                        <th>Reorder Level</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lowStock as $item)
                    <tr>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['sku'] }}</td>
                        <td>{{ $item['current'] }}</td>
                        <td>{{ $item['reorder'] }}</td>
                        <td>
                            <span class="{{ $item['status'] === 'out_of_stock' ? 'alert-high' : 'alert-medium' }}">
                                {{ $item['status'] === 'out_of_stock' ? 'OUT OF STOCK' : 'LOW STOCK' }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Top Vendors -->
        @if(!empty($topVendors))
        <div class="section">
            <div class="section-title">🏪 Top Vendors</div>
            <table>
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th>Items Supplied</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topVendors as $vendor)
                    <tr>
                        <td>{{ $vendor['name'] }}</td>
                        <td>{{ $vendor['items_count'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="footer">
            Page 1 of 2 &bull; Confidential &bull; {{ config('app.name') }}
        </div>
    </div>

    <!-- Details Page -->
    <div class="page">
        <div class="header">
            <h1>Detailed Metrics</h1>
            <p>continued from page 1</p>
        </div>

        <!-- Fast Movers -->
        @if(!empty($fastMovers))
        <div class="section">
            <div class="section-title">🚀 High Value Items</div>
            <table>
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Location</th>
                        <th>Quantity</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fastMovers as $item)
                    <tr>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['location'] }}</td>
                        <td>{{ $item['quantity'] }}</td>
                        <td>${{ number_format($item['value'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Dead Stock -->
        @if(!empty($deadStock))
        <div class="section">
            <div class="section-title">💀 Dead Stock (Consider for Clearance)</div>
            <table>
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>SKU</th>
                        <th>Quantity</th>
                        <th>Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($deadStock as $item)
                    <tr>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['sku'] }}</td>
                        <td>{{ $item['quantity'] }}</td>
                        <td>${{ number_format($item['value'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Location Health -->
        @if(!empty($locations))
        <div class="section">
            <div class="section-title">📍 Location Health Summary</div>
            <table>
                <thead>
                    <tr>
                        <th>Location</th>
                        <th>Total Units</th>
                        <th>Unique Items</th>
                        <th>Location Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($locations as $location)
                    <tr>
                        <td>{{ $location['name'] }}</td>
                        <td>{{ number_format($location['total_units']) }}</td>
                        <td>{{ $location['unique_items'] }}</td>
                        <td>${{ number_format($location['value'], 0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="footer">
            Page 2 of 2 &bull; Confidential &bull; {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
