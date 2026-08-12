<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 9px; color: #333; }
        .page { page-break-after: always; padding: 15px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #2c3e50; padding-bottom: 10px; }
        .header h1 { font-size: 18px; color: #2c3e50; margin-bottom: 5px; }
        .header p { font-size: 8px; color: #666; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 11px; font-weight: bold; background: #ecf0f1; padding: 6px; margin-bottom: 8px; border-left: 4px solid #3498db; }
        .section-subtitle { font-size: 9px; font-weight: bold; margin-top: 10px; margin-bottom: 5px; color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th { background: #34495e; color: white; padding: 5px; text-align: left; font-size: 8px; font-weight: bold; }
        td { padding: 4px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) { background: #f9f9f9; }
        .stat-box { display: inline-block; width: 18%; margin-right: 2%; margin-bottom: 10px; background: #ecf0f1; padding: 8px; border-radius: 3px; }
        .stat-value { font-size: 14px; font-weight: bold; color: #2c3e50; }
        .stat-label { font-size: 7px; color: #666; }
        .currency { text-align: right; font-weight: bold; }
        .number { text-align: right; }
        .low-stock { color: #e74c3c; font-weight: bold; }
        .good-stock { color: #27ae60; }
        .highlight { background: #fff3cd; }
    </style>
</head>
<body>
    <!-- EXECUTIVE SUMMARY PAGE -->
    <div class="page">
        <div class="header">
            <h1>📊 Comprehensive Inventory Report</h1>
            <p>Generated {{ $date }} at {{ $time }}</p>
        </div>

        <div class="section">
            <div class="section-title">Executive Summary</div>
            <div class="stat-box">
                <div class="stat-value">{{ number_format($summary['itemDetails']->count()) }}</div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">${{ number_format($summary['currentSnapshot']->total_value ?? 0, 0) }}</div>
                <div class="stat-label">Inventory Value</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ $health['healthy'] }}</div>
                <div class="stat-label">Healthy Items</div>
            </div>
            <div class="stat-box">
                <div class="stat-value" style="color: #e74c3c;">{{ $health['low_stock'] }}</div>
                <div class="stat-label">Low Stock</div>
            </div>
            <div class="stat-box">
                <div class="stat-value" style="color: #e74c3c;">{{ $health['out_of_stock'] }}</div>
                <div class="stat-label">Out of Stock</div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Stock Health Overview</div>
            <table>
                <tr>
                    <th>Status</th>
                    <th>Count</th>
                    <th>% of Total</th>
                </tr>
                <tr>
                    <td class="good-stock">✓ Healthy</td>
                    <td class="number">{{ $health['healthy'] }}</td>
                    <td class="number">{{ number_format(($health['healthy'] / $health['total']) * 100, 1) }}%</td>
                </tr>
                <tr>
                    <td class="low-stock">⚠️ Low Stock</td>
                    <td class="number">{{ $health['low_stock'] }}</td>
                    <td class="number">{{ number_format(($health['low_stock'] / $health['total']) * 100, 1) }}%</td>
                </tr>
                <tr>
                    <td class="low-stock">🚫 Out of Stock</td>
                    <td class="number">{{ $health['out_of_stock'] }}</td>
                    <td class="number">{{ number_format(($health['out_of_stock'] / $health['total']) * 100, 1) }}%</td>
                </tr>
                <tr style="background: #ecf0f1; font-weight: bold;">
                    <td>Total Items</td>
                    <td class="number">{{ $health['total'] }}</td>
                    <td class="number">100%</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Inventory Trend (Last 30 Days)</div>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Inventory Value</th>
                </tr>
                @foreach($summary['trendData'] as $trend)
                <tr>
                    <td>{{ $trend['date'] }}</td>
                    <td class="currency">${{ number_format($trend['value'], 0) }}</td>
                </tr>
                @endforeach
            </table>
        </div>
    </div>

    <!-- FAST MOVERS & SLOW MOVERS PAGE -->
    <div class="page">
        <div class="header">
            <h1>🚀 Velocity Analysis</h1>
            <p>Top Fast Movers & Slow Movers</p>
        </div>

        <div class="section">
            <div class="section-title">Top 10 Fast Movers (High Velocity)</div>
            <table>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Qty</th>
                    <th>Daily Velocity</th>
                    <th>Days Coverage</th>
                </tr>
                @foreach(array_slice($fastMovers, 0, 10) as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['sku'] }}</td>
                    <td class="number">{{ number_format($item['quantity'], 0) }}</td>
                    <td class="number">{{ number_format($item['velocity'], 2) }}</td>
                    <td class="number">{{ $item['days_of_stock'] }}</td>
                </tr>
                @endforeach
            </table>
        </div>

        <div class="section">
            <div class="section-title">Top 10 Slow Movers (Low Velocity)</div>
            <table>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Qty</th>
                    <th>Daily Velocity</th>
                    <th>Days Coverage</th>
                </tr>
                @foreach(array_slice($slowMovers, 0, 10) as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['sku'] }}</td>
                    <td class="number">{{ number_format($item['quantity'], 0) }}</td>
                    <td class="number">{{ number_format($item['velocity'], 2) }}</td>
                    <td class="number">{{ $item['days_of_stock'] }}</td>
                </tr>
                @endforeach
            </table>
        </div>

        <div class="section">
            <div class="section-title">Dead Stock (No Movement)</div>
            <p>{{ count($deadStock) }} items with zero sales or movement in last 30 days</p>
            <table>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Qty</th>
                    <th>Unit Cost</th>
                    <th>Total Value</th>
                </tr>
                @foreach(array_slice($deadStock, 0, 15) as $item)
                <tr class="highlight">
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['sku'] }}</td>
                    <td class="number">{{ number_format($item['quantity'], 0) }}</td>
                    <td class="currency">${{ number_format($item['cost'], 2) }}</td>
                    <td class="currency">${{ number_format($item['quantity'] * $item['cost'], 2) }}</td>
                </tr>
                @endforeach
            </table>
        </div>
    </div>

    <!-- ABC ANALYSIS PAGE -->
    <div class="page">
        <div class="header">
            <h1>📈 ABC Analysis</h1>
            <p>Inventory Categorization by Value</p>
        </div>

        <div class="section">
            <div class="section-title">Class A - High Value Items (0-80% Cumulative Value)</div>
            <p>{{ $abcAnalysis['a_count'] }} items worth monitoring closely</p>
            <table>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Qty</th>
                    <th>Unit Cost</th>
                    <th>Total Value</th>
                </tr>
                @foreach($abcAnalysis['class_a'] as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['sku'] }}</td>
                    <td class="number">{{ number_format($item['qty'], 0) }}</td>
                    <td class="currency">${{ number_format($item['cost'], 2) }}</td>
                    <td class="currency">${{ number_format($item['value'], 2) }}</td>
                </tr>
                @endforeach
            </table>
        </div>

        <div class="section">
            <div class="section-title">Class B - Medium Value Items (80-95% Cumulative Value)</div>
            <p>{{ $abcAnalysis['b_count'] }} items for standard management</p>
            <table>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Qty</th>
                    <th>Unit Cost</th>
                    <th>Total Value</th>
                </tr>
                @foreach($abcAnalysis['class_b'] as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['sku'] }}</td>
                    <td class="number">{{ number_format($item['qty'], 0) }}</td>
                    <td class="currency">${{ number_format($item['cost'], 2) }}</td>
                    <td class="currency">${{ number_format($item['value'], 2) }}</td>
                </tr>
                @endforeach
            </table>
        </div>

        <div class="section">
            <div class="section-title">Class C - Low Value Items (95-100% Cumulative Value)</div>
            <p>{{ $abcAnalysis['c_count'] }} items for simplified management</p>
            <table>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Qty</th>
                    <th>Unit Cost</th>
                    <th>Total Value</th>
                </tr>
                @foreach($abcAnalysis['class_c'] as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['sku'] }}</td>
                    <td class="number">{{ number_format($item['qty'], 0) }}</td>
                    <td class="currency">${{ number_format($item['cost'], 2) }}</td>
                    <td class="currency">${{ number_format($item['value'], 2) }}</td>
                </tr>
                @endforeach
            </table>
        </div>
    </div>

    <!-- COVERAGE & LOCATIONS PAGE -->
    <div class="page">
        <div class="header">
            <h1>📍 Coverage & Location Analysis</h1>
            <p>Stock Coverage & Location Health</p>
        </div>

        <div class="section">
            <div class="section-title">Stock Coverage (Days of Inventory)</div>
            <table>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Qty</th>
                    <th>Daily Velocity</th>
                    <th>Days of Stock</th>
                </tr>
                @foreach($coverage as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['sku'] }}</td>
                    <td class="number">{{ number_format($item['quantity'], 0) }}</td>
                    <td class="number">{{ number_format($item['velocity'], 2) }}</td>
                    <td class="number">{{ $item['days_of_stock'] }}</td>
                </tr>
                @endforeach
            </table>
        </div>

        <div class="section">
            <div class="section-title">Location Health Report</div>
            <table>
                <tr>
                    <th>Location</th>
                    <th>Type</th>
                    <th>Items</th>
                    <th>Healthy</th>
                    <th>Low Stock</th>
                    <th>Out of Stock</th>
                    <th>Total Value</th>
                </tr>
                @foreach($locationHealth as $location)
                <tr>
                    <td>{{ $location['name'] }}</td>
                    <td>{{ ucfirst($location['type']) }}</td>
                    <td class="number">{{ $location['total_items'] }}</td>
                    <td class="number" style="color: #27ae60;">{{ $location['healthy'] }}</td>
                    <td class="number" style="color: #f39c12;">{{ $location['low_stock'] }}</td>
                    <td class="number" style="color: #e74c3c;">{{ $location['out_of_stock'] }}</td>
                    <td class="currency">${{ number_format($location['total_value'], 2) }}</td>
                </tr>
                @endforeach
            </table>
        </div>
    </div>

    <!-- BREAKDOWN PAGES -->
    <div class="page">
        <div class="header">
            <h1>📦 Category Breakdown</h1>
        </div>

        @foreach($categories as $category)
        <div class="section">
            <div class="section-title">{{ $category['category'] }}</div>
            <table>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Qty</th>
                    <th>Unit Cost</th>
                    <th>Total Value</th>
                </tr>
                @foreach(array_slice($category['items'], 0, 8) as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['sku'] }}</td>
                    <td class="number">{{ number_format($item['quantity'], 0) }}</td>
                    <td class="currency">${{ number_format($item['unit_cost'], 2) }}</td>
                    <td class="currency">${{ number_format($item['total_value'], 2) }}</td>
                </tr>
                @endforeach
            </table>
            <div style="margin-top: 8px; padding: 6px; background: #ecf0f1; font-size: 8px;">
                <strong>Subtotal:</strong> {{ $category['item_count'] }} items |
                <strong>Qty:</strong> {{ number_format($category['total_quantity'], 0) }} |
                <strong>Value:</strong> ${{ number_format($category['total_value'], 2) }}
            </div>
        </div>
        @endforeach
    </div>

    <!-- VENDOR & AGING PAGES -->
    <div class="page">
        <div class="header">
            <h1>🏭 Vendor Breakdown</h1>
        </div>

        @foreach(array_slice($vendors, 0, 6) as $vendor)
        <div class="section">
            <div class="section-title">{{ $vendor['vendor'] }}</div>
            <table>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Qty</th>
                    <th>Unit Cost</th>
                    <th>Total Value</th>
                </tr>
                @foreach(array_slice($vendor['items'], 0, 5) as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['sku'] }}</td>
                    <td class="number">{{ number_format($item['quantity'], 0) }}</td>
                    <td class="currency">${{ number_format($item['unit_cost'], 2) }}</td>
                    <td class="currency">${{ number_format($item['total_value'], 2) }}</td>
                </tr>
                @endforeach
            </table>
            <div style="margin-top: 6px; padding: 5px; background: #ecf0f1; font-size: 8px;">
                <strong>Total:</strong> ${{ number_format($vendor['total_value'], 2) }}
            </div>
        </div>
        @endforeach
    </div>

    <div class="page">
        <div class="header">
            <h1>⏰ Aging Inventory Analysis</h1>
        </div>

        @php
            $ageGroups = ['fresh', 'aging_30', 'aging_60', 'aging_90'];
            $ageLabels = ['Fresh (0-30 days)', 'Aging (31-60 days)', 'Aging (61-90 days)', 'Old (90+ days)'];
        @endphp

        @foreach($ageGroups as $key => $ageGroup)
        <div class="section">
            <div class="section-title">{{ $ageLabels[$loop->index] }}</div>
            <div style="margin-bottom: 8px; padding: 6px; background: #ecf0f1; font-size: 8px;">
                <strong>Count:</strong> {{ $aging[$key]['count'] }} |
                <strong>Qty:</strong> {{ number_format($aging[$key]['total_quantity'], 0) }} |
                <strong>Value:</strong> ${{ number_format($aging[$key]['total_value'], 2) }}
            </div>
            <table>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Qty</th>
                    <th>Received</th>
                    <th>Days Old</th>
                </tr>
                @foreach(array_slice($aging[$key]['items'], 0, 5) as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['sku'] }}</td>
                    <td class="number">{{ number_format($item['quantity'], 0) }}</td>
                    <td>{{ $item['received_date'] }}</td>
                    <td class="number">{{ $item['days_old'] }}</td>
                </tr>
                @endforeach
            </table>
        </div>
        @endforeach
    </div>

    <!-- MARGIN ANALYSIS PAGE -->
    <div class="page">
        <div class="header">
            <h1>💰 Margin Analysis</h1>
        </div>

        <div class="section">
            <div class="section-title">Top 20 Items by Margin</div>
            <table>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Qty</th>
                    <th>Cost</th>
                    <th>Revenue</th>
                    <th>Margin %</th>
                    <th>Total Margin</th>
                </tr>
                @foreach($margin as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['sku'] }}</td>
                    <td class="number">{{ number_format($item['quantity'], 0) }}</td>
                    <td class="currency">${{ number_format($item['cost'], 2) }}</td>
                    <td class="currency">${{ number_format($item['revenue'], 2) }}</td>
                    <td class="number" style="font-weight: bold;">{{ number_format($item['margin_percent'], 1) }}%</td>
                    <td class="currency">${{ number_format($item['total_margin'], 2) }}</td>
                </tr>
                @endforeach
            </table>
        </div>

        <div style="margin-top: 40px; padding: 15px; border-top: 2px solid #34495e;">
            <p style="text-align: center; font-size: 8px; color: #666;">
                This comprehensive inventory report was generated on {{ $date }} at {{ $time }}.<br>
                All values are current as of the report generation date.
            </p>
        </div>
    </div>
</body>
</html>
