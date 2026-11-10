<x-filament-panels::page>
@php
    $manager = $this->isManager();
    $tz = \App\Support\BusinessTime::zone();
    [$rFrom, $rUntil] = $this->range;
    $k = $this->kpis;
    $daily = $this->daily;
    $maxDay = max(1, collect($daily)->max('count'));
    // A round axis top: 1, 2, 5 × 10^n just above the busiest day.
    $mag = 10 ** max(0, (int) floor(log10($maxDay)));
    $axisTop = collect([1, 2, 5, 10])->map(fn ($m) => $m * $mag)->first(fn ($v) => $v >= $maxDay) ?? $maxDay;
    $labelEvery = max(1, (int) ceil(count($daily) / 10));
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $maxPk = max(1, $this->packers->max('packages') ?? 1);
@endphp
@include('fulfillment.dev-banner')
<div class="vxf vxf-reports">
    <div class="vxf-head">
        <div>
            <h1>{{ $manager ? 'Fulfillment Reports' : 'My Reports' }}</h1>
            <p>{{ $rFrom->format('M j') }}{{ $rFrom->isSameDay($rUntil) ? '' : ' – ' . $rUntil->format('M j, Y') }} · built automatically from Start, Mark Packed and Finish</p>
        </div>
        <div class="vxf-actions">
            <div class="vxf-tabs" role="group" aria-label="Period">
                @foreach (\App\Filament\Pages\FulfillmentReports::PERIODS as $key => $label)
                    <button type="button" class="vxf-tab" aria-selected="{{ $period === (string) $key ? 'true' : 'false' }}" wire:click="$set('period', '{{ $key }}')">{{ $label }}</button>
                @endforeach
            </div>
            @if ($period === 'custom')
                <label class="sr-only" for="vxfr-from">From</label><input id="vxfr-from" type="date" class="vxf-input vxfr-date" wire:model.live="from" value="{{ $rFrom->toDateString() }}">
                <label class="sr-only" for="vxfr-until">Until</label><input id="vxfr-until" type="date" class="vxf-input vxfr-date" wire:model.live="until" value="{{ $rUntil->toDateString() }}">
            @endif
            <a class="vxf-btn" href="{{ \App\Filament\Pages\FulfillmentHome::getUrl() }}"><x-heroicon-o-home /> Fulfillment Home</a>
        </div>
    </div>

    {{-- Headline numbers --}}
    <section class="vxfr-kpis" aria-label="Totals for the period">
        @foreach ([
            ['Packages packed', number_format($k['packages']), $k['buyers'] . ' buyers', 'heroicon-o-cube', 'brand'],
            ['Shows completed', $k['shows'], $k['awaiting'] ? $k['awaiting'] . ' waiting for sign-off' : 'all signed off', 'heroicon-o-check-badge', 'ok'],
            ['Avg. packing time', $k['avg_minutes'] !== null ? \App\Filament\Pages\FulfillmentReports::hours($k['avg_minutes'] * 60) : '—', 'per show, pauses excluded', 'heroicon-o-clock', 'info'],
            ['Packages per hour', $k['per_hour'] ?? '—', 'on finished shows', 'heroicon-o-bolt', 'brand'],
            ['Issues', $k['issues'], $k['issues_open'] ? $k['issues_open'] . ' still open' : 'all resolved', 'heroicon-o-exclamation-triangle', $k['issues_open'] ? 'bad' : 'ok'],
        ] as [$label, $value, $sub, $icon, $tone])
            <div class="vxfr-kpi vxfr-kpi--{{ $tone }}">
                <span class="vxfr-kpi__icon" aria-hidden="true"><x-dynamic-component :component="$icon" /></span>
                <div><p class="vxfr-kpi__label">{{ $label }}</p><p class="vxfr-kpi__value vxf-num" x-data x-init="window.vxfCountUp && vxfCountUp($el)">{{ $value }}</p><p class="vxfr-kpi__sub">{{ $sub }}</p></div>
            </div>
        @endforeach
    </section>
    @include('fulfillment.motion')

    <div class="vxfr-grid">
        {{-- Packages per day --}}
        <section class="vxf-card vxfr-chart" aria-labelledby="vxfr-daily">
            <div class="vxf-card__head"><h2 id="vxfr-daily">Packages packed per day</h2>
                <span class="vxf-sub">Busiest: {{ collect($daily)->sortByDesc('count')->first()['label'] ?? '—' }} · {{ $maxDay }}</span></div>
            <div class="vxfr-plot" role="img" aria-label="Bar chart of packages packed per day; the table below lists the same numbers">
                <div class="vxfr-plot__grid" aria-hidden="true">
                    @foreach ([1, .5, 0] as $f)<span style="bottom: {{ $f * 100 }}%"><i>{{ (int) round($axisTop * $f) }}</i></span>@endforeach
                </div>
                <div class="vxfr-plot__bars" style="--n: {{ count($daily) }}">
                    @foreach ($daily as $i => $d)
                        <div class="vxfr-bar" tabindex="0" style="--h: {{ $axisTop ? round($d['count'] / $axisTop * 100, 2) : 0 }}%" aria-label="{{ $d['label'] }}: {{ $d['count'] }} packages">
                            <span class="vxfr-bar__fill"></span>
                            <span class="vxfr-tip" role="tooltip"><b>{{ $d['count'] }}</b> packages<br><small>{{ $d['short'] }}, {{ $d['label'] }}</small></span>
                            <span class="vxfr-bar__x" aria-hidden="true">{{ $i % $labelEvery === 0 ? (count($daily) <= 7 ? $d['short'] : $d['label']) : '' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <details class="vxfr-table-toggle"><summary>Show as a table</summary>
                <table class="vxf-table"><thead><tr><th scope="col">Day</th><th scope="col">Packages</th></tr></thead>
                    <tbody>@foreach ($daily as $d)<tr><td>{{ $d['short'] }}, {{ $d['label'] }}</td><td class="vxf-num">{{ $d['count'] }}</td></tr>@endforeach</tbody></table>
            </details>
        </section>

        {{-- Issues by type --}}
        <section class="vxf-card" aria-labelledby="vxfr-issues">
            <div class="vxf-card__head"><h2 id="vxfr-issues">Issues by type</h2>
                <a class="vxf-btn vxf-btn--sm" href="{{ \App\Filament\Pages\FulfillmentActivity::getUrl(['tab' => 'issues']) }}">Open issues</a></div>
            @if ($this->issueTypes->isEmpty())
                <div class="vxfr-empty"><x-heroicon-o-face-smile /><p>No issues flagged in this period.</p></div>
            @else
                @php $maxIssue = max(1, $this->issueTypes->max('n')); @endphp
                <ul class="vxfr-hbars">
                    @foreach ($this->issueTypes as $t)
                        <li tabindex="0" aria-label="{{ $t['label'] }}: {{ $t['n'] }}, {{ $t['open'] }} open">
                            <span class="vxfr-hbars__label">{{ $t['label'] }}</span>
                            <span class="vxfr-hbars__track"><span style="width: {{ round($t['n'] / $maxIssue * 100) }}%"></span></span>
                            <span class="vxf-num vxfr-hbars__n">{{ $t['n'] }}@if ($t['open'])<small>{{ $t['open'] }} open</small>@endif</span>
                        </li>
                    @endforeach
                </ul>
                <h3 class="vxfr-subhead">Latest</h3>
                <ul class="vxf-feed">
                    @foreach ($this->recentIssues as $f)
                        <li class="vxf-feed__item vxf-feed__item--{{ $f->status === 'open' ? 'bad' : 'ok' }}">
                            <span class="vxf-feed__icon" aria-hidden="true">@if ($f->status === 'open')<x-heroicon-o-exclamation-triangle />@else<x-heroicon-o-check />@endif</span>
                            <span class="vxf-feed__text"><strong>{{ \App\Services\FulfillmentFlagService::label($f->type) }}</strong> · {{ $f->status === 'open' ? 'open' : 'resolved' }}
                                <span class="vxf-sub">{{ $f->show?->title }}@if ($f->buyer) · {{ $f->buyer->letter }} {{ $f->buyer->buyer_username }}@endif · {{ $f->reporter?->name }}</span></span>
                            <time class="vxf-sub vxf-num">{{ $f->created_at?->timezone($tz)->format('M j') }}</time>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- Packer summary: the digital pay sheet --}}
    <section class="vxf-card" aria-labelledby="vxfr-packers">
        <div class="vxf-card__head">
            <div><h2 id="vxfr-packers">{{ $manager ? 'Packer summary' : 'My summary' }}</h2>
                <span class="vxf-sub">Replaces the per-show tracking sheets: counted from who marked each package, time from each show's start to finish.</span></div>
            <button type="button" class="vxf-btn vxf-btn--sm" wire:click="exportPackers"><x-heroicon-o-arrow-down-tray /> Export CSV</button>
        </div>
        @if ($this->packers->isEmpty())
            <div class="vxfr-empty"><x-heroicon-o-cube /><p>Nothing packed in this period yet.</p></div>
        @else
            <div class="vxf-table-wrap">
                <table class="vxf-table">
                    <thead><tr><th scope="col">Packer</th><th scope="col">Packages</th><th scope="col">Buyers</th><th scope="col">Shows</th><th scope="col">Hours on station</th><th scope="col">Pkgs / hour</th><th scope="col">Issues reported</th></tr></thead>
                    <tbody>
                        @foreach ($this->packers as $row)
                            <tr>
                                <th scope="row"><span class="vxfr-person"><span class="vxf-avatar" aria-hidden="true">{{ $initials($row['user']->name) }}</span>{{ $row['user']->name }}</span></th>
                                <td class="vxf-num" style="min-width:150px"><strong>{{ $row['packages'] }}</strong><div class="vxf-bar vxf-bar--mini" aria-hidden="true"><span style="width: {{ round($row['packages'] / $maxPk * 100) }}%"></span></div></td>
                                <td class="vxf-num">{{ $row['buyers'] }}</td>
                                <td class="vxf-num">{{ $row['shows'] }}</td>
                                <td class="vxf-num">{!! $row['hours'] > 0 ? number_format($row['hours'], 1) . ' h' : '<span class="vxf-sub" title="Counted when the show finishes">in progress</span>' !!}</td>
                                <td class="vxf-num">{{ $row['per_hour'] ?? '—' }}</td>
                                <td class="vxf-num">{{ $row['issues'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Show completion records --}}
    <section class="vxf-card" aria-labelledby="vxfr-shows">
        <div class="vxf-card__head">
            <div><h2 id="vxfr-shows">Show completion records</h2>
                <span class="vxf-sub">One record per finished show: station, who packed what, times, and the final sign-off.</span></div>
            <button type="button" class="vxf-btn vxf-btn--sm" wire:click="exportShows"><x-heroicon-o-arrow-down-tray /> Export CSV</button>
        </div>
        @if ($this->runs->isEmpty())
            <div class="vxfr-empty"><x-heroicon-o-clipboard-document-check /><p>No shows finished in this period.</p></div>
        @else
            <div class="vxf-table-wrap">
                <table class="vxf-table">
                    <thead><tr><th scope="col">Show</th><th scope="col">Station</th><th scope="col">Packed by</th><th scope="col">Started → finished</th><th scope="col">Time</th><th scope="col">Packages</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Record</span></th></tr></thead>
                    <tbody>
                        @foreach ($this->showSlice() as $run)
                            <tr wire:key="rs-{{ $run->id }}" @class(['vxf-row--attention' => $run->status === 'completed'])>
                                <td style="min-width:200px"><strong>{{ $run->show?->title }}</strong><span class="vxf-sub">{{ $run->show?->channel?->name ?? 'No channel' }} · {{ $run->show?->show_date?->format('M j') }}</span></td>
                                <td>{{ $run->station?->name ?? '—' }}</td>
                                <td>@forelse ($this->splits[$run->id] ?? [] as $s)<span class="vxfr-split">{{ explode(' ', $s['name'])[0] }} <b class="vxf-num">{{ $s['n'] }}</b></span>@empty{{ $run->packers->pluck('name')->join(', ') ?: '—' }}@endforelse</td>
                                <td class="vxf-num" style="white-space:nowrap">{{ $run->started_at?->timezone($tz)->format('M j g:i A') }}<span class="vxf-sub">{{ $run->finished_at?->timezone($tz)->format('M j g:i A') }}</span></td>
                                <td class="vxf-num">{{ \App\Filament\Pages\FulfillmentReports::hours($run->activeSeconds()) }}</td>
                                <td class="vxf-num">{{ $run->completed_packages }}{{ $run->expected_packages !== null ? ' / ' . $run->expected_packages : '' }}@if ($run->flags_count)<span class="vxf-sub">{{ $run->flags_count }} {{ \Illuminate\Support\Str::plural('issue', $run->flags_count) }}</span>@endif</td>
                                <td>@if ($run->status === 'approved')<span class="vxf-pill vxf-pill--approved">Signed off</span><span class="vxf-sub">{{ $run->approver?->name }}</span>@else<span class="vxf-pill vxf-pill--completed">Needs sign-off</span>@endif</td>
                                <td style="text-align:right;white-space:nowrap">
                                    <a class="vxf-btn vxf-btn--sm vxf-btn--icon" href="{{ route('fulfillment.run.print', $run) }}" target="_blank" rel="noopener" title="Print record" aria-label="Print the record for {{ $run->show?->title }}"><x-heroicon-o-printer /></a>
                                    <a class="vxf-btn vxf-btn--sm" href="{{ \App\Filament\Pages\FulfillmentActivity::getUrl(['tab' => 'shows', 'run' => $run->id]) }}">{{ $run->status === 'completed' && $manager ? 'Review' : 'Log' }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @php $showPages = (int) ceil($this->runs->count() / \App\Filament\Pages\FulfillmentReports::SHOWS_PER_PAGE); @endphp
            @if ($showPages > 1)
                <nav class="vxf-pager" aria-label="Show record pages">
                    <button type="button" class="vxf-btn vxf-btn--sm vxf-btn--icon" wire:click="setShowPage({{ $showPage - 1 }})" @disabled($showPage <= 1) aria-label="Previous page"><x-heroicon-o-chevron-left /></button>
                    @for ($i = 1; $i <= $showPages; $i++)<button type="button" class="vxf-pager__dot {{ $i === $showPage ? 'is-on' : '' }}" wire:click="setShowPage({{ $i }})" aria-label="Page {{ $i }}"></button>@endfor
                    <button type="button" class="vxf-btn vxf-btn--sm vxf-btn--icon" wire:click="setShowPage({{ $showPage + 1 }})" @disabled($showPage >= $showPages) aria-label="Next page"><x-heroicon-o-chevron-right /></button>
                    <span class="vxf-sub vxf-num">{{ $this->runs->count() }} shows</span>
                </nav>
            @endif
        @endif
    </section>
</div>
</x-filament-panels::page>
