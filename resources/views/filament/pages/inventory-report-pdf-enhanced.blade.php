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
        }
        .page {
            page-break-after: always;
            padding: 20mm;
            position: relative;
        }
        .page:last-child {
            page-break-after: avoid;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #1f2937;
        }
        .header h1 {
            font-size: 24px;
            color: #1f2937;
            margin-bottom: 5px;
        }
        .header p {
            color: #6b7280;
            font-size: 12px;
        }

        /* Metrics */
        .metrics {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        .metric-card {
            border: 1px solid #e5e7eb;
            padding: 12px;
            background: #f9fafb;
            border-radius: 6px;
        }
        .metric-label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .metric-value {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
        }
        .metric-subtext {
            font-size: 9px;
            color: #9ca3af;
            margin-top: 3px;
        }

        /* Section */
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #3b82f6;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 10px;
        }
        table thead {
            background: #1f2937;
            color: white;
        }
        table th {
            padding: 8px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #d1d5db;
        }
        table td {
            padding: 6px 8px;
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
        .text-danger {
            color: #dc2626;
        }
        .text-warning {
            color: #d97706;
        }
        .text-success {
            color: #059669;
        }

        /* Grid */
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 9px;
            color: #9ca3af;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    {{-- Page 1: Summary --}}
    <div class="page">
        <div class="header">
            <h1>📦 Inventory Report</h1>
            <p>Generated {{ $date }} at {{ $time }}</p>
        </div>

        @php
            $data = $summary;
            $snapshot = $data['currentSnapshot'];
        @endphp

        <div class="metrics">
            <div class="metric-card">
                <div class="metric-label">Total Inventory Value</div>
                <div class="metric-value">${{ number_format((float) $snapshot->total_value, 2) }}</div>
                <div class="metric-subtext">Across all locations</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Total SKUs</div>
                <div class="metric-value">{{ $snapshot->total_items }}</div>
                <div class="metric-subtext">Active items</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Total Units</div>
                <div class="metric-value">{{ number_format($snapshot->total_quantity) }}</div>
                <div class="metric-subtext">Quantity on hand</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Stock Alerts</div>
                <div class="metric-value text-warning">{{ collect($snapshot->stock_outs ?? [])->count() }}</div>
                <div class="metric-subtext">Out of stock</div>
            </div>
        </div>

        {{-- Stock Health --}}
        <div class="section">
            <div class="section-title">Stock Health Overview</div>
            <div class="metrics">
                <div class="metric-card">
                    <div class="metric-label">Healthy Stock</div>
                    <div class="metric-value text-success">{{ $health['healthy'] }}</div>
                    <div class="metric-subtext">{{ number_format(($health['healthy'] / max(1, $health['total'])) * 100, 0) }}% of items</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Low Stock</div>
                    <div class="metric-value text-warning">{{ $health['low_stock'] }}</div>
                    <div class="metric-subtext">Need reordering soon</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Out of Stock</div>
                    <div class="metric-value text-danger">{{ $health['out_of_stock'] }}</div>
                    <div class="metric-subtext">Order immediately</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Overstock</div>
                    <div class="metric-value">{{ $health['over_stock'] }}</div>
                    <div class="metric-subtext">{{ number_format(($health['over_stock'] / max(1, $health['total'])) * 100, 0) }}% of items</div>
                </div>
            </div>
        </div>

        {{-- Location Breakdown --}}
        @if($snapshot->location_breakdown && count($snapshot->location_breakdown) > 0)
            <div class="section">
                <div class="section-title">Inventory by Location</div>
                <table>
                    <thead>
                        <tr>
                            <th>Location</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">Value</th>
                            <th class="text-right">% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($snapshot->location_breakdown as $locId => $location)
                            @php
                                $pct = ($location['value'] / max(1, $snapshot->total_value)) * 100;
                            @endphp
                            <tr>
                                <td>{{ $location['name'] }}</td>
                                <td class="text-right">{{ number_format($location['quantity']) }}</td>
                                <td class="text-right font-semibold">${{ number_format($location['value'], 2) }}</td>
                                <td class="text-right">{{ number_format($pct, 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="footer">
            VortexOps Inventory Management System
        </div>
    </div>

    {{-- Page 2: Detailed Item List --}}
    <div class="page page-break">
        <div class="header">
            <h1>📋 Detailed Item List</h1>
            <p>Complete inventory breakdown by item</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Item Name</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Unit Cost</th>
                    <th class="text-right">Total Value</th>
                    <th>Location</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($summary['itemDetails'] as $item)
                    @php
                        $statusClass = '';
                        $statusText = 'Healthy';
                        if ($item['is_low_stock']) {
                            $statusClass = 'text-warning';
                            $statusText = 'Low';
                        }
                    @endphp
                    <tr>
                        <td class="font-semibold">{{ $item['sku'] }}</td>
                        <td>{{ $item['name'] }}</td>
                        <td class="text-right">{{ number_format($item['quantity']) }}</td>
                        <td class="text-right">${{ number_format($item['unit_cost'], 4) }}</td>
                        <td class="text-right font-semibold">${{ number_format($item['total_value'], 2) }}</td>
                        <td>{{ $item['location'] }}</td>
                        <td class="text-center {{ $statusClass }}">{{ $statusText }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            Total Items: {{ count($summary['itemDetails']) }} | Total Value: ${{ number_format($summary['itemDetails']->sum('total_value'), 2) }}
        </div>
    </div>

    {{-- Page 3: Stock Velocity & Movers --}}
    <div class="page page-break">
        <div class="header">
            <h1>⚡ Stock Movement Analysis</h1>
            <p>Fast movers, slow movers, and dead stock</p>
        </div>

        {{-- Fast Movers --}}
        <div class="section">
            <div class="section-title">🚀 Fast Movers (Last 30 Days)</div>
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Item Name</th>
                        <th class="text-right">Qty Sold</th>
                        <th class="text-right">Velocity</th>
                        <th class="text-right">Days Left</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fastMovers as $item)
                        <tr>
                            <td class="font-semibold">{{ $item['sku'] ?? '—' }}</td>
                            <td>{{ $item['name'] ?? '—' }}</td>
                            <td class="text-right">{{ number_format($item['quantity_sold'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($item['daily_velocity'] ?? 0, 2) }}/day</td>
                            <td class="text-right">{{ $item['days_of_stock'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No data available</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Slow Movers --}}
        <div class="section">
            <div class="section-title">🐢 Slow Movers (Last 30 Days)</div>
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Item Name</th>
                        <th class="text-right">Current Qty</th>
                        <th class="text-right">Value</th>
                        <th class="text-right">Days to Sell</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($slowMovers as $item)
                        <tr>
                            <td class="font-semibold">{{ $item['sku'] ?? '—' }}</td>
                            <td>{{ $item['name'] ?? '—' }}</td>
                            <td class="text-right">{{ number_format($item['quantity'] ?? 0) }}</td>
                            <td class="text-right">${{ number_format($item['total_value'] ?? 0, 2) }}</td>
                            <td class="text-right">{{ $item['days_of_stock'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No data available</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Dead Stock --}}
        <div class="section">
            <div class="section-title">💀 Dead Stock (Not Sold in 30 Days)</div>
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Item Name</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Tied Up Value</th>
                        <th class="text-right">Days Since Sale</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deadStock as $item)
                        <tr>
                            <td class="font-semibold">{{ $item['sku'] ?? '—' }}</td>
                            <td>{{ $item['name'] ?? '—' }}</td>
                            <td class="text-right">{{ number_format($item['quantity'] ?? 0) }}</td>
                            <td class="text-right text-danger">${{ number_format($item['total_value'] ?? 0, 2) }}</td>
                            <td class="text-right">{{ $item['days_since_last_sale'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No dead stock</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="footer">
            Stock Movement Analysis - Generated {{ $date }}
        </div>
    </div>

    {{-- Page 4: ABC Analysis --}}
    <div class="page page-break">
        <div class="header">
            <h1>📊 ABC Inventory Analysis</h1>
            <p>Pareto classification - Focus on high-value items</p>
        </div>

        {{-- Class A --}}
        <div class="section">
            <div class="section-title">Class A - Top 80% Value ({{ $abcAnalysis['a_count'] }} items)</div>
            <p style="font-size: 9px; color: #6b7280; margin-bottom: 8px;">These items represent 80% of inventory value. Highest priority for stock control.</p>
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Item Name</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Avg Cost</th>
                        <th class="text-right">Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($abcAnalysis['class_a'] as $item)
                        <tr>
                            <td class="font-semibold">{{ $item['sku'] }}</td>
                            <td>{{ $item['name'] }}</td>
                            <td class="text-right">{{ number_format($item['qty']) }}</td>
                            <td class="text-right">${{ number_format($item['cost'], 4) }}</td>
                            <td class="text-right font-semibold">${{ number_format($item['value'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No items</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Class B --}}
        <div class="section">
            <div class="section-title">Class B - Middle 15% Value ({{ $abcAnalysis['b_count'] }} items)</div>
            <p style="font-size: 9px; color: #6b7280; margin-bottom: 8px;">Important items representing 15% of value. Regular monitoring recommended.</p>
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Item Name</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($abcAnalysis['class_b'] as $item)
                        <tr>
                            <td class="font-semibold">{{ $item['sku'] }}</td>
                            <td>{{ $item['name'] }}</td>
                            <td class="text-right">{{ number_format($item['qty']) }}</td>
                            <td class="text-right">${{ number_format($item['value'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">No items</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Class C --}}
        <div class="section">
            <div class="section-title">Class C - Bottom 5% Value ({{ $abcAnalysis['c_count'] }} items)</div>
            <p style="font-size: 9px; color: #6b7280; margin-bottom: 8px;">Low-value items. Can use simple stock control methods.</p>
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Item Name</th>
                        <th class="text-right">Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($abcAnalysis['class_c'] as $item)
                        <tr>
                            <td class="font-semibold">{{ $item['sku'] }}</td>
                            <td>{{ $item['name'] }}</td>
                            <td class="text-right">${{ number_format($item['value'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center">No items</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="footer">
            ABC Analysis - Focus on Class A items for maximum inventory optimization
        </div>
    </div>

    {{-- Page 5: Location Health --}}
    <div class="page page-break">
        <div class="header">
            <h1>🏢 Location Health Report</h1>
            <p>Inventory status by storage location</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Location</th>
                    <th>Type</th>
                    <th class="text-right">Total Items</th>
                    <th class="text-right">Healthy</th>
                    <th class="text-right">Low Stock</th>
                    <th class="text-right">Out of Stock</th>
                    <th class="text-right">Total Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach($locationHealth as $location)
                    <tr>
                        <td class="font-semibold">{{ $location['name'] }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $location['type'])) }}</td>
                        <td class="text-right">{{ $location['total_items'] }}</td>
                        <td class="text-right text-success">{{ $location['healthy'] }}</td>
                        <td class="text-right text-warning">{{ $location['low_stock'] }}</td>
                        <td class="text-right text-danger">{{ $location['out_of_stock'] }}</td>
                        <td class="text-right font-semibold">${{ number_format($location['total_value'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            Monitor locations for balanced inventory distribution
        </div>
    </div>

    {{-- Page 6: Category Breakdown --}}
    @if($categories && count($categories) > 0)
    <div class="page page-break">
        <div class="header">
            <h1>🏷️ Inventory by Category</h1>
            <p>Category-wise breakdown and value distribution</p>
        </div>

        @foreach($categories as $category)
            <div class="section">
                <div class="section-title">{{ $category['category'] }}</div>
                <div class="metrics">
                    <div class="metric-card">
                        <div class="metric-label">Items</div>
                        <div class="metric-value">{{ $category['item_count'] }}</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Total Qty</div>
                        <div class="metric-value">{{ number_format($category['total_quantity']) }}</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Total Value</div>
                        <div class="metric-value">${{ number_format($category['total_value'], 2) }}</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Avg Unit Cost</div>
                        <div class="metric-value">${{ number_format($category['avg_unit_cost'], 2) }}</div>
                    </div>
                </div>
                @if(!empty($category['items']))
                    <table>
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Item Name</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($category['items'] as $item)
                                <tr>
                                    <td class="font-semibold">{{ $item['sku'] }}</td>
                                    <td>{{ $item['name'] }}</td>
                                    <td class="text-right">{{ number_format($item['quantity']) }}</td>
                                    <td class="text-right">${{ number_format($item['total_value'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endforeach

        <div class="footer">
            Category Analysis - Identify strong and weak performing categories
        </div>
    </div>
    @endif

    {{-- Final Page: Summary & Notes --}}
    <div class="page page-break">
        <div class="header">
            <h1>✓ Report Summary</h1>
            <p>Key insights and recommendations</p>
        </div>

        <div class="section">
            <div class="section-title">Report Metadata</div>
            <table>
                <tr>
                    <td style="width: 40%;">Report Generated:</td>
                    <td><strong>{{ $date }} at {{ $time }}</strong></td>
                </tr>
                <tr>
                    <td>Total Inventory Value:</td>
                    <td><strong>${{ number_format($summary['currentSnapshot']->total_value, 2) }}</strong></td>
                </tr>
                <tr>
                    <td>Active SKUs:</td>
                    <td><strong>{{ $summary['currentSnapshot']->total_items }}</strong></td>
                </tr>
                <tr>
                    <td>Units on Hand:</td>
                    <td><strong>{{ number_format($summary['currentSnapshot']->total_quantity) }}</strong></td>
                </tr>
                <tr>
                    <td>Items Out of Stock:</td>
                    <td><strong class="text-danger">{{ collect($summary['currentSnapshot']->stock_outs ?? [])->count() }}</strong></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Actionable Insights</div>
            <ul style="font-size: 10px; margin-left: 15px; line-height: 1.8;">
                <li><strong>Review Low Stock Items:</strong> {{ $health['low_stock'] }} items are below reorder levels. Process orders immediately.</li>
                <li><strong>Focus on Class A Items:</strong> Monitor {{ $abcAnalysis['a_count'] }} high-value items closely (80% of value).</li>
                <li><strong>Analyze Dead Stock:</strong> Review items with no sales in 30+ days for clearance or discontinuation.</li>
                <li><strong>Location Balance:</strong> Ensure inventory is well-distributed across locations for faster fulfillment.</li>
                <li><strong>ABC Optimization:</strong> Apply stricter controls to Class A, standard controls to Class B, and simple controls to Class C items.</li>
            </ul>
        </div>

        <div class="section">
            <div class="section-title">Report Sections Included</div>
            <ul style="font-size: 10px; margin-left: 15px; line-height: 1.8;">
                <li>✓ Executive Summary & Key Metrics</li>
                <li>✓ Detailed Item List with Status</li>
                <li>✓ Stock Movement Analysis (Fast/Slow/Dead)</li>
                <li>✓ ABC Inventory Classification</li>
                <li>✓ Location Health & Balancing</li>
                <li>✓ Category Breakdown & Performance</li>
            </ul>
        </div>

        <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
            <p style="font-size: 9px; color: #9ca3af; text-align: center;">
                This report was automatically generated by VortexOps Inventory Management System.<br>
                For questions or discrepancies, please review your data in the system or contact support.
            </p>
        </div>
    </div>
</body>
</html>
