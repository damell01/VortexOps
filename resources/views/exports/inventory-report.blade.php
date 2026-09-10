<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>VortexOps Inventory Report</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        @page { margin:22px 28px 28px; }
        body { font-family:DejaVu Sans, Arial, sans-serif; color:#172033; line-height:1.4; font-size:10px; }
        .summary { width:100%; border-collapse:separate; border-spacing:6px 0; margin:0 0 16px; }
        .summary td { width:50%; border:1px solid #e5e7eb; background:#fafafa; padding:10px; }
        .summary-label { font-size:8px; color:#64748b; text-transform:uppercase; font-weight:700; }
        .summary-value { font-size:16px; font-weight:800; color:#111827; margin-top:3px; }
        table.data { width:100%; border-collapse:collapse; margin-top:10px; table-layout:fixed; }
        table.data th { background:#f5f3ff; color:#4c1d95; padding:7px 6px; text-align:left; font-size:8.5px; font-weight:800; text-transform:uppercase; border:1px solid #ddd6fe; }
        table.data td { padding:7px 6px; border:1px solid #e5e7eb; font-size:8.5px; vertical-align:top; }
        table.data tr:nth-child(even) td { background:#fafafa; }
        .text-right { text-align:right!important; }
        .text-center { text-align:center!important; }
        .sku { font-weight:700; font-family:DejaVu Sans Mono, monospace; }
        .no-data { text-align:center; padding:40px; color:#64748b; }
    </style>
</head>
<body>
    @include('pdf.partials.brand-header', [
        'reportTitle' => 'Inventory Report',
        'reportSubtitle' => 'Current stock, cost and location summary',
    ])

    <table class="summary">
        <tr>
            <td><div class="summary-label">Total Items</div><div class="summary-value">{{ number_format($totalItems) }}</div></td>
            <td><div class="summary-label">Inventory Value</div><div class="summary-value">${{ number_format($totalValue, 2) }}</div></td>
        </tr>
    </table>

    @if($items->count() > 0)
        <table class="data">
            <thead><tr>
                <th>SKU</th><th style="width:23%">Item Name</th><th>Category</th>
                <th class="text-right">Unit Cost</th><th class="text-right">Avg Cost</th>
                <th class="text-center">Qty</th><th class="text-right">Value</th><th style="width:18%">Locations</th>
            </tr></thead>
            <tbody>
                @foreach($items as $item)
                    @php $totalQty = $item->stock->sum('quantity') ?? 0; @endphp
                    <tr>
                        <td class="sku">{{ $item->sku }}</td>
                        <td><strong>{{ $item->name }}</strong></td>
                        <td>{{ $item->category ?? '—' }}</td>
                        <td class="text-right">${{ number_format((float)$item->unit_cost, 2) }}</td>
                        <td class="text-right">${{ number_format((float)$item->average_cost, 2) }}</td>
                        <td class="text-center">{{ number_format($totalQty, 2) }}</td>
                        <td class="text-right">${{ number_format(($totalQty * ($item->average_cost ?? 0)), 2) }}</td>
                        <td>{{ $item->stock->pluck('location.name')->unique()->join(', ') ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="no-data">No items found</div>
    @endif

    @include('pdf.partials.brand-footer', ['footerLabel' => 'Inventory Report'])
</body>
</html>
