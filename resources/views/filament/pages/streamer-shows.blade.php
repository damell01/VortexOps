@php
    $groups = $this->groups();
    $summary = $this->quickSummary();
    $allShows = collect($groups['needs_you'])->map(fn ($s) => $s + ['bucket' => 'needs_you'])
        ->concat(collect($groups['upcoming'])->map(fn ($s) => $s + ['bucket' => 'upcoming']))
        ->concat(collect($groups['waiting'])->map(fn ($s) => $s + ['bucket' => 'waiting']))
        ->concat(collect($groups['done'])->map(fn ($s) => $s + ['bucket' => 'done']))
        ->values();

    $tones = ['danger' => 'bad', 'warning' => 'warn', 'info' => 'info', 'success' => 'ok', 'gray' => ''];
    $nextUp = $groups['needs_you'][0] ?? null;
    $defaultTab = $summary['needs_you'] > 0 ? 'needs_you' : ($summary['upcoming'] > 0 ? 'upcoming' : 'all');
    $calendarUrl = \App\Filament\Pages\Shows::canAccess() ? \App\Filament\Pages\Shows::getUrl() : null;
    $tabs = [
        ['needs_you', 'Waiting on you', $summary['needs_you']],
        ['upcoming', 'Upcoming', $summary['upcoming']],
        ['waiting', 'Submitted', $summary['submitted']],
        ['done', 'Approved', $summary['approved']],
        ['all', 'All', $allShows->count()],
    ];
@endphp

<x-filament-panels::page>
    @php
        $tiles = [
            ['needs_you', $summary['needs_you'], 'Shows waiting', $summary['needs_you'] ? 'Need items logged' : "You're all caught up", 'heroicon-o-exclamation-circle', '#ea580c', 'rgb(234 88 12 / .12)'],
            ['upcoming', $summary['upcoming'], 'Upcoming', 'Scheduled shows', 'heroicon-o-calendar-days', '#2563eb', 'rgb(37 99 235 / .12)'],
            ['waiting', $summary['submitted'], 'Submitted', 'Pending review', 'heroicon-o-document-text', '#7c3aed', 'rgb(124 58 237 / .12)'],
            ['done', $summary['approved'], 'Approved', 'Ready for pay', 'heroicon-o-check-badge', '#059669', 'rgb(5 150 105 / .12)'],
        ];
        $o = $this->overview();
        $chg = function (float $now, float $before) {
            if ($before <= 0) return null;
            return (int) min(999, max(-999, round(($now - $before) / $before * 100)));
        };
        $axis = fn (float $v) => $v >= 1000 ? '$' . rtrim(rtrim(number_format($v / 1000, 1), '0'), '.') . 'k' : '$' . number_format($v);
        $maxGross = max(1, collect($o['series'])->max('gross'));
        $niceMax = (function ($m) { $p = 10 ** floor(log10($m)); foreach ([1, 2, 2.5, 5, 10] as $f) { if ($f * $p >= $m) return $f * $p; } return 10 * $p; })($maxGross);
        $upcomingShows = $this->nextShows(4);
        $hours = fn ($h) => rtrim(rtrim(number_format($h, 2), '0'), '.') ?: '0';
    @endphp
    <style>
        .vx-sd-hero { position:relative; overflow:hidden; border-radius:18px; padding:26px 28px; color:#fff;
            background: radial-gradient(120% 140% at 85% 0%, rgb(167 139 250 / .55) 0%, transparent 45%), radial-gradient(80% 120% at 60% 120%, rgb(56 189 248 / .25) 0%, transparent 50%), linear-gradient(120deg, #0b1020 0%, #1b1446 55%, #2a1a6b 100%); }
        .vx-sd-hero::after { content:''; position:absolute; inset:0; background-image: radial-gradient(rgb(255 255 255 / .08) 1px, transparent 1px); background-size: 18px 18px; mask-image: linear-gradient(90deg, transparent 30%, #000 80%); pointer-events:none }
        .vx-sd-hero h2 { font-size:28px; font-weight:800; letter-spacing:-.02em; line-height:1.15 }
        .vx-sd-chip { display:inline-flex; align-items:center; gap:6px; margin-top:14px; border-radius:9999px; padding:5px 11px; font-size:12px; font-weight:600; background:rgb(255 255 255 / .1); border:1px solid rgb(255 255 255 / .15) }
        .vx-sd-cta { position:relative; z-index:1; display:inline-flex; align-items:center; gap:12px; border-radius:14px; padding:12px 16px; font-weight:700; color:#fff; border:1px solid rgb(255 255 255 / .35); background:rgb(255 255 255 / .08); backdrop-filter: blur(6px); transition: background .15s }
        .vx-sd-cta:hover { background:rgb(255 255 255 / .16) }
        .vx-sd-cta-plus { display:flex; height:30px; width:30px; align-items:center; justify-content:center; border-radius:9999px; background:#fff; color:#4c1d95 }
        .vx-sd-row { display:grid; gap:16px; grid-template-columns: minmax(0,1fr) }
        @media (min-width: 1100px) { .vx-sd-row { grid-template-columns: minmax(0,1.25fr) minmax(0,1.1fr) minmax(0,.8fr) } }
        .vx-sd-kpis { display:grid; gap:10px; grid-template-columns:repeat(3,minmax(0,1fr)) }
        .vx-sd-kpi { border-radius:12px; padding:12px 14px; background:var(--vxw-surface-2); border:1px solid var(--vxw-border) }
        .vx-sd-kpi-v { font-size:22px; font-weight:800; letter-spacing:-.01em; font-variant-numeric:tabular-nums }
        .vx-sd-up { font-size:11.5px; font-weight:700 }
        .vx-sd-chart { position:relative; display:grid; grid-template-columns:40px minmax(0,1fr); gap:6px; margin-top:16px }
        .vx-sd-yaxis { display:flex; flex-direction:column; justify-content:space-between; height:150px; font-size:10.5px; color:var(--vxw-muted); text-align:right; font-variant-numeric:tabular-nums }
        .vx-sd-plot { position:relative; height:150px; display:flex; align-items:flex-end; gap:2px; border-bottom:1px solid var(--vxw-border-strong) }
        .vx-sd-plot::before { content:''; position:absolute; inset:0; background: repeating-linear-gradient(to top, transparent 0, transparent calc(25% - 1px), var(--vxw-border) calc(25% - 1px), var(--vxw-border) 25%); pointer-events:none }
        .vx-sd-bar { position:relative; flex:1; min-width:0; height:100%; display:flex; align-items:flex-end; cursor:default }
        .vx-sd-bar > span { display:block; width:100%; max-width:14px; margin:0 auto; border-radius:4px 4px 0 0; background:#8b5cf6; transition: background .12s }
        .vx-sd-bar:hover > span { background:#6d28d9 }
        .vx-sd-tip { position:absolute; bottom:calc(100% + 6px); left:50%; transform:translateX(-50%); white-space:nowrap; border-radius:8px; padding:6px 9px; font-size:11.5px; background:var(--vxw-text); color:var(--vxw-surface); pointer-events:none; z-index:2 }
        .vx-sd-xaxis { display:flex; gap:2px; margin-left:46px; margin-top:6px; font-size:10.5px; color:var(--vxw-muted) }
        .vx-sd-xaxis span { flex:1; min-width:0; text-align:center; white-space:nowrap; overflow:visible }
        .vx-sd-up-row { display:flex; align-items:center; gap:12px; padding:11px 0; border-top:1px solid var(--vxw-border) }
        .vx-sd-up-row:first-child { border-top:0 }
        .vx-sd-date { display:flex; flex:none; flex-direction:column; align-items:center; justify-content:center; height:48px; width:48px; border-radius:12px; background:rgb(124 58 237 / .1); color:#6d28d9; line-height:1 }
        .vx-sd-date b { font-size:18px }
        .vx-sd-date small { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.04em }
        .vx-sd-qa { display:flex; align-items:center; gap:12px; width:100%; border-radius:12px; padding:12px; text-align:left; background:var(--vxw-surface-2); border:1px solid var(--vxw-border); transition:border-color .15s, background .15s }
        .vx-sd-qa:hover { border-color:rgb(124 58 237 / .5); background:rgb(124 58 237 / .05) }
        .vx-sd-qa-icon { display:flex; flex:none; height:38px; width:38px; align-items:center; justify-content:center; border-radius:10px }
        .vx-list.vxw-tiles { grid-template-columns: minmax(0,1fr) !important }
        .vx-list .vxw-tile { display:grid; grid-template-columns: auto minmax(0,1fr) auto; align-items:center; column-gap:16px; row-gap:6px }
        .vx-list .vxw-tile > .vxw-tile-foot { grid-column:3; grid-row:1 / span 2; margin:0 }
    </style>

    <div class="vxw" style="grid-template-columns:minmax(0,1fr)" data-vx-page="streamer-shows" x-data="{ tab: @js($defaultTab), sort: 'newest', view: 'grid' }">
        {{-- Welcome --}}
        <section class="vx-sd-hero">
            <div class="relative flex flex-wrap items-center justify-between" style="gap:18px;z-index:1">
                <div>
                    <h2>Welcome back, {{ $this->firstName() }} 👋</h2>
                    <p style="margin-top:6px;color:rgb(255 255 255 / .78)">Here's an overview of your shows and performance.</p>
                    <span class="vx-sd-chip"><x-filament::icon icon="heroicon-m-calendar" style="height:14px;width:14px" /> {{ \App\Support\BusinessTime::local()->format('l, F j, Y') }}</span>
                </div>
                @if ($nextUp && $nextUp['url'])
                    <a href="{{ $nextUp['url'] }}" class="vx-sd-cta">
                        <span class="vx-sd-cta-plus"><x-filament::icon icon="heroicon-m-plus" style="height:18px;width:18px" /></span>
                        <span>Log your next show<span style="display:block;font-size:12px;font-weight:500;opacity:.8">{{ \Illuminate\Support\Str::limit($nextUp['title'], 34) }}</span></span>
                        <x-filament::icon icon="heroicon-m-chevron-right" style="height:18px;width:18px" />
                    </a>
                @elseif ($calendarUrl)
                    <a href="{{ $calendarUrl }}" class="vx-sd-cta"><span class="vx-sd-cta-plus"><x-filament::icon icon="heroicon-m-calendar-days" style="height:18px;width:18px" /></span> See your calendar <x-filament::icon icon="heroicon-m-chevron-right" style="height:18px;width:18px" /></a>
                @endif
            </div>
        </section>

        {{-- The two things a streamer comes here to do: clock in, and log a show (above). --}}
        @if (\App\Support\AdminModules::isEnabled('timekeeping'))
            @livewire('clock-widget', ['compact' => true])
        @endif

        {{-- At a glance; each tile opens its list below. --}}
        <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(min(150px,44%),1fr))">
            @foreach ($tiles as [$key, $count, $label, $sub, $icon, $color, $soft])
                <button type="button" class="vxw-card flex items-center text-left" style="gap:14px;padding:16px;transition:box-shadow .15s"
                    @click="tab='{{ $key }}'; document.getElementById('show-grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                    :style="{ boxShadow: tab==='{{ $key }}' ? '0 0 0 2px {{ $color }}' : '' }">
                    <span style="display:flex;flex:none;height:44px;width:44px;align-items:center;justify-content:center;border-radius:12px;background:{{ $soft }};color:{{ $color }}"><x-filament::icon :icon="$icon" style="height:24px;width:24px" /></span>
                    <span class="min-w-0">
                        <span class="block tabular-nums" style="font-size:24px;font-weight:800;line-height:1.1">{{ number_format($count) }}</span>
                        <span class="block font-semibold" style="font-size:13.5px">{{ $label }}</span>
                        <span class="vxw-meta block">{{ $sub }}</span>
                    </span>
                </button>
            @endforeach
        </div>

        <div class="vx-sd-row">
            {{-- This month --}}
            <section class="vxw-card" style="min-width:0">
                <div class="vxw-card-head">
                    <h3 class="vxw-h2">{{ $o['label'] }}</h3>
                    <label><span class="sr-only">Period</span>
                        <select wire:model.live="period" class="vxw-select" style="width:auto;min-height:34px;padding-block:0">
                            <option value="month">This month</option><option value="last_month">Last month</option><option value="year">This year</option><option value="last_year">Last year</option>
                        </select>
                    </label>
                </div>
                <div class="vxw-card-body">
                    <div class="vx-sd-kpis">
                        @foreach ([['Shows', number_format($o['now']['shows']), $chg($o['now']['shows'], $o['before']['shows'])], ['Hours worked', $hours($o['now']['hours']), $chg($o['now']['hours'], $o['before']['hours'])], ['Gross sales', '$'.number_format($o['now']['gross']), $chg($o['now']['gross'], $o['before']['gross'])]] as [$label, $value, $d])
                            <div class="vx-sd-kpi">
                                <div class="vxw-meta" style="font-size:12px">{{ $label }}</div>
                                <div class="flex flex-wrap items-baseline" style="gap:6px">
                                    <span class="vx-sd-kpi-v">{{ $value }}</span>
                                    @if ($d !== null)<span class="vx-sd-up" style="color:{{ $d >= 0 ? '#059669' : '#dc2626' }}" title="vs {{ $o['compare'] }}">{{ $d >= 0 ? '↑' : '↓' }} {{ abs($d) }}%</span>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="vx-sd-chart" role="img" aria-label="Gross sales by {{ count($o['series']) > 31 ? 'month' : 'day' }}">
                        <div class="vx-sd-yaxis"><span>{{ $axis($niceMax) }}</span><span>{{ $axis($niceMax * .75) }}</span><span>{{ $axis($niceMax / 2) }}</span><span>{{ $axis($niceMax / 4) }}</span><span>$0</span></div>
                        <div class="vx-sd-plot">
                            @foreach ($o['series'] as $b)
                                <div class="vx-sd-bar" x-data="{ tip: false }" @mouseenter="tip = true" @mouseleave="tip = false">
                                    <span style="height:{{ $b['gross'] > 0 ? max(2, round($b['gross'] / $niceMax * 100, 1)) : 0 }}%"></span>
                                    <div class="vx-sd-tip" x-show="tip" x-cloak>{{ $b['label'] }} · ${{ number_format($b['gross']) }} · {{ $b['shows'] }} {{ \Illuminate\Support\Str::plural('show', $b['shows']) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @php $step = max(1, (int) ceil(count($o['series']) / 7)); @endphp
                    <div class="vx-sd-xaxis">@foreach ($o['series'] as $i => $b)<span>{{ $i % $step === 0 ? $b['label'] : '' }}</span>@endforeach</div>
                    <div class="mt-4 flex flex-wrap vxw-meta" style="gap:6px 18px;font-size:12.5px">
                        <span>Units sold <b style="color:var(--vxw-text)">{{ number_format($o['now']['units']) }}</b></span>
                        <span>Paid <b style="color:var(--vxw-text)">${{ number_format($o['now']['paid'], 2) }}</b></span>
                        <span>Awaiting payment <b style="color:var(--vxw-text)">${{ number_format($o['now']['awaiting'], 2) }}</b></span>
                    </div>
                </div>
            </section>

            {{-- Upcoming shows --}}
            <section class="vxw-card" style="min-width:0">
                <div class="vxw-card-head">
                    <h3 class="vxw-h2">Upcoming shows</h3>
                    @if ($calendarUrl)<a href="{{ $calendarUrl }}" class="vxw-btn vxw-btn--sm">View all <x-filament::icon icon="heroicon-m-arrow-right" /></a>@endif
                </div>
                <div class="vxw-card-body" style="padding-top:4px">
                    @forelse ($upcomingShows as $u)
                        @php $d = \Illuminate\Support\Carbon::createFromTimestampUTC($u['day'] * 86400); @endphp
                        <div class="vx-sd-up-row">
                            <span class="vx-sd-date"><small>{{ $d->format('M') }}</small><b>{{ $d->format('j') }}</b></span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-semibold">{{ $u['title'] }}</div>
                                <div class="vxw-meta">{{ $u['date'] }}@if($u['time']) · {{ $u['time']->format('g:i A') }}@endif</div>
                            </div>
                            @if ($u['channel'])<span class="vxw-pill" style="white-space:nowrap">{{ $u['channel'] }}</span>@endif
                        </div>
                    @empty
                        <div class="vxw-empty" style="padding:24px 0"><x-filament::icon icon="heroicon-o-calendar" /><div class="vxw-empty-title">Nothing scheduled</div><div class="vxw-empty-text">New shows appear here once you're assigned.</div></div>
                    @endforelse
                </div>
            </section>

            {{-- Quick actions --}}
            <section class="vxw-card" style="min-width:0">
                <div class="vxw-card-head"><h3 class="vxw-h2">Quick actions</h3></div>
                <div class="vxw-card-body" style="display:grid;gap:10px;padding-top:4px">
                    @php
                        $actions = array_values(array_filter([
                            \App\Filament\Pages\EndOfStreamForm::canAccess() ? ['Log a show', 'Complete your show report', $nextUp['url'] ?? \App\Filament\Pages\EndOfStreamForm::getUrl(), 'heroicon-o-clipboard-document-check', '#7c3aed', 'rgb(124 58 237 / .12)', null] : null,
                            \App\Filament\Pages\InventoryGuide::canAccess() ? ['Find inventory', 'Check stock for your next show', \App\Filament\Pages\InventoryGuide::getUrl(), 'heroicon-o-magnifying-glass', '#2563eb', 'rgb(37 99 235 / .12)', null] : null,
                            \App\Support\AdminModules::isEnabled('timekeeping') ? ['Clock in', 'Start tracking your hours', null, 'heroicon-o-play-circle', '#059669', 'rgb(5 150 105 / .12)', 'clock'] : null,
                            $calendarUrl ? ['View calendar', 'See all your scheduled shows', $calendarUrl, 'heroicon-o-calendar-days', '#ea580c', 'rgb(234 88 12 / .12)', null] : null,
                        ]));
                    @endphp
                    @foreach ($actions as [$title, $sub, $url, $icon, $color, $soft, $jump])
                        @if ($jump)
                            <button type="button" class="vx-sd-qa" @click="document.querySelector('[data-vx=clock-widget]')?.scrollIntoView({ behavior: 'smooth', block: 'center' })">
                        @else
                            <a href="{{ $url }}" class="vx-sd-qa">
                        @endif
                            <span class="vx-sd-qa-icon" style="background:{{ $soft }};color:{{ $color }}"><x-filament::icon :icon="$icon" style="height:22px;width:22px" /></span>
                            <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $title }}</span><span class="vxw-meta block">{{ $sub }}</span></span>
                            <x-filament::icon icon="heroicon-m-chevron-right" style="height:16px;width:16px;color:var(--vxw-muted)" />
                        @if ($jump)</button>@else</a>@endif
                    @endforeach
                </div>
            </section>
        </div>


        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="vxw-chips" role="tablist" aria-label="Filter your shows">
                @foreach($tabs as [$key, $label, $count])
                    <button type="button" role="tab" class="vxw-chip" :class="tab==='{{ $key }}' && 'is-active'" :aria-selected="tab==='{{ $key }}'" @click="tab='{{ $key }}'">
                        {{ $label }} <span class="vxw-chip-count">{{ $count }}</span>
                    </button>
                @endforeach
            </div>
            <div class="flex items-center gap-2">
            <div class="vxw-chips" role="group" aria-label="Layout">
                <button type="button" class="vxw-chip" :class="view==='grid' && 'is-active'" @click="view='grid'"><x-filament::icon icon="heroicon-m-squares-2x2" style="height:15px;width:15px" /> Grid</button>
                <button type="button" class="vxw-chip" :class="view==='list' && 'is-active'" @click="view='list'"><x-filament::icon icon="heroicon-m-list-bullet" style="height:15px;width:15px" /> List</button>
            </div>
            <label class="flex items-center gap-2">
                <span class="sr-only">Sort shows</span>
                <select x-model="sort" class="vxw-select" style="min-height:36px;width:auto;padding-block:0">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                </select>
            </label>
            </div>
        </div>

        <section id="show-grid" class="vxw-tiles" :class="view==='list' && 'vx-list'" aria-live="polite" style="scroll-margin-top:90px">
            @foreach ($allShows as $show)
                <article wire:key="streamer-show-{{ $show['id'] }}"
                    x-show="tab==='all' || tab==='{{ $show['bucket'] }}'" x-cloak
                    :style="{ order: sort==='newest' ? {{ -$show['day'] }} : {{ $show['day'] }} }"
                    @class(['vxw-tile', 'vxw-tile--attn' => $show['bucket'] === 'needs_you'])
                    style="cursor:default">
                    <div class="flex items-start justify-between gap-3">
                        <span class="vxw-pill vxw-pill--dot {{ ($tones[$show['tone']] ?? '') ? 'vxw-pill--' . $tones[$show['tone']] : '' }}">{{ $show['state'] }}</span>
                        <span class="vxw-meta text-right" style="min-width:0">
                            {{ $show['date'] }}@if($show['time']) · {{ $show['time']->format('g:i A') }}@endif
                        </span>
                    </div>
                    <div>
                        <h3 class="vxw-tile-title">{{ $show['title'] }}</h3>
                        @if($show['channel'])<div class="vxw-meta mt-1">{{ $show['channel'] }}</div>@endif
                    </div>

                    @if($show['revision_requested'] && $show['revision_reason'])
                        <div class="vxw-alert vxw-alert--info" style="padding:8px 10px;font-size:12.5px">
                            <x-filament::icon icon="heroicon-m-chat-bubble-left-ellipsis" />
                            <span>You asked: “{{ $show['revision_reason'] }}”</span>
                        </div>
                    @endif

                    <div class="vxw-tile-foot">
                        @if($show['url'])
                            <a href="{{ $show['url'] }}" class="vxw-btn vxw-btn--block {{ $show['bucket'] === 'needs_you' ? 'vxw-btn--primary' : '' }}">
                                {{ $show['action'] }} <x-filament::icon icon="heroicon-m-arrow-right" />
                            </a>
                        @else
                            <span class="vxw-meta">Report opens after the show airs.</span>
                        @endif
                    </div>

                    @if($show['can_request_revision'] && $revisionFor !== $show['id'])
                        <button type="button" wire:click="askForChanges({{ $show['id'] }})" class="vxw-btn vxw-btn--ghost vxw-btn--sm -mt-1 self-center">Need to change something?</button>
                    @endif
                    @if($revisionFor === $show['id'])
                        <div class="vxw-field rounded-[var(--vxw-radius-sm)] border border-[var(--vxw-border)] bg-[var(--vxw-surface-2)] p-3">
                            <label class="vxw-label" for="revision-{{ $show['id'] }}">What needs changing?</label>
                            <textarea id="revision-{{ $show['id'] }}" wire:model="revisionReason" rows="2" class="vxw-textarea" style="min-height:72px" placeholder="e.g. I logged the wrong item"></textarea>
                            <div class="flex gap-2">
                                <button type="button" wire:click="submitRevisionRequest" wire:loading.attr="disabled" wire:target="submitRevisionRequest" class="vxw-btn vxw-btn--primary vxw-btn--sm">Request reopen</button>
                                <button type="button" wire:click="cancelRevisionRequest" class="vxw-btn vxw-btn--ghost vxw-btn--sm">Cancel</button>
                            </div>
                        </div>
                    @endif
                </article>
            @endforeach

            @foreach($tabs as [$key, $label, $count])
                @if($count === 0 && $key !== 'all')
                    <div class="vxw-card col-span-full" x-show="tab==='{{ $key }}'" x-cloak>
                        <div class="vxw-empty">
                            <x-filament::icon icon="{{ $key === 'needs_you' ? 'heroicon-o-check-circle' : 'heroicon-o-calendar' }}" />
                            <div class="vxw-empty-title">{{ $key === 'needs_you' ? 'Nothing waiting on you' : 'No ' . strtolower($label) . ' shows' }}</div>
                            @if($calendarUrl)<div class="vxw-empty-text">See every past and upcoming show on the <a class="vxw-link" href="{{ $calendarUrl }}">calendar</a>.</div>@endif
                        </div>
                    </div>
                @endif
            @endforeach
        </section>


        {{-- Every show they hosted, past and upcoming, not only the ones with a report to file. --}}
        @php $list = $this->allShows(); @endphp
        <section class="vxw-card" id="all-my-shows" style="min-width:0">
            <div class="vxw-card-head">
                <div>
                    <h3 class="vxw-h2">All my shows</h3>
                    <p class="vxw-sub">Every show you've been on — search by title, or narrow by channel and date.</p>
                </div>
            </div>
            <div class="vxw-card-body grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(min(180px,100%),1fr))">
                <label class="vxw-field"><span class="vxw-label">Search</span><input type="search" wire:model.live.debounce.400ms="search" class="vxw-input" placeholder="Show title"></label>
                <label class="vxw-field"><span class="vxw-label">Channel</span><select wire:model.live="channelFilter" class="vxw-select"><option value="">All channels</option>@foreach ($this->myChannels() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
                <label class="vxw-field"><span class="vxw-label">When</span><select wire:model.live="when" class="vxw-select"><option value="all">Any time</option><option value="upcoming">Upcoming</option><option value="past">Past</option><option value="month">This month</option><option value="year">This year</option></select></label>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm" style="min-width:680px">
                    <thead><tr class="text-left text-[11px] font-bold uppercase tracking-wide" style="color:var(--vxw-muted)">
                        <th class="px-4 py-2">Date</th><th class="px-4 py-2">Show</th><th class="px-4 py-2 text-right">Gross sales</th><th class="px-4 py-2 text-right">Units</th><th class="px-4 py-2 text-right">Hours worked</th><th class="px-4 py-2">Report</th><th class="px-4 py-2 text-right">Your pay</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($list as $show)
                            @php
                                $entry = $show->streamerLogEntry;
                                $upcoming = $show->show_date && $show->show_date->isFuture();
                                $pay = $show->payouts;
                                $reportState = $show->status === 'cancelled' ? 'Cancelled' : ($upcoming ? 'Scheduled' : (! $entry || ! $entry->submitted_at ? 'Not filed' : (\App\Models\StreamerLogEntry::statusLabels()[$entry->status] ?? ucfirst($entry->status))));
                            @endphp
                            <tr wire:key="mine-{{ $show->id }}" style="border-top:1px solid var(--vxw-border)">
                                <td class="px-4 py-2.5 whitespace-nowrap">{{ $show->show_date?->format('D M j, Y') }}</td>
                                <td class="px-4 py-2.5"><div class="font-medium">{{ $show->title ?: 'Show #'.$show->id }}</div>@if($show->channel)<div class="vxw-meta">{{ $show->channel->name }}</div>@endif</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $upcoming ? '—' : '$'.number_format((float) $show->gross_revenue, 2) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $upcoming ? '—' : number_format((int) $show->units_sold) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $entry?->hours_streamed !== null ? rtrim(rtrim(number_format((float) $entry->hours_streamed, 2), '0'), '.') : '—' }}</td>
                                <td class="px-4 py-2.5">{{ $reportState }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    @if ($pay->isEmpty())
                                        <span class="vxw-meta">—</span>
                                    @else
                                        ${{ number_format((float) $pay->sum('calculated_payout'), 2) }}
                                        <div class="vxw-meta">{{ $pay->every(fn ($p) => $p->status === 'paid') ? 'Paid' : 'Awaiting payment' }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center vxw-meta">No shows match.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($list->lastPage() > 1)
                <div class="flex items-center justify-between px-4 py-3" style="border-top:1px solid var(--vxw-border)">
                    <span class="vxw-meta">Page {{ $list->currentPage() }} of {{ $list->lastPage() }} · {{ number_format($list->total()) }} shows</span>
                    <div class="flex gap-2">
                        <button type="button" class="vxw-btn vxw-btn--sm" wire:click="gotoListPage({{ $list->currentPage() - 1 }})" @disabled($list->onFirstPage())>Previous</button>
                        <button type="button" class="vxw-btn vxw-btn--sm" wire:click="gotoListPage({{ $list->currentPage() + 1 }})" @disabled(! $list->hasMorePages())>Next</button>
                    </div>
                </div>
            @endif
        </section>

        @if ($allShows->isEmpty())
            <section class="vxw-card">
                <div class="vxw-empty">
                    <x-filament::icon icon="heroicon-o-video-camera" />
                    <div class="vxw-empty-title">No shows yet</div>
                    <div class="vxw-empty-text">Once you're assigned to a show it will appear here.</div>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>

