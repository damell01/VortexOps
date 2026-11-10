@php
    [$from, $until] = $this->range;
    $sum = $this->summary;
    $labels = \App\Services\LedgerChargeReport::LABELS;
    $signed = fn ($v) => ((float) $v < 0 ? '−' : ((float) $v > 0 ? '+' : '')) . '$' . number_format(abs((float) $v), 2);
    $tz = \App\Support\BusinessTime::zone();
    $mine = (bool) $lockedStreamerId;
    $pages = $this->pageCount();
    $canOpenShows = \App\Filament\Resources\ShowResource::canAccess();
    $isAdmin = (bool) auth()->user()?->isAdmin();
    $tone = ['shipping' => 'ship', 'refunds' => 'refund', 'correction' => 'adjust'];
@endphp
<div class="vxlc" data-vx-page="ledger-charges">
    <style>
        .vxlc{display:grid;gap:16px;color:var(--vxw-text,#182033)}
        .vxlc-card{background:var(--vxw-surface,#fff);border:1px solid var(--vxw-border,#e7e3f3);border-radius:18px;box-shadow:0 1px 2px rgba(24,12,55,.04)}
        .vxlc-top{padding:18px 20px;display:grid;gap:14px}
        .vxlc-intro{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;justify-content:space-between}
        .vxlc-intro p{margin:0;max-width:760px;font-size:14px;line-height:1.5;color:var(--vxw-muted,#64748b)}
        .vxlc-filters{display:flex;flex-wrap:wrap;gap:10px 14px;align-items:flex-end}
        .vxlc-seg{display:inline-flex;flex-wrap:wrap;gap:4px;padding:4px;border-radius:12px;background:var(--vxw-surface-2,#f4f2fb)}
        .vxlc-seg button{border:0;background:none;padding:7px 12px;border-radius:9px;font-size:13px;font-weight:600;color:var(--vxw-muted,#64748b);cursor:pointer;white-space:nowrap}
        .vxlc-seg button[aria-pressed="true"]{background:var(--vxw-surface,#fff);color:#6d28d9;box-shadow:0 1px 3px rgba(24,12,55,.12)}
        .vxlc-field{display:grid;gap:4px;font-size:12px;font-weight:600;color:var(--vxw-muted,#64748b)}
        .vxlc-field select,.vxlc-field input{min-width:170px;height:38px;border:1px solid var(--vxw-border,#e2e8f0);border-radius:10px;padding:0 10px;background:var(--vxw-surface,#fff);color:var(--vxw-text,#182033);font-size:14px}
        .vxlc-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;padding:0 20px 18px}
        .vxlc-stat{border:1px solid var(--vxw-border,#e7e3f3);border-radius:14px;padding:12px 14px;display:grid;gap:4px}
        .vxlc-stat span{font-size:12px;font-weight:600;color:var(--vxw-muted,#64748b)}
        .vxlc-stat b{font-size:20px;font-weight:750;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
        .vxlc-stat.is-total{background:linear-gradient(135deg,#f5f3ff,#ede9fe);border-color:#ddd6fe}
        .vxlc-stat.is-total b{font-size:24px}
        .vxlc-head{display:flex;flex-wrap:wrap;gap:6px 12px;align-items:baseline;justify-content:space-between;padding:16px 20px 10px}
        .vxlc-head h3{margin:0;font-size:16px;font-weight:750}
        .vxlc-head span{font-size:13px;color:var(--vxw-muted,#64748b)}
        .vxlc-wrap{overflow-x:auto}
        .vxlc-table{width:100%;border-collapse:collapse;font-size:14px}
        .vxlc-table th{position:sticky;top:0;text-align:left;font-size:11.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--vxw-muted,#64748b);background:var(--vxw-surface-2,#faf9ff);padding:9px 12px;border-bottom:1px solid var(--vxw-border,#e7e3f3);white-space:nowrap}
        .vxlc-table td{padding:12px;border-bottom:1px solid var(--vxw-border,#f0edf8);vertical-align:top}
        .vxlc-table tbody tr:hover td{background:rgba(124,58,237,.035)}
        .vxlc-table tr:last-child td{border-bottom:0}
        .vxlc-table .num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
        .vxlc-sub{display:block;margin-top:2px;font-size:12.5px;color:var(--vxw-muted,#64748b);line-height:1.4}
        .vxlc-strong{font-weight:650}
        .vxlc-pill{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:700;white-space:nowrap}
        .vxlc-pill--ship{background:#e0f2fe;color:#075985}
        .vxlc-pill--refund{background:#ffe4e6;color:#9f1239}
        .vxlc-pill--adjust{background:#fef3c7;color:#92400e}
        .vxlc-pill--muted{background:#f1f5f9;color:#475569}
        .vxlc-a{color:#6d28d9;font-weight:650;text-decoration:none}
        .vxlc-a:hover{text-decoration:underline;text-underline-offset:3px}
        .vxlc-amt{font-weight:750}
        .vxlc-amt.is-neg{color:#b91c1c}
        .vxlc-amt.is-pos{color:#15803d}
        .vxlc-mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px}
        .vxlc-none{color:var(--vxw-faint,#94a3b8)}
        .vxlc-empty{padding:18px 20px;margin:0;color:var(--vxw-muted,#64748b);font-size:14px}
        .vxlc-pager{display:flex;gap:8px;align-items:center;justify-content:flex-end;padding:12px 20px;border-top:1px solid var(--vxw-border,#f0edf8)}
        .vxlc-pager button{min-width:36px;height:36px;border-radius:10px;border:1px solid var(--vxw-border,#e2e8f0);background:var(--vxw-surface,#fff);cursor:pointer;font-weight:700}
        .vxlc-pager button[disabled]{opacity:.4;cursor:default}
        .dark .vxlc-stat.is-total{background:linear-gradient(135deg,#221a3f,#2a2050);border-color:#3b2f6b}
        .dark .vxlc-pill--ship{background:#0c2a3d;color:#7dd3fc}.dark .vxlc-pill--refund{background:#3f1520;color:#fda4af}
        .dark .vxlc-pill--adjust{background:#3a2a0b;color:#fcd34d}.dark .vxlc-pill--muted{background:#1e2533;color:#cbd5e1}
        .dark .vxlc-a{color:#c4b5fd}.dark .vxlc-amt.is-neg{color:#fca5a5}.dark .vxlc-amt.is-pos{color:#86efac}
        .dark .vxlc-seg button[aria-pressed="true"]{color:#c4b5fd}
        @media (max-width:860px){
            .vxlc-lines thead{display:none}
            .vxlc-lines tr{display:grid;grid-template-columns:1fr auto;gap:6px 12px;padding:14px 16px;border-bottom:1px solid var(--vxw-border,#f0edf8)}
            .vxlc-lines td{padding:0;border:0}
            .vxlc-lines td[data-label]::before{content:attr(data-label);display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--vxw-muted,#64748b)}
            .vxlc-lines td.num{grid-column:2;grid-row:1}
            .vxlc-lines td.vxlc-when{grid-column:1;grid-row:1}
            .vxlc-lines td:not(.num):not(.vxlc-when){grid-column:1 / -1}
        }
    </style>

    <section class="vxlc-card">
        <div class="vxlc-top">
            <div class="vxlc-intro">
                <p>
                    @if($mine)
                        What Whatnot charged back on your shows: shipping charges, refunds and adjustments. A minus is a charge; a plus is money given back.
                    @else
                        Charges carried by Estill Distribution streamers (the only team charged for them): shipping charges, refunds and adjustments from approved ledger matches. A minus is a charge; a plus is money given back.
                    @endif
                </p>
                <button type="button" class="vxw-btn vxw-btn--sm" wire:click="export"><x-filament::icon icon="heroicon-m-arrow-down-tray" /> Export CSV</button>
            </div>
            <div class="vxlc-filters">
                <div class="vxlc-field"><span>Period</span>
                    <div class="vxlc-seg" role="group" aria-label="Period">
                        @foreach(\App\Livewire\LedgerCharges::PERIODS as $key => $label)
                            <button type="button" wire:click="$set('period', '{{ $key }}')" aria-pressed="{{ $period === $key ? 'true' : 'false' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                @if($period === 'custom')
                    <label class="vxlc-field">From <input type="date" wire:model.live="from"></label>
                    <label class="vxlc-field">To <input type="date" wire:model.live="until"></label>
                @endif
                @unless($mine)
                    <label class="vxlc-field">Streamer
                        <select wire:model.live="streamer" aria-label="Streamer">
                            <option value="">Everyone</option>
                            @foreach($this->streamerOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                    </label>
                @endunless
                <label class="vxlc-field">Type
                    <select wire:model.live="category" aria-label="Charge type">
                        <option value="">All charges</option>
                        @foreach($labels as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
            </div>
        </div>
        <div class="vxlc-stats">
            <div class="vxlc-stat is-total"><span>Total · {{ $from->format('M j') }} – {{ $until->format('M j, Y') }}</span><b class="vxlc-amt {{ $sum['totals']['total'] < 0 ? 'is-neg' : '' }}">{{ $signed($sum['totals']['total']) }}</b></div>
            @foreach($labels as $key => $label)
                <div class="vxlc-stat"><span>{{ $label }}</span><b>{{ $signed($sum['totals'][$key]) }}</b></div>
            @endforeach
            <div class="vxlc-stat"><span>Lines</span><b>{{ number_format($sum['entries']) }}</b></div>
        </div>
    </section>

    @unless($mine)
        <section class="vxlc-card">
            <div class="vxlc-head"><h3>By streamer</h3><span>Click a name to see only their lines</span></div>
            @if($sum['people'])
                <div class="vxlc-wrap">
                    <table class="vxlc-table">
                        <thead><tr><th scope="col">Streamer</th>@foreach($labels as $label)<th scope="col" class="num">{{ $label }}</th>@endforeach<th scope="col" class="num">Total</th><th scope="col" class="num">Lines</th></tr></thead>
                        <tbody>
                            @foreach($sum['people'] as $p)
                                <tr wire:key="p-{{ $p['streamer_id'] }}">
                                    <td><button type="button" class="vxlc-a" style="background:none;border:0;padding:0;cursor:pointer" wire:click="$set('streamer', {{ $p['streamer_id'] }})">{{ $p['streamer'] }}</button></td>
                                    @foreach(array_keys($labels) as $key)<td class="num">{{ $signed($p[$key]) }}</td>@endforeach
                                    <td class="num vxlc-amt {{ $p['total'] < 0 ? 'is-neg' : '' }}">{{ $signed($p['total']) }}</td>
                                    <td class="num">{{ $p['entries'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="vxlc-empty">No charges in this period.</p>
            @endif
        </section>
    @endunless

    <section class="vxlc-card">
        <div class="vxlc-head"><h3>{{ $mine ? 'Your charges' : 'Every charge' }}</h3><span>{{ number_format($this->total) }} {{ \Illuminate\Support\Str::plural('line', $this->total) }}</span></div>
        @if($this->lines->isNotEmpty())
            <div class="vxlc-wrap" wire:loading.class="opacity-50">
                <table class="vxlc-table vxlc-lines">
                    <thead><tr>
                        <th scope="col">Date</th>
                        @unless($mine)<th scope="col">Streamer</th>@endunless
                        <th scope="col">Charge</th>
                        <th scope="col">Show</th>
                        <th scope="col">Order &amp; buyer</th>
                        <th scope="col">Shipment</th>
                        <th scope="col" class="num">Amount</th>
                    </tr></thead>
                    <tbody>
                        @foreach($this->lines as $line)
                            @php
                                $order = $line->whatnot_order_id && $line->whatnot_order_id !== '-' ? $line->whatnot_order_id : null;
                                $orderUrl = filled($line->order_hash) ? 'https://www.whatnot.com/dashboard/orders/'.rawurlencode($line->order_hash) : null;
                            @endphp
                            <tr wire:key="l-{{ $line->id }}">
                                <td class="vxlc-when" data-label="Date"><span class="vxlc-strong" style="white-space:nowrap">{{ \Illuminate\Support\Carbon::parse($line->created_date)->timezone($tz)->format('M j') }}</span><span class="vxlc-sub">{{ \Illuminate\Support\Carbon::parse($line->created_date)->timezone($tz)->format('g:i A') }}</span></td>
                                @unless($mine)<td data-label="Streamer" class="vxlc-strong">{{ $line->streamer }}</td>@endunless
                                <td data-label="Charge">
                                    <span class="vxlc-pill vxlc-pill--{{ $tone[$line->category] ?? 'muted' }}">{{ $labels[$line->category] ?? $line->category }}</span>
                                    <span class="vxlc-sub">{{ $line->transaction_type }}</span>
                                    @if($line->message)<span class="vxlc-sub" title="{{ $line->message }}">{{ \Illuminate\Support\Str::limit($line->message, 70) }}</span>@endif
                                </td>
                                <td data-label="Show">
                                    @if($line->show_title)
                                        @if($canOpenShows && $line->show_id)
                                            <a class="vxlc-a" href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $line->show_id]) }}">{{ \Illuminate\Support\Str::limit($line->show_title, 48) }}</a>
                                        @else
                                            <span class="vxlc-strong">{{ \Illuminate\Support\Str::limit($line->show_title, 48) }}</span>
                                        @endif
                                        <span class="vxlc-sub">{{ $line->show_date ? \Illuminate\Support\Carbon::parse($line->show_date)->format('M j, Y') : '' }}{{ $line->channel ? ' · '.$line->channel : '' }}</span>
                                    @else
                                        <span class="vxlc-none">Not tied to a show</span>
                                        @if($line->channel)<span class="vxlc-sub">{{ $line->channel }}</span>@endif
                                    @endif
                                </td>
                                <td data-label="Order &amp; buyer">
                                    @if($order)
                                        @if($orderUrl)
                                            <a class="vxlc-a vxlc-mono" href="{{ $orderUrl }}" target="_blank" rel="noopener">#{{ $order }}</a>
                                        @elseif($isAdmin)
                                            <a class="vxlc-a vxlc-mono" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('index', ['q' => $order, 'period' => 'all']) }}">#{{ $order }}</a>
                                        @else
                                            <span class="vxlc-mono">#{{ $order }}</span>
                                        @endif
                                        @if($line->buyer)<span class="vxlc-sub">Buyer <b>{{ '@'.ltrim($line->buyer, '@') }}</b></span>@endif
                                        @if($line->item)<span class="vxlc-sub" title="{{ $line->item }}">{{ $line->lot ? 'Lot '.$line->lot.' · ' : '' }}{{ \Illuminate\Support\Str::limit($line->item, 44) }}{{ $line->order_total !== null ? ' · $'.number_format((float) $line->order_total, 2) : '' }}</span>@endif
                                    @else
                                        <span class="vxlc-none">No order</span>
                                    @endif
                                </td>
                                <td data-label="Shipment">
                                    @if($line->tracking || $line->carrier)
                                        <span class="vxlc-strong">{{ $line->carrier ? strtoupper($line->carrier) : 'Shipment' }}</span>
                                        @if($line->tracking)<span class="vxlc-sub vxlc-mono">{{ $line->tracking }}</span>@endif
                                        @if($line->shipment_status)<span class="vxlc-sub">{{ ucwords(str_replace('_', ' ', $line->shipment_status)) }}</span>@endif
                                    @else
                                        <span class="vxlc-none">—</span>
                                    @endif
                                </td>
                                <td data-label="Amount" class="num"><span class="vxlc-amt {{ (float) $line->amount < 0 ? 'is-neg' : 'is-pos' }}">{{ $signed($line->amount) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($pages > 1)
                <div class="vxlc-pager">
                    <button type="button" wire:click="setPage({{ $page - 1 }})" @disabled($page <= 1) aria-label="Previous page">‹</button>
                    <span class="vxlc-sub" style="margin:0">Page {{ $page }} of {{ $pages }}</span>
                    <button type="button" wire:click="setPage({{ $page + 1 }})" @disabled($page >= $pages) aria-label="Next page">›</button>
                </div>
            @endif
        @else
            <p class="vxlc-empty">{{ $mine ? 'No charges on your shows in this period.' : 'No charge lines for these filters.' }}</p>
        @endif
    </section>
</div>
