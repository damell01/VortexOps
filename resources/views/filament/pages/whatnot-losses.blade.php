<x-filament-panels::page>
    @php
        $rows = $this->rows();
        $t = $this->totals();
        $opts = $this->options();
        $money = fn ($v) => ($v < 0 ? '-$' : '$').number_format(abs($v), abs($v) >= 1000 ? 0 : 2);
        $tz = auth()->user()?->timezone ?: \App\Support\BusinessTime::zone();
        $sortHead = function (string $key, string $label) {
            $active = $this->sort === $key;
            $icon = $active ? ($this->dir === 'asc' ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down') : 'heroicon-m-chevron-up-down';
            return new \Illuminate\Support\HtmlString('<button type="button" wire:click="sortBy(\''.$key.'\')">'.e($label).' '.svg($icon, '', ['style' => 'width:14px;height:14px;opacity:'.($active ? 1 : .5)])->toHtml().'</button>');
        };
    @endphp

    <div class="vxr" data-vx-page="whatnot-losses">
        <div class="vxr-head">
            <div>
                <h1 class="vxr-title">Whatnot Losses</h1>
                <p class="vxr-subtitle">Shows with negative estimated Whatnot earnings.</p>
                <a class="vxr-link" style="margin-top:6px;font-size:14px" href="{{ \App\Filament\Pages\NotificationSettings::getUrl(['configure' => 'whatnot_negative']) }}">Manage summaries in Notification Settings</a>
            </div>
            <div class="vxr-actions">
                <button type="button" class="vxr-btn" wire:click="exportCsv"><x-filament::icon icon="heroicon-o-arrow-up-tray" /> Export CSV</button>
                <button type="button" class="vxr-btn vxr-btn--primary" wire:click="openSend"><x-filament::icon icon="heroicon-o-paper-airplane" /> Send Summary</button>
            </div>
        </div>

        <div class="vxr-toolbar">
            <label class="vxr-control">
                <x-filament::icon icon="heroicon-o-calendar" />
                <select wire:model.live="range" aria-label="Date range">
                    @foreach (\App\Filament\Pages\WhatnotLosses::RANGES as $k => $label)
                        <option value="{{ $k }}">{{ $k === 'custom' ? $label : $label }}</option>
                    @endforeach
                </select>
                <span style="color:var(--vxw-muted);white-space:nowrap">{{ $this->periodLabel() }}</span>
            </label>
            @if ($range === 'custom')
                <label class="vxr-control"><span style="color:var(--vxw-muted)">From</span><input type="date" wire:model.live="from"></label>
                <label class="vxr-control"><span style="color:var(--vxw-muted)">To</span><input type="date" wire:model.live="to"></label>
            @endif
            <select class="vxr-select" wire:model.live="business" aria-label="Business">
                <option value="">All businesses</option>
                @foreach ($opts['businesses'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <select class="vxr-select" wire:model.live="streamer" aria-label="Streamer">
                <option value="">All streamers</option>
                @foreach ($opts['streamers'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
        </div>

        <div class="vxr-stats">
            <x-vxr.stat label="Negative Shows" :value="$t['count']" icon="heroicon-o-arrow-trending-down" tone="bad" />
            <x-vxr.stat label="Estimated Whatnot Earnings" :value="$money($t['total'])" icon="heroicon-o-currency-dollar" tone="bad" :value-class="$t['total'] < 0 ? 'vxr-val-bad' : ''" />
            <x-vxr.stat label="Businesses Affected" :value="$t['businesses']" icon="heroicon-o-building-office-2" tone="info" />
        </div>

        <div class="vxr-banner"><x-filament::icon icon="heroicon-s-information-circle" /> Whatnot estimates may change as analytics refresh. Business costs and payroll are not included — see Business Profitability for those.</div>

        <div class="vxr-card">
            @if ($rows->isEmpty())
                <div class="vxr-empty"><b>No negative shows</b>No show had negative estimated Whatnot earnings in {{ $this->periodLabel() }}.</div>
            @else
                <div class="vxr-table-wrap">
                    <table class="vxr-table" style="min-width:820px">
                        <thead><tr>
                            <th>{{ $sortHead('show', 'Show') }}</th><th>{{ $sortHead('business', 'Business') }}</th><th>{{ $sortHead('streamer', 'Streamer') }}</th>
                            <th>{{ $sortHead('date', 'Show Date') }}</th><th class="num">{{ $sortHead('amount', 'Estimated Whatnot Earnings') }}</th><th>{{ $sortHead('synced', 'Last Synced') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($rows as $r)
                                <tr wire:key="wl-{{ $r['id'] }}">
                                    <td><a class="strong" style="color:inherit;text-decoration:none" href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $r['id']]) }}">{{ $r['title'] }}</a><span class="sub"><x-vx.channel :name="$r['channel']" /></span></td>
                                    <td>{{ $r['business'] }}</td>
                                    <td>{{ $r['streamer'] }}</td>
                                    <td style="white-space:nowrap">{{ $r['date']?->format('M j, Y') }}</td>
                                    <td class="num vxr-val-bad strong">{{ $money($r['whatnot_net']) }}</td>
                                    <td style="white-space:nowrap">{{ $r['synced_at'] ? $r['synced_at']->copy()->setTimezone($tz)->format('M j, Y g:i A') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="vxr-card-foot"><span>{{ $t['count'] }} {{ \Illuminate\Support\Str::plural('show', $t['count']) }}</span><span>Sorted by {{ ['show' => 'show', 'business' => 'business', 'streamer' => 'streamer', 'date' => 'date', 'synced' => 'last synced', 'amount' => 'estimated earnings'][$sort] ?? $sort }}</span></div>
            @endif
        </div>
    </div>

    @include('filament.components.vxr-send-summary', [
        'title' => 'Send Whatnot Loss Summary',
        'digest' => $this->sendOpen ? $this->lossDigest() : ['label' => '', 'count' => 0, 'total' => 0],
        'countLabel' => 'shows with negative earnings',
        'amountLabel' => 'estimated Whatnot earnings',
    ])
</x-filament-panels::page>
