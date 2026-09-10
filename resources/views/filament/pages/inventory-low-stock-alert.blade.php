<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>VortexOps Low Stock Alert</title>
<style>
@page{margin:22px 28px 28px}*{box-sizing:border-box}body{font-family:DejaVu Sans,Arial,sans-serif;color:#172033;font-size:10px;line-height:1.45;margin:0}.alert-box{background:#fee2e2;border-left:4px solid #dc2626;padding:10px 12px;margin-bottom:14px;color:#991b1b}.summary{width:100%;border-collapse:separate;border-spacing:5px 0;margin-bottom:15px}.summary td{width:33%;border:1px solid #e5e7eb;background:#fafafa;padding:9px;text-align:center}.summary-label{font-size:8px;color:#64748b;text-transform:uppercase;font-weight:700}.summary-value{font-size:15px;font-weight:800;color:#111827;margin-top:3px}.critical{color:#dc2626;font-weight:700}.warning{color:#d97706}.action-box{background:#fffbeb;border-left:4px solid #f59e0b;padding:10px 12px;margin:12px 0;color:#92400e}table.data{width:100%;border-collapse:collapse;font-size:8.5px;margin-bottom:14px}table.data th{padding:7px 5px;text-align:left;font-weight:800;background:#f5f3ff;color:#4c1d95;border:1px solid #ddd6fe}table.data td{padding:6px 5px;border:1px solid #e5e7eb}table.data tr:nth-child(even) td{background:#fafafa}.text-right{text-align:right!important}.text-center{text-align:center!important}
</style>
</head>
<body>
@include('pdf.partials.brand-header', ['reportTitle'=>'Low Stock Alert','reportSubtitle'=>$count.' item(s) currently below reorder level'])
<div class="alert-box"><strong>Attention:</strong> Review the items below and confirm which ones should be reordered. Suggested quantities are based on the current inventory rules and available sales velocity.</div>
<table class="summary"><tr>
<td><div class="summary-label">Items to Reorder</div><div class="summary-value critical">{{ $count }}</div></td>
<td><div class="summary-label">Current Units</div><div class="summary-value">{{ number_format(collect($items)->sum('current_qty')) }}</div></td>
<td><div class="summary-label">Estimated Reorder Cost</div><div class="summary-value">${{ number_format(collect($items)->sum(fn($i)=>$i['suggested_qty']*$i['avg_cost']),2) }}</div></td>
</tr></table>
<table class="data"><thead><tr><th>SKU</th><th>Item Name</th><th class="text-right">Current</th><th class="text-right">Reorder Level</th><th class="text-right">Short By</th><th class="text-right">Suggested</th><th class="text-right">Avg Cost</th><th class="text-right">Est. Cost</th><th>Priority</th></tr></thead><tbody>
@foreach($items as $item)
@php $shortBy=$item['reorder_level']-$item['current_qty']; $estCost=$item['suggested_qty']*$item['avg_cost']; $priority=$shortBy>($item['reorder_level']*.5)?'CRITICAL':'HIGH'; @endphp
<tr><td><strong>{{ $item['sku'] }}</strong></td><td>{{ $item['name'] }}</td><td class="text-right critical">{{ number_format($item['current_qty']) }}</td><td class="text-right">{{ number_format($item['reorder_level']) }}</td><td class="text-right warning">{{ number_format($shortBy) }}</td><td class="text-right"><strong>{{ number_format($item['suggested_qty']) }}</strong></td><td class="text-right">${{ number_format($item['avg_cost'],4) }}</td><td class="text-right"><strong>${{ number_format($estCost,2) }}</strong></td><td>{{ $priority }}</td></tr>
@endforeach
</tbody></table>
<div class="action-box"><strong>Recommended workflow:</strong> review critical items first, group orders by vendor, confirm actual vendor lead time, and adjust reorder levels after the next inventory count if needed.</div>
@include('pdf.partials.brand-footer', ['footerLabel'=>'Low Stock Alert'])
</body>
</html>
