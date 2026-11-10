@php
    [$from, $until] = $this->range;
    $sum = $this->summary;
    $labels = \App\Services\LedgerChargeReport::LABELS;
    $signed = fn ($v) => ((float) $v < 0 ? '−' : '') . '$' . number_format(abs((float) $v), 2);
    $tz = \App\Support\BusinessTime::zone();
    $mine = (bool) $lockedStreamerId;
    $pages = $this->pageCount();
@endphp
<div class="vxw" data-vx-page="ledger-charges">
    <section class="vxw-card">
        <div class="vxw-card-body" style="display:grid;gap:14px">
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
                <p class="vxw-meta" style="margin:0;max-width:720px">
                    @if($mine)
                        Ledger corrections Whatnot charged on your shows: shipping charges, refunds and order refund shipping charges. A minus is a charge; a plus is money given back.
                    @else
                        Ledger corrections charged to Estill Distribution streamers (the only team charged for them): shipping charges, refunds and order refund shipping charges, from approved ledger matches. A minus is a charge; a plus is money given back.
                    @endif
                </p>
                <button type="button" class="vxw-btn vxw-btn--sm" wire:click="export"><x-filament::icon icon="heroicon-m-arrow-down-tray" /> Export CSV</button>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:end">
                <div class="vxw-tabs" role="group" aria-label="Period" style="display:flex;flex-wrap:wrap;gap:6px">
                    @foreach(\App\Livewire\LedgerCharges::PERIODS as $key => $label)
                        <button type="button" class="vxw-btn vxw-btn--sm {{ $period === $key ? 'vxw-btn--primary' : 'vxw-btn--ghost' }}" wire:click="$set('period', '{{ $key }}')" aria-pressed="{{ $period === $key ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>
                @if($period === 'custom')
                    <label class="vxw-meta">From <input type="date" class="vxw-input" wire:model.live="from"></label>
                    <label class="vxw-meta">To <input type="date" class="vxw-input" wire:model.live="until"></label>
                @endif
                @unless($mine)
                    <label class="vxw-meta">Streamer
                        <select class="vxw-input" wire:model.live="streamer">
                            <option value="">Everyone</option>
                            @foreach($this->streamerOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                    </label>
                @endunless
                <label class="vxw-meta">Type
                    <select class="vxw-input" wire:model.live="category">
                        <option value="">All charges</option>
                        @foreach($labels as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
            </div>
        </div>
        <div class="vxw-stats">
            <div class="vxw-stat"><div class="vxw-stat-label">Total · {{ $from->format('M j') }} – {{ $until->format('M j, Y') }}</div><div class="vxw-stat-value {{ $sum['totals']['total'] < 0 ? 'vxw-tone-warn' : '' }}">{{ $signed($sum['totals']['total']) }}</div></div>
            @foreach($labels as $key => $label)
                <div class="vxw-stat"><div class="vxw-stat-label">{{ $label }}</div><div class="vxw-stat-value">{{ $signed($sum['totals'][$key]) }}</div></div>
            @endforeach
            <div class="vxw-stat"><div class="vxw-stat-label">Lines</div><div class="vxw-stat-value">{{ number_format($sum['entries']) }}</div></div>
        </div>
    </section>

    @unless($mine)
        <section class="vxw-card" style="margin-top:16px">
            <div class="vxw-card-head"><h3 class="vxw-h2">By streamer</h3><span class="vxw-meta">Click a name to see only their lines</span></div>
            @if($sum['people'])
                <div class="vxw-table-wrap" style="overflow-x:auto">
                    <table class="vxw-table" style="width:100%">
                        <thead><tr><th scope="col" style="text-align:left">Streamer</th>@foreach($labels as $label)<th scope="col" style="text-align:right">{{ $label }}</th>@endforeach<th scope="col" style="text-align:right">Total</th><th scope="col" style="text-align:right">Lines</th></tr></thead>
                        <tbody>
                            @foreach($sum['people'] as $p)
                                <tr wire:key="p-{{ $p['streamer_id'] }}">
                                    <td><button type="button" class="vxw-link" wire:click="$set('streamer', {{ $p['streamer_id'] }})" style="font-weight:600">{{ $p['streamer'] }}</button></td>
                                    @foreach(array_keys($labels) as $key)<td style="text-align:right;font-variant-numeric:tabular-nums">{{ $signed($p[$key]) }}</td>@endforeach
                                    <td style="text-align:right;font-weight:700;font-variant-numeric:tabular-nums">{{ $signed($p['total']) }}</td>
                                    <td style="text-align:right">{{ $p['entries'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="vxw-meta" style="padding:16px 18px;margin:0">No charges in this period.</p>
            @endif
        </section>
    @endunless

    <section class="vxw-card" style="margin-top:16px">
        <div class="vxw-card-head"><h3 class="vxw-h2">{{ $mine ? 'Your charges' : 'Every charge' }}</h3><span class="vxw-meta">{{ number_format($this->total) }} {{ \Illuminate\Support\Str::plural('line', $this->total) }}</span></div>
        @if($this->lines->isNotEmpty())
            <div style="overflow-x:auto" wire:loading.class="opacity-50">
                <table class="vxw-table" style="width:100%">
                    <thead><tr><th scope="col" style="text-align:left">Date</th>@unless($mine)<th scope="col" style="text-align:left">Streamer</th>@endunless<th scope="col" style="text-align:left">What</th><th scope="col" style="text-align:left">Show</th><th scope="col" style="text-align:left">Order</th><th scope="col" style="text-align:right">Amount</th></tr></thead>
                    <tbody>
                        @foreach($this->lines as $line)
                            <tr wire:key="l-{{ $line->id }}">
                                <td style="white-space:nowrap">{{ \Illuminate\Support\Carbon::parse($line->created_date)->timezone($tz)->format('M j, g:i A') }}</td>
                                @unless($mine)<td>{{ $line->streamer }}</td>@endunless
                                <td><strong>{{ $labels[$line->category] ?? $line->category }}</strong><div class="vxw-meta">{{ $line->transaction_type }}{{ $line->message ? ' · ' . \Illuminate\Support\Str::limit($line->message, 80) : '' }}</div></td>
                                <td>{{ $line->show_title ?? '—' }}<div class="vxw-meta">{{ $line->channel }}{{ $line->show_date ? ' · ' . \Illuminate\Support\Carbon::parse($line->show_date)->format('M j') : '' }}</div></td>
                                <td class="vxw-meta">{{ $line->whatnot_order_id && $line->whatnot_order_id !== '-' ? $line->whatnot_order_id : '—' }}</td>
                                <td style="text-align:right;font-weight:700;font-variant-numeric:tabular-nums;{{ (float) $line->amount < 0 ? 'color:#b91c1c' : 'color:#15803d' }}">{{ $signed($line->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($pages > 1)
                <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end;padding:12px 18px">
                    <button type="button" class="vxw-btn vxw-btn--sm vxw-btn--ghost" wire:click="setPage({{ $page - 1 }})" @disabled($page <= 1) aria-label="Previous page">‹</button>
                    <span class="vxw-meta">Page {{ $page }} of {{ $pages }}</span>
                    <button type="button" class="vxw-btn vxw-btn--sm vxw-btn--ghost" wire:click="setPage({{ $page + 1 }})" @disabled($page >= $pages) aria-label="Next page">›</button>
                </div>
            @endif
        @else
            <p class="vxw-meta" style="padding:16px 18px;margin:0">{{ $mine ? 'No charges on your shows in this period.' : 'No charge lines for these filters.' }}</p>
        @endif
    </section>
</div>
