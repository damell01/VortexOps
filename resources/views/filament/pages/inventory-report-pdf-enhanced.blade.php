<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>VortexOps Comprehensive Inventory Report</title>
<style>
@page{margin:18px 24px 24px}*{box-sizing:border-box}body{font-family:DejaVu Sans,Arial,sans-serif;color:#172033;font-size:9px;line-height:1.35;margin:0}.page{page-break-after:always}.page:last-child{page-break-after:auto}.section{margin-bottom:16px}.section-title{font-size:11px;font-weight:800;color:#4c1d95;border-bottom:2px solid #7c3aed;padding-bottom:5px;margin-bottom:8px}.metrics{width:100%;border-collapse:separate;border-spacing:5px 0;margin-bottom:14px}.metrics td{border:1px solid #e5e7eb;background:#fafafa;padding:8px}.metric-label{font-size:7px;text-transform:uppercase;color:#64748b;font-weight:800}.metric-value{font-size:14px;font-weight:800;color:#111827;margin-top:2px}.green{color:#059669}.amber{color:#d97706}.red{color:#dc2626}.purple{color:#7c3aed}table.data{width:100%;border-collapse:collapse;margin-bottom:10px;table-layout:fixed}table.data th{background:#f5f3ff;color:#4c1d95;padding:5px 6px;border:1px solid #ddd6fe;text-align:left;font-size:7.5px;font-weight:800;text-transform:uppercase}table.data td{padding:5px 6px;border:1px solid #e5e7eb;vertical-align:top}table.data tr:nth-child(even) td{background:#fafafa}.right{text-align:right!important}.center{text-align:center!important}.muted{color:#64748b}
</style>
</head>
<body>
@php $snapshot=$summary['currentSnapshot']; $items=$summary['itemDetails']; @endphp
<div class="page">
@include('pdf.partials.brand-header',['reportTitle'=>'Comprehensive Inventory Report','reportSubtitle'=>'Inventory value, stock health, velocity and coverage'])
<table class="metrics"><tr>
<td><div class="metric-label">Inventory Value</div><div class="metric-value">${{ number_format((float)$snapshot->total_value,2) }}</div></td>
<td><div class="metric-label">Active SKUs</div><div class="metric-value">{{ number_format((int)$snapshot->total_items) }}</div></td>
<td><div class="metric-label">Units On Hand</div><div class="metric-value">{{ number_format((float)$snapshot->total_quantity,0) }}</div></td>
<td><div class="metric-label">Out of Stock</div><div class="metric-value red">{{ $health['out_of_stock'] }}</div></td>
</tr></table>
<div class="section"><div class="section-title">Stock Health</div><table class="metrics"><tr>
<td><div class="metric-label">Healthy</div><div class="metric-value green">{{ $health['healthy'] }}</div></td>
<td><div class="metric-label">Low Stock</div><div class="metric-value amber">{{ $health['low_stock'] }}</div></td>
<td><div class="metric-label">Out of Stock</div><div class="metric-value red">{{ $health['out_of_stock'] }}</div></td>
<td><div class="metric-label">Overstock</div><div class="metric-value purple">{{ $health['over_stock'] }}</div></td>
</tr></table></div>
@if($snapshot->location_breakdown)
<div class="section"><div class="section-title">Inventory by Location</div><table class="data"><thead><tr><th>Location</th><th class="right">Units</th><th class="right">Value</th></tr></thead><tbody>@foreach($snapshot->location_breakdown as $loc)<tr><td>{{ $loc['name']??'—' }}</td><td class="right">{{ number_format($loc['quantity']??0,0) }}</td><td class="right">${{ number_format($loc['value']??0,2) }}</td></tr>@endforeach</tbody></table></div>
@endif
<div class="section"><div class="section-title">Highest-Value Inventory</div><table class="data"><thead><tr><th>SKU</th><th style="width:32%">Item</th><th>Location</th><th class="right">Qty</th><th class="right">Avg Cost</th><th class="right">Value</th></tr></thead><tbody>@foreach(collect($items)->take(20) as $item)<tr><td>{{ $item['sku'] }}</td><td>{{ $item['name'] }}</td><td>{{ $item['location'] }}</td><td class="right">{{ number_format($item['quantity'],0) }}</td><td class="right">${{ number_format($item['unit_cost'],2) }}</td><td class="right">${{ number_format($item['total_value'],2) }}</td></tr>@endforeach</tbody></table></div>
@include('pdf.partials.brand-footer',['footerLabel'=>'Comprehensive Inventory Report'])
</div>

<div class="page">
@include('pdf.partials.brand-header',['reportTitle'=>'Inventory Movement Analysis','reportSubtitle'=>'Fast movers, slow movers and dead stock'])
<div class="section"><div class="section-title">Fast Movers — Last 30 Days</div><table class="data"><thead><tr><th>SKU</th><th>Item</th><th class="right">Qty Sold</th><th class="right">Daily Velocity</th><th class="right">Days of Stock</th></tr></thead><tbody>@forelse($fastMovers as $item)<tr><td>{{ $item['sku']??'—' }}</td><td>{{ $item['name']??'—' }}</td><td class="right">{{ number_format($item['quantity_sold']??0,0) }}</td><td class="right">{{ number_format($item['daily_velocity']??0,2) }}</td><td class="right">{{ $item['days_of_stock']??'—' }}</td></tr>@empty<tr><td colspan="5" class="center muted">No fast-mover data available.</td></tr>@endforelse</tbody></table></div>
<div class="section"><div class="section-title">Slow Movers — Last 30 Days</div><table class="data"><thead><tr><th>SKU</th><th>Item</th><th class="right">Current Qty</th><th class="right">Value</th><th class="right">Days of Stock</th></tr></thead><tbody>@forelse($slowMovers as $item)<tr><td>{{ $item['sku']??'—' }}</td><td>{{ $item['name']??'—' }}</td><td class="right">{{ number_format($item['quantity']??0,0) }}</td><td class="right">${{ number_format($item['total_value']??0,2) }}</td><td class="right">{{ $item['days_of_stock']??'—' }}</td></tr>@empty<tr><td colspan="5" class="center muted">No slow-mover data available.</td></tr>@endforelse</tbody></table></div>
<div class="section"><div class="section-title">Dead Stock — No Sale in 30 Days</div><table class="data"><thead><tr><th>SKU</th><th>Item</th><th class="right">Qty</th><th class="right">Value</th><th class="right">Days Since Sale</th></tr></thead><tbody>@forelse($deadStock as $item)<tr><td>{{ $item['sku']??'—' }}</td><td>{{ $item['name']??'—' }}</td><td class="right">{{ number_format($item['quantity']??0,0) }}</td><td class="right">${{ number_format($item['total_value']??0,2) }}</td><td class="right">{{ $item['days_since_last_sale']??'—' }}</td></tr>@empty<tr><td colspan="5" class="center muted">No dead stock in this window.</td></tr>@endforelse</tbody></table></div>
@include('pdf.partials.brand-footer',['footerLabel'=>'Inventory Movement Analysis'])
</div>

<div class="page">
@include('pdf.partials.brand-header',['reportTitle'=>'Inventory Classification & Coverage','reportSubtitle'=>'ABC value concentration and estimated stock coverage'])
<table class="metrics"><tr><td><div class="metric-label">Class A Items</div><div class="metric-value">{{ $abcAnalysis['a_count'] }}</div></td><td><div class="metric-label">Class B Items</div><div class="metric-value">{{ $abcAnalysis['b_count'] }}</div></td><td><div class="metric-label">Class C Items</div><div class="metric-value">{{ $abcAnalysis['c_count'] }}</div></td><td><div class="metric-label">Coverage Rows</div><div class="metric-value">{{ count($coverage) }}</div></td></tr></table>
<div class="section"><div class="section-title">Class A — Highest Value</div><table class="data"><thead><tr><th>SKU</th><th>Item</th><th class="right">Qty</th><th class="right">Avg Cost</th><th class="right">Value</th></tr></thead><tbody>@foreach($abcAnalysis['class_a'] as $item)<tr><td>{{ $item['sku'] }}</td><td>{{ $item['name'] }}</td><td class="right">{{ number_format($item['qty'],0) }}</td><td class="right">${{ number_format($item['cost'],2) }}</td><td class="right">${{ number_format($item['value'],2) }}</td></tr>@endforeach</tbody></table></div>
<div class="section"><div class="section-title">Stock Coverage</div><table class="data"><thead><tr><th>SKU</th><th>Item</th><th class="right">Qty</th><th class="right">Daily Velocity</th><th class="right">Days of Stock</th></tr></thead><tbody>@forelse($coverage as $item)<tr><td>{{ $item['sku'] }}</td><td>{{ $item['name'] }}</td><td class="right">{{ number_format($item['quantity'],0) }}</td><td class="right">{{ number_format($item['velocity'],2) }}</td><td class="right">{{ $item['days_of_stock']??'—' }}</td></tr>@empty<tr><td colspan="5" class="center muted">No coverage data available.</td></tr>@endforelse</tbody></table></div>
@include('pdf.partials.brand-footer',['footerLabel'=>'Inventory Classification & Coverage'])
</div>

<div class="page">
@include('pdf.partials.brand-header',['reportTitle'=>'Inventory Aging & Margin','reportSubtitle'=>'Age buckets and current margin analysis'])
<div class="section"><div class="section-title">Inventory Aging</div><table class="data"><thead><tr><th>Age</th><th class="right">Items</th><th class="right">Value</th></tr></thead><tbody>@foreach($aging as $bucket)<tr><td>{{ $bucket['label'] }}</td><td class="right">{{ $bucket['count'] }}</td><td class="right">${{ number_format($bucket['value']??$bucket['total_value']??0,2) }}</td></tr>@endforeach</tbody></table></div>
<div class="section"><div class="section-title">Margin Analysis</div><table class="data"><thead><tr><th>SKU</th><th>Item</th><th class="right">Qty</th><th class="right">Cost</th><th class="right">Revenue</th><th class="right">Margin</th><th class="right">Margin %</th></tr></thead><tbody>@forelse($margin as $item)<tr><td>{{ $item['sku'] }}</td><td>{{ $item['name'] }}</td><td class="right">{{ number_format($item['quantity'],0) }}</td><td class="right">${{ number_format($item['cost'],2) }}</td><td class="right">${{ number_format($item['revenue'],2) }}</td><td class="right">${{ number_format($item['total_margin'],2) }}</td><td class="right">{{ number_format($item['margin_percent'],1) }}%</td></tr>@empty<tr><td colspan="7" class="center muted">No margin data available.</td></tr>@endforelse</tbody></table></div>
@include('pdf.partials.brand-footer',['footerLabel'=>'Inventory Aging & Margin'])
</div>
</body>
</html>
