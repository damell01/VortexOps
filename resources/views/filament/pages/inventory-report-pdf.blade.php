<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>VortexOps Inventory Report</title>
<style>
*{margin:0;padding:0;box-sizing:border-box} @page{margin:22px 28px 28px}
body{font-family:DejaVu Sans,Arial,sans-serif;color:#172033;line-height:1.4;font-size:10px}
.metrics{width:100%;border-collapse:separate;border-spacing:5px 0;margin-bottom:18px}.metrics td{width:25%;border:1px solid #e5e7eb;padding:9px;background:#fafafa}
.metric-label{font-size:8px;color:#64748b;text-transform:uppercase;font-weight:700}.metric-value{font-size:15px;font-weight:800;color:#5b21b6;margin-top:3px}
.section{margin-bottom:20px;page-break-inside:avoid}.section-title{font-size:12px;font-weight:800;color:#4c1d95;border-bottom:2px solid #7c3aed;padding-bottom:6px;margin-bottom:10px}
table.data{width:100%;border-collapse:collapse;margin-bottom:12px}table.data th{background:#f5f3ff;color:#4c1d95;padding:7px;text-align:left;font-size:8.5px;font-weight:800;border:1px solid #ddd6fe}table.data td{padding:7px;border:1px solid #e5e7eb}table.data tr:nth-child(even) td{background:#fafafa}
.alert-box{border-left:4px solid #dc2626;background:#fef2f2;padding:9px;margin-bottom:8px}.alert-box.warning{border-left-color:#f59e0b;background:#fffbeb}.alert-title{font-weight:800;color:#7f1d1d}.alert-box.warning .alert-title{color:#92400e}.empty-state{text-align:center;color:#94a3b8;padding:15px;font-style:italic}.page-break{page-break-after:always}
</style>
</head>
<body>
@include('pdf.partials.brand-header', ['reportTitle'=>'Inventory Analytics Report','reportSubtitle'=>'Stock value, locations and exceptions'])

<table class="metrics"><tr>
<td><div class="metric-label">Total Inventory Value</div><div class="metric-value">${{ number_format($currentSnapshot->total_value,2) }}</div></td>
<td><div class="metric-label">Total SKUs</div><div class="metric-value">{{ $currentSnapshot->total_items }}</div></td>
<td><div class="metric-label">Total Units</div><div class="metric-value">{{ number_format($currentSnapshot->total_quantity) }}</div></td>
<td><div class="metric-label">Stock Alerts</div><div class="metric-value">{{ count($currentSnapshot->stock_outs ?? []) }}</div></td>
</tr></table>

@if($currentSnapshot->location_breakdown)
<div class="section"><div class="section-title">Inventory by Location</div><table class="data"><thead><tr><th>Location</th><th style="text-align:right">Value</th><th style="text-align:right">Units</th></tr></thead><tbody>
@forelse($currentSnapshot->location_breakdown as $locId=>$loc)<tr><td>{{ $loc['name'] ?? "Location $locId" }}</td><td style="text-align:right">${{ number_format($loc['value'],2) }}</td><td style="text-align:right">{{ number_format($loc['quantity']) }}</td></tr>@empty<tr><td colspan="3" class="empty-state">No location data available</td></tr>@endforelse
</tbody></table></div>
@endif

@if($currentSnapshot->item_type_breakdown)
<div class="section"><div class="section-title">Inventory by Type</div><table class="data"><thead><tr><th>Type</th><th style="text-align:right">Value</th><th style="text-align:right">Units</th></tr></thead><tbody>
@forelse($currentSnapshot->item_type_breakdown as $type=>$data)<tr><td>{{ $type }}</td><td style="text-align:right">${{ number_format($data['value'],2) }}</td><td style="text-align:right">{{ number_format($data['quantity']) }}</td></tr>@empty<tr><td colspan="3" class="empty-state">No type data available</td></tr>@endforelse
</tbody></table></div>
@endif

@if($currentSnapshot->slow_moving_items && count($currentSnapshot->slow_moving_items)>0)
<div class="section"><div class="section-title">Slow-Moving Items (30+ days)</div><div class="alert-box warning"><div class="alert-title">Items with no movement in the last 30 days</div></div><table class="data"><thead><tr><th>Item Name</th><th style="text-align:right">Quantity</th><th style="text-align:right">Value</th></tr></thead><tbody>
@foreach($currentSnapshot->slow_moving_items as $item)<tr><td>{{ $item['name'] }}</td><td style="text-align:right">{{ $item['quantity'] }}</td><td style="text-align:right">${{ number_format($item['value'],2) }}</td></tr>@endforeach
</tbody></table></div>
@endif

@if($currentSnapshot->stock_outs && count($currentSnapshot->stock_outs)>0)
<div class="section"><div class="section-title">Out of Stock Items</div><div class="alert-box"><div class="alert-title">Items at zero quantity</div></div><table class="data"><thead><tr><th>SKU</th><th>Item Name</th></tr></thead><tbody>
@foreach($currentSnapshot->stock_outs as $item)<tr><td>{{ $item['sku'] }}</td><td>{{ $item['name'] }}</td></tr>@endforeach
</tbody></table></div>
@endif

<div class="page-break"></div>
<div class="section"><div class="section-title">Detailed Item Inventory</div><table class="data"><thead><tr><th>SKU</th><th>Item Name</th><th>Location</th><th style="text-align:right">Qty</th><th style="text-align:right">Unit Cost</th><th style="text-align:right">Total Value</th></tr></thead><tbody>
@forelse($itemDetails as $item)<tr><td>{{ $item['sku'] }}</td><td>{{ $item['name'] }}</td><td>{{ $item['location'] }}</td><td style="text-align:right">{{ number_format($item['quantity']) }} {{ $item['is_low_stock'] ? 'Low' : '' }}</td><td style="text-align:right">${{ number_format($item['unit_cost'],2) }}</td><td style="text-align:right"><strong>${{ number_format($item['total_value'],2) }}</strong></td></tr>@empty<tr><td colspan="6" class="empty-state">No inventory items found</td></tr>@endforelse
</tbody></table></div>

@include('pdf.partials.brand-footer', ['footerLabel'=>'Inventory Analytics Report'])
</body>
</html>
