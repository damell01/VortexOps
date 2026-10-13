<x-filament-panels::page>
@php
    $plan = $this->plan();
    $items = $this->visibleItems();
    $money = fn ($v) => '$'.number_format((float) $v, 0);
    $labels = [
        'move' => ['Move to right channel', 'wsc-pill--move'],
        'create' => ['Missing — create', 'wsc-pill--create'],
        'update' => ['Update figures', 'wsc-pill--update'],
        'extra' => ['Not on Whatnot\'s list', 'wsc-pill--extra'],
        'confirmed' => ['Confirmed real', 'wsc-pill--ok'],
        'skip_empty' => ['No sales — skipped', 'wsc-pill--muted'],
        'ok' => ['Lines up', 'wsc-pill--ok'],
    ];
    $fieldNames = ['gross_revenue' => 'Est. sales', 'whatnot_net' => 'Est. earnings', 'show_duration' => 'Minutes', 'buyers_count' => 'Buyers',
        'avg_order_value' => 'AOV', 'giveaway_spend' => 'Giveaway spend', 'giveaways_count' => 'Giveaways', 'first_time_buyers' => 'First-time buyers',
        'returning_buyers' => 'Returning buyers', 'shares_count' => 'Shares', 'max_concurrent_viewers' => 'Max viewers', 'total_views' => 'Views', 'avg_order_rating' => 'Rating'];
@endphp

<div class="wsc">
    @if (! $files)
        <section class="wsc-card wsc-empty">
            <h2>How it works</h2>
            <ol>
                <li>In Whatnot Seller Hub, switch to a channel's account, open <b>Analytics → Shows</b>, set the dates (from 6/1), choose <b>Show 100</b> and <b>Download</b> each page.</li>
                <li>Do the same for every channel, then click <b>Upload exports</b> and drop each channel's files in its box.</li>
                <li>Review: shows in the wrong channel, shows missing from the app, figures that differ, and totals side by side.</li>
                <li><b>Apply changes</b> moves and creates the shows and fills in Whatnot's figures. Shows the app has that Whatnot doesn't list are only reported.</li>
            </ol>
        </section>
    @endif

    @if ($files)
        <section class="wsc-card">
            <h3 class="wsc-h">Uploaded</h3>
            <div class="wsc-files">
                @foreach ($files as $channelId => $list)
                    @php $stats = $plan['channels'][$channelId]['files'] ?? []; @endphp
                    <div><b>{{ \App\Models\WhatnotChannel::whereKey($channelId)->value('name') }}</b>:
                        @foreach ($list as $f)
                            @php $st = $stats[basename($f['path'])] ?? null; @endphp
                            <span class="wsc-file @if ($st && $st['rows'] > 0 && $st['new'] === 0) is-dupe @endif">{{ $f['name'] }}@if ($st) <span class="wsc-muted">({{ $st['rows'] }} rows{{ $st['first'] ? ', '.\Carbon\Carbon::parse($st['last'])->format('M j').'–'.\Carbon\Carbon::parse($st['first'])->format('M j') : '' }})</span>@if ($st['rows'] > 0 && $st['new'] === 0) <b class="wsc-short">same page as another file</b>@endif @endif</span>@unless ($loop->last), @endunless
                        @endforeach
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($result)
        <section class="wsc-card wsc-done">
            <b>Applied.</b> {{ $result['moved'] }} moved to the right channel · {{ $result['created'] }} created · {{ $result['updated'] }} updated · {{ $result['deleted'] ?? 0 }} deleted.
            @if ($result['failed'])
                <div class="wsc-failed">Failed: @foreach ($result['failed'] as $what => $why) <div>{{ $what }} — {{ $why }}</div> @endforeach</div>
            @endif
            <div class="wsc-muted">The comparison below is re-checked after the changes.</div>
        </section>
    @endif

    @if ($plan)
        <div class="wsc-grid">
            @unless ($plan['all_channels'] ?? false)
                <section class="wsc-card wsc-warn">Not uploaded yet: <b>{{ implode(', ', $plan['missing_channels'] ?? []) }}</b>. Shows are still moved and created, but nothing is deleted until every channel's export is here.</section>
            @endunless
            @foreach ($plan['channels'] as $channelId => $c)
                @php
                    $cShows = $c['counts']['confirmed'] ?? 0; $cSales = $c['counts']['confirmed_sales'] ?? 0;
                    $lines = abs($c['sheet']['shows'] - ($c['app']['shows'] - $cShows)) <= 1 && abs($c['sheet']['sales'] - ($c['app']['sales'] - $cSales)) < max(25, $c['sheet']['sales'] * 0.002);
                @endphp
                <section class="wsc-card wsc-channel">
                    <header>
                        <h3>{{ $c['name'] }}</h3>
                        <span class="wsc-muted">{{ \Carbon\Carbon::parse($c['from'])->format('M j') }} – {{ \Carbon\Carbon::parse($c['to'])->format('M j, Y') }}</span>
                        <span @class(['wsc-pill', $lines ? 'wsc-pill--ok' : 'wsc-pill--extra'])>{{ $lines ? 'Lines up' : 'Differs' }}</span>
                    </header>
                    @if (($c['aired_after_last_row'] ?? 0) > 0 && $c['last_row'] && $c['last_row'] < \Carbon\Carbon::parse($c['to'])->subDays(2)->toDateString())
                        <div class="wsc-warn wsc-warn--inline">Whatnot's rows stop at <b>{{ \Carbon\Carbon::parse($c['last_row'])->format('M j') }}</b>, but the app has <b>{{ $c['aired_after_last_row'] }}</b> shows with sales after that. The newest page of this channel's download is probably missing — check the show count on Whatnot's Analytics Overview and add that page.</div>
                    @endif
                    <table class="wsc-totals">
                        <thead><tr><th></th><th>Whatnot</th><th>App</th></tr></thead>
                        <tbody>
                            <tr><td>Shows</td><td>{{ number_format($c['sheet']['shows']) }}</td><td>{{ number_format($c['app']['shows']) }}</td></tr>
                            <tr><td>Est. sales</td><td>{{ $money($c['sheet']['sales']) }}</td><td>{{ $money($c['app']['sales']) }}</td></tr>
                            <tr><td>Est. earnings</td><td>{{ $money($c['sheet']['earnings']) }}</td><td>{{ $money($c['app']['earnings']) }}</td></tr>
                            <tr><td>Orders</td><td>{{ number_format($c['sheet']['orders']) }}</td><td>{{ number_format($c['app']['orders']) }} <span class="wsc-muted">imported</span></td></tr>
                        </tbody>
                    </table>
                    <div class="wsc-counts">
                        @foreach (['move' => 'to move here', 'create' => 'missing', 'update' => 'figures to update', 'extra' => 'not on Whatnot\'s list', 'orders_short' => 'missing some orders'] as $k => $label)
                            @if ($c['counts'][$k] ?? 0)<span><b>{{ $c['counts'][$k] }}</b> {{ $label }}</span>@endif
                        @endforeach
                        @if ($cShows)<span class="wsc-muted">App includes {{ $cShows }} confirmed {{ \Illuminate\Support\Str::plural('show', $cShows) }} Whatnot leaves out ({{ $money($cSales) }})</span>@endif
                        @if (! array_filter(\Illuminate\Support\Arr::only($c['counts'], ['move', 'create', 'update', 'extra'])))<span class="wsc-muted">Every show matched.</span>@endif
                    </div>
                </section>
            @endforeach
        </div>

        <section class="wsc-card">
            <div class="wsc-bar">
                <div class="wsc-seg">
                    @php $all = collect($plan['items']); @endphp
                    @foreach (['changes' => 'Changes', 'move' => 'Wrong channel', 'create' => 'Missing', 'update' => 'Figures', 'extra' => 'Not on Whatnot', 'confirmed' => 'Confirmed real', 'orders_short' => 'Orders short', 'all' => 'Everything'] as $key => $label)
                        @php
                            $n = match ($key) {
                                'changes' => $all->whereIn('action', ['move', 'create', 'update', 'extra'])->count(),
                                'all' => $all->count(),
                                'orders_short' => $all->filter(fn ($it) => $it['sheet_orders'] !== null && $it['app_orders'] < $it['sheet_orders'])->count(),
                                default => $all->where('action', $key)->count(),
                            };
                        @endphp
                        <button type="button" wire:click="setFilter('{{ $key }}')" @class(['is-on' => $filter === $key])>{{ $label }} <span>{{ $n }}</span></button>
                    @endforeach
                </div>
                {{ $this->applyAction }}
            </div>

            @if ($filter === 'extra')
                <p class="wsc-note">These are in the app for that channel on days the export covers, but no Whatnot export lists them — usually a duplicate or a test. When every channel's export is uploaded, Apply can delete them; ones with finalized pay or an approved report are kept for you to check.</p>
            @elseif ($filter === 'orders_short')
                <p class="wsc-note">Whatnot counts more orders for these shows than the app has imported. The orders backfill fills them in; the figures above don't depend on it.</p>
            @endif

            <div class="wsc-table-wrap">
                <table class="wsc-table">
                    <thead><tr><th>What</th><th>Show</th><th>Date</th><th>Channel</th><th>Details</th><th>Orders<br><span class="wsc-muted">Whatnot / app</span></th></tr></thead>
                    <tbody>
                        @forelse ($items as $it)
                            <tr wire:key="it-{{ $loop->index }}-{{ $it['show_id'] }}-{{ $it['date'] }}">
                                <td><span class="wsc-pill {{ $labels[$it['action']][1] }}">{{ $labels[$it['action']][0] }}</span></td>
                                <td class="wsc-title">
                                    @if ($it['show_id'])
                                        <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $it['show_id']]) }}" target="_blank">{{ $it['title'] }}</a>
                                    @else
                                        {{ $it['title'] }}
                                    @endif
                                </td>
                                <td class="wsc-nowrap">{{ \Carbon\Carbon::parse($it['date'])->format('M j') }}@if ($it['app_date'] && $it['app_date'] !== $it['date'])<div class="wsc-muted">app: {{ \Carbon\Carbon::parse($it['app_date'])->format('M j') }}</div>@endif</td>
                                <td class="wsc-nowrap">@if ($it['from_channel'])<span class="wsc-muted">{{ $it['from_channel'] }} →</span> @endif{{ $it['channel'] }}</td>
                                <td class="wsc-details">
                                    @if ($it['changes'])
                                        @foreach (array_slice($it['changes'], 0, 4, true) as $field => [$before, $after])
                                            <div>{{ $fieldNames[$field] ?? $field }}: <s>{{ $before === null ? '—' : (is_numeric($before) ? rtrim(rtrim(number_format($before, 2), '0'), '.') : $before) }}</s> → <b>{{ rtrim(rtrim(number_format((float) $after, 2), '0'), '.') }}</b></div>
                                        @endforeach
                                        @if (count($it['changes']) > 4)<div class="wsc-muted">+{{ count($it['changes']) - 4 }} more</div>@endif
                                    @elseif ($it['action'] === 'create')
                                        {{ $money($it['metrics']['gross_revenue'] ?? 0) }} sales · {{ $it['metrics']['show_duration'] ?? 0 }} min
                                    @elseif ($it['action'] === 'extra')
                                        @if (! empty($it['duplicate_of']))
                                            Duplicate of <a class="wsc-link" href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $it['duplicate_of']['show_id']]) }}" target="_blank">{{ \Illuminate\Support\Str::limit($it['duplicate_of']['title'], 50) }}</a>{{ $it['duplicate_of']['date'] ? ' ('.\Carbon\Carbon::parse($it['duplicate_of']['date'])->format('M j').')' : '' }} — {{ match ($it['duplicate_of']['by'] ?? '') { 'live_id' => 'same Whatnot stream', 'title' => 'same title, and only that one is on Whatnot\'s list', 'shipments' => 'all '.$it['duplicate_of']['orders'].' of its packages shipped from that show', default => 'all '.$it['duplicate_of']['orders'].' of its orders are on that show' } }}. Deleted on apply.
                                        @elseif (($it['app_orders'] ?? 0) > 0 || ! empty($it['aired']))<span class="wsc-short">Kept: it has {{ ($it['app_orders'] ?? 0) > 0 ? $it['app_orders'].' imported orders' : 'sales' }}, so it aired — likely renamed on Whatnot or on a channel/page not uploaded</span>
                                        @elseif (! empty($it['blocked']))<span class="wsc-short">Kept unless you switch on deleting ones with pay: {{ $it['blocked'] }}</span>@elseif ($plan['all_channels'] ?? false)Deleted on apply (if you leave the switch on)@else<span class="wsc-muted">Upload every channel to delete</span>@endif
                                    @elseif ($it['action'] === 'confirmed')
                                        <span class="wsc-muted">You confirmed it aired on this channel; Whatnot's analytics leaves it out. {{ $money($it['gross'] ?? 0) }} sales.</span>
                                    @elseif ($it['action'] === 'move' && ! $it['changes'])
                                        <span class="wsc-muted">Figures already match</span>
                                    @endif
                                </td>
                                <td class="wsc-nowrap">@if ($it['action'] === 'extra' && empty($it['duplicate_of']) && (! empty($it['aired']) || ! empty($it['blocked']) || ($it['app_orders'] ?? 0) > 0))<button type="button" class="wsc-keep" wire:click="confirmReal({{ $it['show_id'] }})" title="You checked it on Whatnot: it aired on this channel. It stays and is no longer flagged.">Confirmed real — keep</button><br><button type="button" class="wsc-del" wire:click="deleteUnlisted({{ $it['show_id'] }})" wire:confirm="Delete this show? It is not on any Whatnot export. Its pay lines and imported orders go with it.">Delete</button><br>@endif @if ($it['action'] === 'confirmed')<button type="button" class="wsc-link" wire:click="confirmReal({{ $it['show_id'] }}, false)">Flag again</button><br>@endif @if (! empty($it['live_url']))<a class="wsc-link" href="{{ $it['live_url'] }}" target="_blank" rel="noopener">Whatnot ↗</a><br>@endif{{ $it['sheet_orders'] ?? '—' }} / <span @class(['wsc-short' => $it['sheet_orders'] !== null && $it['app_orders'] < $it['sheet_orders']])>{{ $it['app_orders'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="wsc-muted" style="text-align:center;padding:24px">Nothing in this list.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>

<style>
.wsc{display:grid;gap:16px}
.wsc-card{border:1px solid var(--vx-border,#e5e7eb);border-radius:14px;background:#fff;padding:16px 18px}
.dark .wsc-card{background:#111827;border-color:#1f2937}
.wsc-empty h2{font-size:16px;font-weight:800;margin-bottom:8px}
.wsc-empty ol{list-style:decimal;padding-left:20px;display:grid;gap:6px;font-size:14px}
.wsc-h{font-size:13px;font-weight:800;margin-bottom:6px}
.wsc-files{display:grid;gap:4px;font-size:13px}
.wsc-file.is-dupe{background:#fef3c7;border-radius:6px;padding:0 4px}
.wsc-done{border-color:#86efac;background:#f0fdf4}
.dark .wsc-done{background:#052e16;border-color:#166534}
.wsc-failed{margin-top:6px;color:#b91c1c;font-size:13px}
.wsc-muted{color:#6b7280;font-size:12px}
.wsc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}
.wsc-channel header{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px}
.wsc-channel h3{font-size:15px;font-weight:800;margin-right:auto}
.wsc-totals{width:100%;font-size:13px;border-collapse:collapse}
.wsc-totals th{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;text-align:right;font-weight:700;padding:4px 0}
.wsc-totals th:first-child,.wsc-totals td:first-child{text-align:left;color:#6b7280}
.wsc-totals td{text-align:right;padding:4px 0;font-variant-numeric:tabular-nums;font-weight:600;border-top:1px solid var(--vx-border,#f3f4f6)}
.wsc-counts{display:flex;flex-wrap:wrap;gap:6px 12px;margin-top:10px;font-size:12.5px}
.wsc-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:10px}
.wsc-seg{display:flex;flex-wrap:wrap;gap:4px}
.wsc-seg button{padding:6px 10px;border-radius:9px;font-size:12.5px;font-weight:700;color:#6b7280;border:1px solid var(--vx-border,#e5e7eb)}
.wsc-seg button span{margin-left:4px;font-weight:600;opacity:.75}
.wsc-seg button.is-on{background:var(--primary-600,#7c3aed);border-color:transparent;color:#fff}
.wsc-note{font-size:13px;color:#6b7280;margin:0 0 10px}
.wsc-table-wrap{overflow-x:auto}
.wsc-table{width:100%;border-collapse:collapse;font-size:13px}
.wsc-table th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;padding:8px;border-bottom:1px solid var(--vx-border,#e5e7eb)}
.wsc-table td{padding:8px;border-bottom:1px solid var(--vx-border,#f3f4f6);vertical-align:top}
.dark .wsc-table td{border-color:#1f2937}
.wsc-title{min-width:240px;font-weight:600}
.wsc-title a{color:inherit;text-decoration:underline;text-decoration-color:#d1d5db;text-underline-offset:2px}
.wsc-details{font-size:12.5px;min-width:200px}
.wsc-nowrap{white-space:nowrap}
.wsc-short{color:#b45309;font-weight:700}
.wsc-keep{font-size:12px;font-weight:800;color:#166534;border:1px solid #bbf7d0;border-radius:7px;padding:1px 8px;margin-bottom:4px;white-space:nowrap}
.wsc-del{font-size:12px;font-weight:800;color:#b91c1c;border:1px solid #fecaca;border-radius:7px;padding:1px 8px;margin-bottom:4px}
.wsc-link{color:var(--primary-600,#7c3aed);text-decoration:underline;text-underline-offset:2px}
.wsc-warn--inline{margin:0 0 8px;padding:8px 10px;border:1px solid #fcd34d;border-radius:10px;font-size:12.5px}
.wsc-warn{grid-column:1/-1;border-color:#fcd34d;background:#fffbeb;font-size:13px}
.dark .wsc-warn{background:#422006;border-color:#92400e}
.wsc-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:800;white-space:nowrap;background:#f3f4f6;color:#374151}
.wsc-pill--move{background:#fee2e2;color:#991b1b}
.wsc-pill--create{background:#dbeafe;color:#1e40af}
.wsc-pill--update{background:#ede9fe;color:#5b21b6}
.wsc-pill--extra{background:#fef3c7;color:#92400e}
.wsc-pill--ok{background:#dcfce7;color:#166534}
.wsc-pill--muted{background:#f3f4f6;color:#6b7280}
.dark .wsc-pill{background:#1f2937;color:#d1d5db}
</style>
</x-filament-panels::page>
