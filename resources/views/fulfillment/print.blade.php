<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Packing record · {{ $run->show?->title }}</title>
    <meta name="robots" content="noindex">
    <style>
        body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#111;margin:28px;font-size:12px}
        h1{font-size:20px;margin:0 0 4px}.muted{color:#555}
        dl{display:grid;grid-template-columns:repeat(4,1fr);gap:6px 16px;margin:16px 0}
        dt{font-size:10px;text-transform:uppercase;color:#666;letter-spacing:.04em}dd{margin:0;font-weight:700}
        table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #ccc;padding:5px 6px;text-align:left}th{background:#f2f0fb;font-size:11px}
        .letter{font-family:ui-monospace,Menlo,monospace;font-weight:800}
        .sign{margin-top:28px;display:grid;grid-template-columns:1fr 1fr;gap:40px}.sign div{border-top:1px solid #333;padding-top:4px}
        @media print{body{margin:10mm}button{display:none}}
    </style>
</head>
<body>
<button type="button" onclick="window.print()" style="float:right;padding:8px 14px;border-radius:8px;border:1px solid #7c3aed;background:#7c3aed;color:#fff;font-weight:700;cursor:pointer">Print</button>
<h1>{{ $run->show?->title }}</h1>
<p class="muted">VortexOps · Fulfillment packing record · {{ \App\Models\FulfillmentRun::statusLabel($run->status) }}</p>
<dl>
    <div><dt>Stream date</dt><dd>{{ $run->show?->show_date?->format('M j, Y') }}{{ $run->show?->start_time ? ' · ' . $run->show->start_time->timezone($tz)->format('g:i A') : '' }}</dd></div>
    <div><dt>Channel</dt><dd>{{ $run->show?->channel?->name ?? '—' }}</dd></div>
    <div><dt>Streamer</dt><dd>{{ $run->show?->streamers->pluck('name')->join(', ') ?: '—' }}</dd></div>
    <div><dt>Station</dt><dd>{{ $run->station?->name ?? '—' }}{{ $run->station?->camera_label ? ' · ' . $run->station->camera_label : '' }}</dd></div>
    <div><dt>Packers</dt><dd>{{ $run->packers->pluck('name')->join(', ') ?: '—' }}</dd></div>
    <div><dt>Packing start</dt><dd>{{ $run->started_at?->timezone($tz)->format('M j, g:i A') ?? '—' }}</dd></div>
    <div><dt>Packing complete</dt><dd>{{ $run->finished_at?->timezone($tz)->format('M j, g:i A') ?? '—' }}</dd></div>
    <div><dt>Packages</dt><dd>{{ $run->completed_packages }}{{ $run->expected_packages !== null ? ' / ' . $run->expected_packages : '' }}</dd></div>
</dl>
@if ($run->discrepancies)<p><strong>Discrepancies:</strong> {{ implode(' ', $run->discrepancies) }}</p>@endif
<table>
    <thead><tr><th>Letter</th><th>Buyer</th><th>Orders</th><th>Items</th><th>Status</th><th>Packed by</th><th>Packed at</th><th>Checked</th></tr></thead>
    <tbody>
    @foreach ($run->buyers as $b)
        <tr><td class="letter">{{ $b->letter }}</td><td>{{ $b->buyer_username }}</td><td>{{ implode(', ', (array) $b->order_ids) }}</td><td>{{ $b->item_count }}</td>
            <td>{{ ucfirst($b->status) }}</td><td>{{ $b->packer?->name }}</td><td>{{ $b->packed_at?->timezone($tz)->format('g:i:s A') }}</td>
            <td>{{ $b->packages->isNotEmpty() ? $b->packages->whereNotNull('checked_at')->count() . '/' . $b->packages->count() : ($b->checked_at ? '✓' : '') }}</td></tr>
    @endforeach
    </tbody>
</table>
@if ($run->flags->isNotEmpty())
    <h2 style="font-size:14px;margin-top:18px">Issues</h2>
    <ul>@foreach ($run->flags as $f)<li>{{ \App\Services\FulfillmentFlagService::label($f->type) }} · {{ ucfirst($f->status) }} · {{ $f->details }} ({{ $f->reporter?->name }})@if ($f->resolution) · Resolution: {{ $f->resolution }}@endif</li>@endforeach</ul>
@endif
<div class="sign">
    <div>Packer confirmed done: {{ $run->acknowledged_at ? $run->acknowledger?->name . ' · ' . $run->acknowledged_at->timezone($tz)->format('M j, g:i A') : '' }}</div>
    <div>Final sign-off: {{ $run->approved_at ? ($run->signoff_signature ? '“' . $run->signoff_signature . '” · ' : '') . $run->approver?->name . ' · ' . $run->approved_at->timezone($tz)->format('M j, g:i A') : '' }}</div>
    @if ($run->approval_notes)<div>Note: {{ $run->approval_notes }}</div>@endif
</div>
</body>
</html>
