@use('App\Services\ShowPerformanceReport', 'SPR')
<x-filament-panels::page>
    @php
        $t = $this->totals();
        $map = $this->heatmap();
        $best = $this->bestTimes($map);
        $board = $this->leaderboard();
        $champion = $board->first();
        $opts = $this->options();
        $money = fn ($v) => $v === null ? '—' : (($v < 0 ? '-$' : '$').number_format(abs($v), abs($v) >= 1000 ? 0 : 2));
        $hrs = fn ($h) => $h === null ? '—' : (floor($h) > 0 ? floor($h).'h' : '').(round(($h - floor($h)) * 60) > 0 ? ' '.round(($h - floor($h)) * 60).'m' : '');
        $fmt = fn ($v) => $v === null ? '—' : ($this->metric === 'shows' ? (int) $v : $money($v).' / hr');
        $boardFmt = fn ($v) => $this->board === 'net_hour' ? $money($v).' / hr' : $money($v);
        // Shade by where a cell sits between the weakest and strongest slot.
        $values = collect($map['cells'])->flatten(1)->map(fn ($c) => (new SPR)->cellValue($c, $this->metric))->filter(fn ($v) => $v !== null);
        $top = max(0.01, (float) $values->max());
        $low = min(0.0, (float) $values->min());
        $topBest = $best['rows'][0]['value'] ?? null;
        [$openDay, $openBlock] = $this->cell ? array_map('intval', explode('-', $this->cell)) : [null, null];
    @endphp

    <div class="vxr vxsp" data-vx-page="show-performance">
        <div class="vxr-head">
            <div>
                <h1 class="vxr-title">Show Performance</h1>
                <p class="vxr-subtitle">Your strongest time slots and highest-performing shows. Times are Central.</p>
            </div>
            <div class="vxr-seg vxsp-basis" role="tablist" aria-label="Net basis">
                @foreach (SPR::BASES as $k => $label)
                    <button type="button" wire:click="setBasis('{{ $k }}')" @class(['is-active' => $this->basis === $k]) title="{{ $k === 'business' ? 'After product cost and pay — finalized shows only' : 'What Whatnot paid out after its fees' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="vxr-toolbar">
            <label class="vxr-control">
                <x-filament::icon icon="heroicon-o-calendar" />
                <select wire:model.live="range" aria-label="Date range">
                    @foreach (\App\Filament\Pages\ShowPerformance::RANGES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            <select class="vxr-select" wire:model.live="channel" aria-label="Channel">
                <option value="">All channels</option>
                @foreach ($opts['channels'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <select class="vxr-select" wire:model.live="business" aria-label="Business">
                <option value="">All businesses</option>
                @foreach ($opts['businesses'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <select class="vxr-select" wire:model.live="streamer" aria-label="Streamer">
                <option value="">All streamers</option>
                @foreach ($opts['streamers'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <span class="vxsp-period">{{ $this->periodLabel() }}</span>
        </div>

        <div class="vxr-stats">
            <x-vxr.stat label="Gross sales" :value="$money($t['gross'])" icon="heroicon-o-shopping-cart" tone="accent" :note="$t['shows'].' '.\Illuminate\Support\Str::plural('show', $t['shows'])" />
            <x-vxr.stat label="{{ $this->netLabel() }}" :value="$money($t['net'])" icon="heroicon-o-banknotes" tone="ok" :value-class="($t['net'] ?? 0) < 0 ? 'vxr-val-bad' : ''" :note="$t['pending'] ? $t['pending'].' pending, not counted' : ($this->basis === 'business' ? 'All shows finalized' : 'Every show has earnings')" />
            <x-vxr.stat label="{{ $this->netLabel() }} / hour" :value="$t['net_hour'] === null ? '—' : $money($t['net_hour'])" icon="heroicon-o-arrow-trending-up" tone="info" :note="$t['hours'].' hours streamed'" />
            <x-vxr.stat :label="$this->basis === 'business' ? 'Finalized shows' : 'Shows with earnings'" :value="$t['final']" icon="heroicon-o-check-badge" tone="warn" :note="'of '.$t['shows'].' in range'" />
        </div>

        <div class="vxsp-grid">
            <section class="vxr-card vxsp-heat">
                <div class="vxr-card-head">
                    <div>
                        <h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-fire" /> Best Times to Stream</h2>
                        <p class="vxr-card-sub">Each square is a day and a two-hour start window. Tap one to see its shows.</p>
                    </div>
                    <div class="vxr-seg" role="tablist">
                        @foreach (SPR::METRICS as $k => $label)
                            <button type="button" wire:click="setMetric('{{ $k }}')" @class(['is-active' => $this->metric === $k])>{{ $k === 'net_hour' ? 'Net / hour' : $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="vxr-card-body">
                    @if ($t['shows'] === 0)
                        <p class="vxsp-empty">No shows in this range yet.</p>
                    @else
                        <div class="vxsp-map" style="--cols: {{ count($map['blocks']) }}">
                            <span></span>
                            @foreach ($map['blocks'] as $b)<span class="vxsp-col">{{ SPR::blockShort($b) }}</span>@endforeach
                            @foreach (SPR::DAYS as $d => $day)
                                <span class="vxsp-row">{{ $day }}</span>
                                @foreach ($map['blocks'] as $b)
                                    @php
                                        $c = $map['cells'][$d][$b];
                                        $v = (new SPR)->cellValue($c, $this->metric);
                                        $level = $v === null ? null : ($v < 0 ? -min(1, $v / ($low ?: -1)) : $v / $top);
                                        $tip = SPR::dayName($d).' '.SPR::blockLabel($b).': '.($c['shows'] ? $c['shows'].' '.\Illuminate\Support\Str::plural('show', $c['shows']).' · '.$fmt($v) : 'no shows');
                                    @endphp
                                    @if ($c['shows'] > 0)
                                        <button type="button" wire:click="openCell({{ $d }}, {{ $b }})" title="{{ $tip }}" aria-label="{{ $tip }}"
                                            @class(['vxsp-cell', 'is-neg' => $level !== null && $level < 0, 'is-pending' => $v === null, 'is-light' => $level !== null && abs($level) < .45, 'is-open' => $openDay === $d && $openBlock === $b, 'is-best' => $topBest !== null && $v === $topBest && $c['shows'] >= ($best['thin'] ? 1 : SPR::MIN_SHOWS)])
                                            style="--lvl: {{ $level === null ? 0 : round(abs($level), 3) }}">
                                            <span class="vxsp-cell-v">{{ $v === null ? '' : ($this->metric === 'shows' ? (int) $v : '$'.number_format($v, 0)) }}</span>
                                        </button>
                                    @else
                                        <span class="vxsp-cell is-none" title="{{ $tip }}"></span>
                                    @endif
                                @endforeach
                            @endforeach
                        </div>
                        <div class="vxsp-legend">
                            <span>Less</span><i style="--lvl:.12"></i><i style="--lvl:.4"></i><i style="--lvl:.7"></i><i style="--lvl:1"></i><span>More</span>
                            @if ($this->metric !== 'shows')<span class="vxsp-legend-note"><i class="is-neg" style="--lvl:.7"></i> Lost money &nbsp; <i class="is-pending"></i> Pending</span>@endif
                            @if ($map['untimed'])<span class="vxsp-legend-note">{{ $map['untimed'] }} {{ \Illuminate\Support\Str::plural('show', $map['untimed']) }} without a start time not shown</span>@endif
                        </div>

                        @if ($this->cell !== null)
                            @php $shows = $this->cellShows(); @endphp
                            <div class="vxsp-drill" wire:key="drill-{{ $this->cell }}">
                                <div class="vxsp-drill-head">
                                    <strong>{{ SPR::dayName($openDay) }}s, {{ SPR::blockLabel($openBlock) }}</strong>
                                    <button type="button" class="vxr-link" wire:click="openCell({{ $openDay }}, {{ $openBlock }})">Close</button>
                                </div>
                                @foreach ($shows as $s)
                                    <a class="vxsp-drill-row" href="{{ $this->showUrl($s['id']) }}">
                                        <span class="vxsp-drill-title">{{ $s['title'] }}<small>{{ $s['date']?->format('M j') }} · {{ $s['start']?->format('g:i A') }} · {{ $s['streamer'] }}</small></span>
                                        <span class="vxsp-drill-fig">{{ $money($s['sales']) }}<small>gross</small></span>
                                        <span class="vxsp-drill-fig">@if($s['final']){{ $money($s['net']) }}@else<span class="vxr-pill vxr-pill--warn">Pending</span>@endif<small>net</small></span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
            </section>

            <section class="vxr-card vxsp-champ">
                <div class="vxr-card-head"><h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-trophy" /> Champion Show</h2></div>
                <div class="vxr-card-body">
                    @if ($champion)
                        <div class="vxsp-champ-body">
                            <span class="vxr-pill vxr-pill--warn">Highest {{ $this->board === 'gross' ? 'gross sales' : ($this->basis === 'business' ? 'business net' : 'Whatnot net').($this->board === 'net_hour' ? ' / hour' : '') }}</span>
                            <svg class="vxsp-badge" viewBox="0 0 120 120" aria-hidden="true">
                                <defs>
                                    <linearGradient id="vxsp-gold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fde68a"/><stop offset=".55" stop-color="#f59e0b"/><stop offset="1" stop-color="#b45309"/></linearGradient>
                                    <linearGradient id="vxsp-purple" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#a78bfa"/><stop offset="1" stop-color="#5b21b6"/></linearGradient>
                                </defs>
                                <path d="M14 40 L38 30 L22 62 Z M106 40 L82 30 L98 62 Z" fill="url(#vxsp-gold)"/>
                                <path d="M60 8 L98 30 L98 74 L60 112 L22 74 L22 30 Z" fill="url(#vxsp-gold)"/>
                                <path d="M60 18 L89 35 L89 70 L60 100 L31 70 L31 35 Z" fill="url(#vxsp-purple)"/>
                                <circle cx="60" cy="58" r="20" fill="#fff"/>
                                <path d="M40 58 a20 20 0 0 1 40 0 Z" fill="#7c3aed"/>
                                <rect x="40" y="56" width="40" height="4" fill="#312e81"/>
                                <circle cx="60" cy="58" r="7" fill="#fff" stroke="#312e81" stroke-width="3"/>
                            </svg>
                            <h3 class="vxsp-champ-title">{{ $champion['title'] }}</h3>
                            <p class="vxsp-champ-meta">{{ $champion['channel'] }} · {{ $champion['streamer'] }} · {{ $champion['date']?->format('M j') }}</p>
                            <p class="vxsp-champ-value">{{ $boardFmt($champion['value']) }}</p>
                            <p class="vxsp-champ-cap">{{ $this->board === 'gross' ? 'gross sales' : ($this->basis === 'business' ? 'business net' : 'Whatnot net').($this->board === 'net_hour' ? ' per hour' : '') }}</p>
                            <div class="vxsp-champ-facts">
                                <span><strong>{{ $money($champion['sales']) }}</strong>Gross sales</span>
                                <span><strong>{{ $champion['final'] ? $money($champion['net']) : 'Pending' }}</strong>{{ $this->netLabel() }}</span>
                                <span><strong>{{ $hrs($champion['stream_hours']) }}</strong>Duration</span>
                            </div>
                            <a class="vxr-btn vxr-btn--primary vxsp-champ-btn" href="{{ $this->showUrl($champion['id']) }}"><x-filament::icon icon="heroicon-o-eye" /> View show</a>
                        </div>
                    @else
                        <p class="vxsp-empty">No {{ $this->board === 'gross' ? '' : ($this->basis === 'business' ? 'finalized ' : '') }}shows to crown yet.</p>
                    @endif
                </div>
            </section>
        </div>

        @if ($best['rows'] !== [])
            <section class="vxr-card">
                <div class="vxr-card-head">
                    <div>
                        <h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-clock" /> Top Time Slots</h2>
                        <p class="vxr-card-sub">By {{ strtolower(SPR::METRICS[$this->metric]) }}, start time.
                            @if ($best['thin']) No slot has {{ SPR::MIN_SHOWS }} shows yet, so these are early reads. @else Minimum {{ SPR::MIN_SHOWS }} shows per slot. @endif</p>
                    </div>
                </div>
                <div class="vxr-card-body vxsp-slots">
                    @foreach ($best['rows'] as $i => $slot)
                        <button type="button" class="vxsp-slot" wire:click="openCell({{ $slot['day'] }}, {{ $slot['block'] }})">
                            <span @class(['vxsp-medal', 'vxsp-medal--'.($i + 1) => $i < 3])>{{ $i + 1 }}</span>
                            <span class="vxsp-slot-name">{{ SPR::dayName($slot['day']) }} {{ SPR::blockLabel($slot['block']) }}</span>
                            <span class="vxsp-bar"><i style="width: {{ $topBest > 0 ? max(4, round(max(0, $slot['value']) / $topBest * 100)) : 4 }}%"></i></span>
                            <span class="vxsp-slot-fig">{{ $fmt($slot['value']) }}<small>{{ $slot['shows'] }} {{ \Illuminate\Support\Str::plural('show', $slot['shows']) }}</small></span>
                        </button>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="vxr-card">
            <div class="vxr-card-head">
                <div>
                    <h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-chart-bar" /> Show Leaderboard</h2>
                    <p class="vxr-card-sub">{{ $this->board === 'gross' ? 'Whatnot sales, every show.' : ($this->basis === 'business' ? 'Business profit after product cost and pay, finalized shows only.' : 'What Whatnot paid out after its fees, shows with earnings in.') }}</p>
                </div>
                <div class="vxr-seg" role="tablist">
                    @foreach (SPR::BOARD as $k => $label)
                        <button type="button" wire:click="setBoard('{{ $k }}')" @class(['is-active' => $this->board === $k])>{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            @if ($board->isEmpty())
                <div class="vxr-card-body"><p class="vxsp-empty">Nothing to rank yet{{ $this->board === 'gross' ? '' : ($this->basis === 'business' ? ' — shows appear here once they are finalized' : ' — shows appear here once their Whatnot earnings are in') }}.</p></div>
            @else
                <ol class="vxsp-board">
                    <li class="vxsp-board-head" aria-hidden="true"><span>Rank</span><span>Show</span><span>Channel</span><span>Duration</span><span>Gross</span><span>Net</span><span>Net / hr</span><span></span></li>
                    @foreach ($board as $r)
                        <li class="vxsp-board-row">
                            <span><span @class(['vxsp-medal', 'vxsp-medal--'.$r['rank'] => $r['rank'] <= 3])>{{ $r['rank'] }}</span></span>
                            <span class="vxsp-board-title">{{ $r['title'] }}<small>{{ $r['date']?->format('M j') }} · {{ $r['streamer'] }}<span class="vxsp-sm-only"> · {{ $r['channel'] }}</span></small></span>
                            <span data-label="Channel">{{ $r['channel'] }}</span>
                            <span data-label="Duration">{{ $hrs($r['stream_hours']) }}</span>
                            <span data-label="Gross" @class(['is-sorted' => $this->board === 'gross'])>{{ $money($r['sales']) }}</span>
                            <span data-label="Net" @class(['is-sorted' => $this->board === 'net', 'vxr-val-bad' => ($r['net'] ?? 0) < 0])>{{ $r['final'] ? $money($r['net']) : 'Pending' }}</span>
                            <span data-label="Net / hr" @class(['is-sorted' => $this->board === 'net_hour'])>{{ $r['net_hour'] === null ? '—' : $money($r['net_hour']) }}</span>
                            <span><a class="vxr-btn vxsp-view" href="{{ $this->showUrl($r['id']) }}"><x-filament::icon icon="heroicon-o-eye" /> View</a></span>
                        </li>
                    @endforeach
                </ol>
            @endif
            <div class="vxr-card-foot">{{ $this->basis === 'business' ? 'Business net takes off recorded product cost and pay. Gross is Whatnot sales.' : 'Whatnot net is after Whatnot\'s fees, before product cost and pay — switch to Business net at the top for those. Gross is Whatnot sales.' }}</div>
        </section>
    </div>
</x-filament-panels::page>
