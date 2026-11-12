<x-filament-panels::page>
@php
    $manager = $this->isManager();
@endphp
@include('fulfillment.motion')

<style>
/* Compact sticky station targets stay in view while the manager scrolls the show queue. */
.vxf-queue-dock{position:relative;top:auto;z-index:2;display:flex;align-items:center;gap:12px;padding:9px 12px;margin-bottom:10px;border:1px solid #ddd6fe;border-radius:14px;background:rgba(250,248,255,.97);box-shadow:0 6px 18px rgba(30,18,65,.08);backdrop-filter:blur(10px)}
.vxf-queue-dock__label{display:flex;flex-direction:column;gap:2px;min-width:135px;font-size:12px}.vxf-queue-dock__label span{color:#64748b;font-size:10px}
.vxf-queue-dock__stations{display:flex;gap:7px;overflow-x:auto;flex:1;padding:2px;scrollbar-width:thin}
.vxf-queue-dock__target{display:flex;align-items:center;gap:7px;flex:0 0 auto;min-height:42px;padding:6px 9px;border:1px dashed #a78bfa;border-radius:10px;background:#fff;font-size:12px}
.vxf-queue-dock__target.is-over{background:#ede9fe;border:2px solid #7c3aed}.vxf-queue-dock__target.is-busy{border-style:solid;border-color:#e2e8f0;opacity:.65}
.vxf-queue-dock__name{font-weight:700;white-space:nowrap}.vxf-queue-dock__state{color:#64748b;font-size:10px}.vxf-queue-dock__assign{border-radius:7px;background:#6d28d9;color:white;padding:5px 8px;font-weight:700;font-size:11px}
.dark .vxf-queue-dock{background:rgba(30,20,50,.97);border-color:#6d28d9}.dark .vxf-queue-dock__target{background:#241c35;color:#f8fafc}
@media(max-width:800px){.vxf-queue-dock{top:auto;display:block;padding:8px}.vxf-queue-dock__label{margin-bottom:6px}.vxf-queue-dock__label span{display:none}.vxf-queue-dock__target{min-height:44px}}

.vxf-dnd-mobile-hint{display:none}
@media(max-width:800px){.vxf-dnd-desktop-hint{display:none}.vxf-dnd-mobile-hint{display:inline}.vxf-dnd-team__chips{display:none}.vxf-queue-dock__assign{min-height:36px;padding:7px 12px}.vxf-queue-dock__stations{scroll-snap-type:x proximity}.vxf-queue-dock__target{scroll-snap-align:start}}
.vxf-queue-search{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:10px 0}.vxf-queue-search input{flex:1;min-width:180px;min-height:42px;border:1px solid #cbd5e1;border-radius:9px;padding:8px 12px;background:var(--vxf-panel,#fff);color:inherit}.vxf-queue-search input:focus{outline:2px solid #7c3aed;outline-offset:1px}

/* Dashboard hierarchy: compact stats, uncluttered stations, full-width queue. */
.vxf .vxf-head{padding:2px 2px 0}
.vxf .vxf-head h1{font-size:20px}
.vxf .vxf-head-actions .vxf-btn{min-height:34px;padding:0 11px;font-size:12px}
.vxf .vxf-home-tabs{margin-top:8px}
.vxf .vxf-floor .vxf-card__body{padding:10px}
.vxf .vxf-floor .vxf-station{padding:11px;min-height:215px;gap:7px}
.vxf .vxf-floor .vxf-station__show{min-height:34px;font-size:12px}
.vxf .vxf-floor .vxf-station__meta{font-size:11px}
.vxf .vxf-floor .vxf-station__name{font-size:13px}
.vxf .vxf-floor .vxf-station__mid{gap:6px}
.vxf .vxf-floor .vxf-station__foot{border-top:1px solid var(--vxf-line);padding-top:8px}
.vxf .vxf-queue-dock{margin:0 0 8px}

.vxf .vxf-stats{gap:9px}
.vxf .vxf-stat{min-height:68px;padding:10px 12px;gap:10px}
.vxf .vxf-stat__icon{width:34px;height:34px;flex:0 0 34px}
.vxf .vxf-stat__value{font-size:1.45rem;line-height:1.15}
.vxf .vxf-stat__label{font-size:12px}
.vxf .vxf-floor .vxf-card__head{gap:12px;flex-wrap:wrap}
.vxf .vxf-status-filters{display:flex;flex-wrap:wrap;gap:5px}
.vxf .vxf-status-filters .vxf-tab{min-height:32px;padding:5px 9px}
.vxf .vxf-stations--track{scroll-snap-type:x proximity}
.vxf .vxf-station{scroll-snap-align:start}
.vxf .vxf-queue-dock{box-shadow:none}
@media(min-width:1440px){
  .vxf .vxf-stations--track{display:grid;grid-auto-flow:row;grid-auto-columns:auto;grid-template-columns:repeat(5,minmax(0,1fr));overflow:visible;gap:10px}
  .vxf .vxf-stations--track>.vxf-station{width:auto;min-width:0;max-width:none;box-sizing:border-box}
  .vxf .vxf-carousel__arrow{display:none!important}
}
@media(max-width:800px){
  .vxf .vxf-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
  .vxf .vxf-queue-dock__stations{max-width:100%}
  .vxf .vxf-status-filters{overflow-x:auto;flex-wrap:nowrap;max-width:100%;padding-bottom:3px}
  .vxf .vxf-status-filters .vxf-tab{flex:0 0 auto}
}
</style>
<div class="vxf" x-data="{ guide: (() => { try { return localStorage.getItem('vxf-guide-home') === 'shown' } catch (e) { return true } })(),
        toggleGuide() { this.guide = ! this.guide; try { localStorage.setItem('vxf-guide-home', this.guide ? 'shown' : 'hidden') } catch (e) {} } }">
    <div class="vxf-head">
        <div>
            <h1>Fulfillment Dashboard</h1>
            <p><span class="vxf-live"><i aria-hidden="true"></i> Live</span> {{ $manager ? 'Fulfillment Operations · stations, shows ready to pack and today\'s activity' : 'Your shows, your station and your shift' }} · {{ \App\Support\BusinessTime::local()->format('D, M j · g:i A') }}</p>
        </div>
        <div class="vxf-actions vxf-head-actions">
            @if ($manager && $this->signoffQueue->isNotEmpty())
                @php $nextSign = $this->signoffQueue->first(); @endphp
                <button type="button" class="vxf-btn vxf-btn--sm" wire:click="openSignoff({{ $nextSign->id }})" title="Review pending completed shows"><x-heroicon-o-check-badge /> Sign-offs <span class="vxf-signq__count">{{ $this->signoffQueue->count() }}</span></button>
            @endif
            @if ($manager)
                <button type="button" class="vxf-btn vxf-btn--primary" x-on:click="$dispatch('vxf-assign', {})"><x-heroicon-o-plus /> Assign Show</button>
            @endif
            <button type="button" class="vxf-btn" @click="toggleGuide()"><x-heroicon-o-light-bulb /> How it works</button>
            <a class="vxf-btn" href="{{ route('fulfillment.whiteboard') }}" target="_blank" rel="noopener"><x-heroicon-o-tv /> TV Whiteboard</a>
        </div>
    </div>

    <nav class="vxf-home-tabs" aria-label="Fulfillment sections">
        <a href="{{ \App\Filament\Pages\FulfillmentHome::getUrl() }}" aria-current="page" class="is-active">Overview</a>
        <a href="{{ \App\Filament\Pages\FulfillmentStationBoard::getUrl() }}">Station Board</a>
        <a href="#vxf-queue">Shows</a>
        @if (\App\Filament\Pages\ShipmentOverview::canAccess())<a href="{{ \App\Filament\Pages\ShipmentOverview::getUrl() }}">Orders</a>@endif
        <a href="{{ \App\Filament\Pages\FulfillmentActivity::getUrl(['tab' => 'issues']) }}">Issues</a>
        <a href="{{ \App\Filament\Pages\FulfillmentReports::getUrl() }}">Reports</a>
        <a href="{{ \App\Filament\Pages\FulfillmentActivity::getUrl(['tab' => 'schedule']) }}">Schedule</a>
    </nav>
    <section class="vxf-guide" x-show="guide" x-cloak x-transition aria-label="How the Fulfillment Center works">
        <ol>
            <li><b>1</b><div><strong>Shows arrive</strong><span>When a stream ends and its report is approved, the show appears in Ready to pack.</span></div></li>
            <li><b>2</b><div><strong>Assign</strong><span>{{ $manager ? 'Pick a packer and a station: Assign show on an idle station, or Assign in the list.' : 'A manager gives you a show and a station. Yours appear under My shows.' }}</span></div></li>
            <li><b>3</b><div><strong>Pack A–Z</strong><span>Press Start. Buyers are lettered in order; scan, Mark Packed, and it moves to the next one.</span></div></li>
            <li><b>4</b><div><strong>Finish &amp; sign off</strong><span>The packer reviews the show and confirms it's done. The fulfillment admin reviews it and signs it off.</span></div></li>
        </ol>
        <button type="button" class="vxf-iconbtn" @click="toggleGuide()" aria-label="Hide the guide"><x-heroicon-o-x-mark /></button>
    </section>

    @island(name: 'floor')
    @php
        $manager = $this->isManager();
        $stats = $this->stats;
        $shipments = \App\Filament\Pages\ShipmentOverview::canAccess() ? \App\Filament\Pages\ShipmentOverview::getUrl() : null;
        $tz = \App\Support\BusinessTime::zone();
    @endphp
    <div class="vxf-island" wire:poll.visible.20s>
    <section class="vxf-stats" aria-label="Today at a glance">
        @foreach (array_filter([
            ['Active stations', $stats['stations_active'] . ' / ' . $stats['stations_total'], 'packing now', 'heroicon-o-squares-2x2', null],
            ['Ready to pack', $stats['ready'], \Illuminate\Support\Str::plural('show', $stats['ready']), 'heroicon-o-inbox-stack', null],
            ['Packed today', $stats['completed_today'], null, 'heroicon-o-cube', null],
            $shipments ? ['In transit', $stats['in_transit'], 'last 30 days', 'heroicon-o-truck', $shipments] : null,
            $shipments ? ['Delivered', $stats['delivered'], 'last 30 days', 'heroicon-o-check-badge', $shipments] : null,
            ['Open issues', $stats['issues'], null, 'heroicon-o-exclamation-triangle', \App\Filament\Pages\FulfillmentActivity::getUrl(['tab' => 'issues'])],

        ]) as [$label, $value, $sub, $icon, $href])
            @if ($href)<a class="vxf-card vxf-stat vxf-stat--link" href="{{ $href }}">@else<div class="vxf-card vxf-stat">@endif
                <span class="vxf-stat__icon" aria-hidden="true"><x-dynamic-component :component="$icon" /></span>
                <div>
                    <p class="vxf-stat__label">{{ $label }}</p>
                    <p class="vxf-stat__value vxf-num" x-data x-init="window.vxfCountUp && vxfCountUp($el)">{{ $value }}</p>
                    @if ($sub)<span class="vxf-stat__sub">{{ $sub }}</span>@endif
                </div>
            @if ($href)</a>@else</div>@endif
        @endforeach
    </section>

    <section class="vxf-card vxf-floor" aria-labelledby="vxf-stations" x-data="{ status: 'all' }">
        <div class="vxf-card__head">
            <h2 id="vxf-stations">Packing Stations <span class="vxf-pill vxf-pill--packing">Live</span></h2>
            <div class="vxf-status-filters" role="group" aria-label="Filter stations by status">
                @foreach (['all' => 'All', 'packing' => 'Packing', 'hold' => 'On Hold', 'ready' => 'Ready', 'paused' => 'Paused', 'idle' => 'Idle'] as $key => $label)
                    <button type="button" class="vxf-tab" x-on:click="status = @js($key)" x-bind:aria-pressed="status === @js($key) ? 'true' : 'false'" x-bind:class="{ 'is-active': status === @js($key) }">{{ $label }} <span class="vxf-filter-count">{{ $key === 'all' ? $this->stations->count() : $this->stations->filter(fn ($item) => ($item['state'] === $key))->count() }}</span></button>
                @endforeach
            </div>
        </div>
        <div class="vxf-card__body">
            <div class="vxf-carousel" x-data="{ more: false, check() { const t = $refs.track; if (t) this.more = t.scrollWidth > t.clientWidth + 4 }, go(d) { const t = $refs.track; t?.scrollBy({ left: d * t.clientWidth * .9, behavior: 'smooth' }) } }" x-init="$nextTick(() => { check(); if ($refs.track) new ResizeObserver(() => check()).observe($refs.track) })">
            <button type="button" class="vxf-carousel__arrow vxf-carousel__arrow--prev" x-show="more" x-cloak @click="go(-1)" aria-label="Previous stations"><x-heroicon-o-chevron-left /></button>
            <div class="vxf-stations vxf-stations--track" x-ref="track">
                @forelse ($this->stations as $row)
                    @php $run = $row['run']; $state = $row['state']; $show = $run?->show;
                        $done = (int) ($run->completed_packages ?? 0); $total = $run?->expected_packages;
                        $pct = $total ? min(100, round($done / max(1, $total) * 100)) : 0; @endphp
                    @php $tone = ['packing' => 'packing', 'hold' => 'hold', 'paused' => 'paused', 'ready' => 'ready'][$state] ?? 'idle'; @endphp
                    <article class="vxf-card vxf-station vxf-station--{{ $tone }}" wire:key="station-{{ $row['station']->id }}" x-show="status === 'all' || status === @js($state)" x-cloak
                        @if ($manager) x-data="{ over: false }" x-bind:class="{ 'is-drop': over }"
                            x-on:dragover="if (window.vxfDnd.accepts($event, {{ $state === 'ready' ? "['run','packer']" : ($run ? "[]" : "['run']") }})) { $event.preventDefault(); over = true }"
                            x-on:dragleave="over = false"
                            x-on:drop.prevent="over = false; window.vxfDnd.dropOnStation($event, $wire, {{ \Illuminate\Support\Js::from(['station' => $row['station']->id, 'runId' => $state === 'ready' ? $run?->id : null, 'users' => $state === 'ready' && $show ? $show->fulfillmentUsers->pluck('id')->all() : []]) }})"
                        @endif>
                        <div class="vxf-station__top">
                            <span class="vxf-station__name">{{ $row['station']->name }}</span>
                            <span style="display:inline-flex;gap:6px;align-items:center">
                                <span class="vxf-pill vxf-pill--{{ $this->statusPill($state) }}">{{ $this->stateLabel($state) }}@if ($state === 'paused' && $run?->pause_reason) · {{ \App\Models\FulfillmentRun::pauseReasonLabel($run->pause_reason) }}@endif</span>
                                <a class="vxf-station__tv" href="{{ route('fulfillment.whiteboard', ['station' => $row['station']->id]) }}" target="_blank" rel="noopener" title="Open {{ $row['station']->name }} on the TV whiteboard" aria-label="Open {{ $row['station']->name }} on the TV whiteboard"><x-heroicon-o-tv /></a>
                            </span>
                        </div>
                        @if ($run && $show)
                            <p class="vxf-station__show" style="display:flex;align-items:center;gap:8px"><x-show-cover :show="$show" :size="28" /><span style="min-width:0;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">{{ $show->title }}</span></p>
                            <p class="vxf-station__meta">
                                <span><x-heroicon-o-calendar /> {{ $show->show_date?->format('M j') }}{{ $show->start_time ? ', ' . $show->start_time->timezone($tz)->format('g:i A') : '' }}</span>
                                <span><x-heroicon-o-user /> {{ ($run->packers->pluck('name')->join(', ')) ?: ($show->fulfillmentUsers->pluck('name')->join(', ') ?: 'No packer') }}</span>
                            </p>
                            <div class="vxf-station__mid">
                                <span class="vxf-station__letter {{ $row['current'] ? '' : 'is-empty' }}" aria-label="{{ $row['current'] ? 'Current buyer ' . $row['current']->letter : 'No buyer selected' }}">{{ $row['current']?->letter ?? ($state === 'paused' ? '❚❚' : '–') }}</span>
                                @include('fulfillment.ring', ['pct' => $pct, 'label' => $done . ' / ' . ($total ?? '?'), 'tone' => $tone, 'size' => 74])
                            </div>
                            <div class="vxf-station__foot">
                                @if ($state === 'ready')
                                    <span class="vxf-station__next"><x-heroicon-o-clock /> Waiting to start</span>
                                @elseif ($state === 'paused')
                                    <span class="vxf-station__next"><x-heroicon-o-pause /> Paused</span>
                                @elseif ($row['next'])
                                    <span class="vxf-station__next">Next: <strong>{{ $row['next']->buyer_username }}</strong></span>
                                @else
                                    <span class="vxf-station__next">Last buyer</span>
                                @endif
                                <span class="vxf-actions">
                                    @if ($manager && $state === 'ready')<button type="button" class="vxf-btn vxf-btn--sm" x-on:click="$dispatch('vxf-assign', {{ \Illuminate\Support\Js::from(['runId' => $run->id, 'title' => $show->title, 'users' => $show->fulfillmentUsers->pluck('id')->all(), 'station' => $row['station']->id]) }})">Assign</button>@endif
                                    <a class="vxf-btn vxf-btn--sm vxf-btn--icon" href="{{ \App\Filament\Pages\FulfillmentStationBoard::getUrl(['run' => $run->id]) }}" aria-label="Open {{ $row['station']->name }}" title="Open station"><x-heroicon-o-chevron-right /></a>
                                </span>
                            </div>
                        @else
                            <div class="vxf-station__idle"><x-heroicon-o-inbox /><span>No show assigned</span></div>
                            <div class="vxf-station__foot vxf-actions">
                                @if ($manager)
                                    <button type="button" class="vxf-btn vxf-btn--sm vxf-btn--primary" x-on:click="$dispatch('vxf-assign', {{ \Illuminate\Support\Js::from(['station' => $row['station']->id]) }})"><x-heroicon-o-plus /> Assign show</button>
                                @endif
                                <a class="vxf-btn vxf-btn--sm" href="{{ \App\Filament\Pages\FulfillmentStationBoard::getUrl(['station' => $row['station']->id]) }}">Open</a>
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="vxf-empty">No active stations. A fulfillment admin can add stations in Schedule &amp; Activity.</p>
                @endforelse
            </div>
            <button type="button" class="vxf-carousel__arrow vxf-carousel__arrow--next" x-show="more" x-cloak @click="go(1)" aria-label="More stations"><x-heroicon-o-chevron-right /></button>
            </div>
        </div>
    </section>

    </div>
    @endisland

    <div class="vxf-cols vxf-cols--full-queue">
        <div class="vxf-shows-col" style="display:flex;flex-direction:column;gap:10px;min-width:0">
        @island(name: 'queue')
        @php
            $manager = $this->isManager();
            $tz = \App\Support\BusinessTime::zone();
            $total = $this->queueTotal;
            $per = \App\Filament\Pages\FulfillmentHome::QUEUE_PER_PAGE;
            $pages = $this->queuePageCount();
            $requireReport = \App\Services\FulfillmentQueue::requiresApprovedReport();
            $approved = $requireReport ? $this->queueApproved : [];
            $ordersLink = \App\Filament\Resources\FulfillmentResource::canAccess();
        @endphp
        <div class="vxf-island" wire:poll.visible.60s>
        @if ($manager)
        {{-- Keep station targets visible while scrolling the show queue. Keyboard/touch users can use Assign. --}}
        <div class="vxf-queue-dock" role="region" aria-label="Station assignment shortcuts">
            <div class="vxf-queue-dock__label"><strong>Assign to station</strong><span>Drag a show here, or choose Assign on its row</span></div>
            <div class="vxf-queue-dock__stations">
                @foreach ($this->stationChoices as $choice)
                    <div class="vxf-queue-dock__target" x-data="{ over: false }" x-bind:class="{ 'is-over': over, 'is-busy': @js($choice['busy']) }"
                         x-on:dragover="if (!@js($choice['busy']) && window.vxfDnd?.accepts($event, ['run'])) { $event.preventDefault(); over = true }"
                         x-on:dragleave="over = false"
                         x-on:drop.prevent="over = false; if (!@js($choice['busy'])) window.vxfDnd.dropOnStation($event, $wire, { station: @js($choice['id']), runId: null, users: [] })">
                        <span class="vxf-queue-dock__name">{{ $choice['name'] }}</span>
                        <span class="vxf-queue-dock__state">{{ $choice['busy'] ? 'Occupied' : 'Available' }}</span>
                        @unless ($choice['busy'])
                            <button type="button" class="vxf-queue-dock__assign" x-on:click="$dispatch('vxf-assign', { station: @js($choice['id']) })" aria-label="Assign a show to {{ $choice['name'] }}">Assign</button>
                        @endunless
                    </div>
                @endforeach
            </div>
        </div>
        @endif
        <section class="vxf-card" aria-labelledby="vxf-queue">
            <div class="vxf-card__head">
                <h2 id="vxf-queue">{{ $manager ? "Today's Shows / Ready to Pack" : 'My shows' }} <span class="vxf-pill vxf-num">{{ $total }}</span></h2>
                <div class="vxf-tabs" role="group" aria-label="Job length" x-data="{ tab: @js($jobs) }">
                    @foreach (['all' => 'All', 'slow' => 'Long jobs', 'quick' => 'Quick'] as $key => $label)
                        <button type="button" class="vxf-tab" x-on:click="tab = '{{ $key }}'" wire:click="$set('jobs', '{{ $key }}')" x-bind:aria-pressed="tab === '{{ $key }}' ? 'true' : 'false'" aria-pressed="{{ $jobs === $key ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <div class="vxf-queue-search">
                <label for="vxf-show-search" class="vxf-sub">Find a show</label>
                <input id="vxf-show-search" type="search" wire:model.live.debounce.400ms="queueSearch" placeholder="Search show title, streamer, or Whatnot ID" autocomplete="off" aria-label="Search fulfillment shows" />
                @if ($queueSearch !== '')<button type="button" class="vxf-btn vxf-btn--sm" wire:click="$set('queueSearch', '')">Clear</button>@endif
            </div>
            @if ($manager && count($this->team))
                <div class="vxf-dnd-team" aria-label="Fulfillment team">
                    <span class="vxf-sub"><span class="vxf-dnd-desktop-hint">Drag a teammate onto a show, or drag a show to a station above.</span><span class="vxf-dnd-mobile-hint">Use Assign on a show or station to choose a packer and station.</span></span>
                    <div class="vxf-dnd-team__chips">
                        @foreach ($this->team as $p)
                            <span class="vxf-chip vxf-dnd-chip" draggable="true" title="Drag {{ $p['name'] }} onto a show or a ready station"
                                  x-on:dragstart="window.vxfDnd.start($event, 'packer', {{ \Illuminate\Support\Js::from(['id' => $p['id'], 'name' => $p['name']]) }})"><b>{{ $p['initials'] }}</b> {{ $p['name'] }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
            @if ($this->queue->isEmpty())
                <p class="vxf-empty">{{ $jobs !== 'all' ? 'No ' . ($jobs === 'slow' ? 'long' : 'quick') . ' jobs waiting.' : ($manager ? 'Nothing waiting. New shows appear here once they end and their orders sync.' : 'No shows are assigned to you right now.') }}</p>
            @else
                <div class="vxf-table-wrap" wire:loading.class="vxf-dim" wire:target="jobs, setQueuePage">
                    <table class="vxf-table">
                        <thead><tr><th scope="col">Show</th><th scope="col">Streamed</th><th scope="col">Orders · Pkgs</th><th scope="col">Station · Packer</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                        <tbody>
                            @foreach ($this->queue as $run)
                                @php $show = $run->show; $people = $run->packers->isNotEmpty() ? $run->packers : $show->fulfillmentUsers;
                                    $state = ($run->isOnStation() && $run->open_flags_count) ? 'hold' : $run->status;
                                    $needsReport = $requireReport && $run->status === 'ready' && ! in_array((int) $show->id, $approved, true); @endphp
                                @php $dndRun = ['runId' => $run->id, 'title' => $show->title, 'users' => $show->fulfillmentUsers->pluck('id')->all(), 'station' => $run->station_id]; @endphp
                                <tr wire:key="q-{{ $run->id }}"
                                    @if ($manager && $run->status === 'ready') draggable="true" class="vxf-dnd-row" title="Drag onto a station, or drop a person here"
                                        x-data="{ over: false }" x-bind:class="{ 'is-drop': over }"
                                        x-on:dragstart="window.vxfDnd.start($event, 'run', {{ \Illuminate\Support\Js::from($dndRun) }})"
                                        x-on:dragover="if (window.vxfDnd.accepts($event, ['packer'])) { $event.preventDefault(); over = true }"
                                        x-on:dragleave="over = false"
                                        x-on:drop.prevent="over = false; window.vxfDnd.dropOnRun($event, $wire, {{ \Illuminate\Support\Js::from($dndRun) }})"
                                    @endif>
                                    <td style="min-width:200px"><span style="display:flex;gap:10px;align-items:flex-start"><x-show-cover :show="$show" :size="36" /><span style="min-width:0"><strong>{{ $show->title }}</strong>@if ($show->is_slow_pack) <span class="vxf-pill vxf-pill--paused" title="Marked as a slow pack">Long job</span>@endif<span class="vxf-sub">{{ $show->channel?->name ?? 'No channel' }} · {{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer' }}</span></span></span></td>
                                    <td class="vxf-num" style="white-space:nowrap">{{ $show->show_date?->format('M j') }}@if ($show->start_time)<span class="vxf-sub">{{ $show->start_time->timezone($tz)->format('g:i A') }}</span>@endif</td>
                                    <td class="vxf-num" style="white-space:nowrap">{!! $run->order_count !== null ? $run->order_count : '<span class="vxf-unknown">—</span>' !!} · {!! $run->expected_packages !== null ? $run->expected_packages : '<span class="vxf-unknown" title="Counted once packing starts">pending</span>' !!}</td>
                                    <td>{{ $run->station?->name ?? '—' }}<span class="vxf-sub">
                                        @if ($people->isEmpty())<span style="color:#c2410c;font-weight:700">Unassigned</span>@else{{ $people->pluck('name')->join(', ') }}@endif</span></td>
                                    <td>
                                        <span class="vxf-pill vxf-pill--{{ $this->statusPill($state) }}">{{ $this->stateLabel($state) }}</span>
                                        @if ($needsReport)<span class="vxf-sub">Waiting on report approval</span>@endif
                                    </td>
                                    <td style="text-align:right;white-space:nowrap">
                                        @if ($ordersLink)<a class="vxf-btn vxf-btn--sm vxf-btn--icon" href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('view', ['record' => $show->id]) }}" title="Order details" aria-label="Order details for {{ $show->title }}"><x-heroicon-o-document-text /></a>@endif
                                        @if ($manager)<button type="button" class="vxf-btn vxf-btn--sm" x-on:click="$dispatch('vxf-assign', {{ \Illuminate\Support\Js::from(['runId' => $run->id, 'title' => $show->title, 'users' => $show->fulfillmentUsers->pluck('id')->all(), 'station' => $run->station_id]) }})">{{ $people->isEmpty() ? 'Assign' : 'Edit' }}</button>@endif
                                        @if ($needsReport)
                                            <span class="vxf-btn vxf-btn--sm" aria-disabled="true" title="Packing starts once the streamer report is approved" style="opacity:.55;cursor:not-allowed">Start</span>
                                        @else
                                            <a class="vxf-btn vxf-btn--sm vxf-btn--primary" href="{{ \App\Filament\Pages\FulfillmentStationBoard::getUrl(['run' => $run->id]) }}">{{ $run->isOnStation() ? 'Open packing' : 'Open to start' }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($pages > 1)
                    <nav class="vxf-pager" aria-label="Ready to pack pages">
                        <button type="button" class="vxf-btn vxf-btn--sm vxf-btn--icon" wire:click="setQueuePage({{ $queuePage - 1 }})" @disabled($queuePage <= 1) aria-label="Previous page"><x-heroicon-o-chevron-left /></button>
                        @foreach (array_unique([1, max(1, $queuePage - 1), $queuePage, min($pages, $queuePage + 1), $pages]) as $i)
                            <button type="button" class="vxf-pager__num {{ $i === $queuePage ? 'is-on' : '' }}" wire:click="setQueuePage({{ $i }})" aria-label="Page {{ $i }}" @if ($i === $queuePage) aria-current="page" @endif>{{ $i }}</button>
                        @endforeach
                        <button type="button" class="vxf-btn vxf-btn--sm vxf-btn--icon" wire:click="setQueuePage({{ $queuePage + 1 }})" @disabled($queuePage >= $pages) aria-label="Next page"><x-heroicon-o-chevron-right /></button>
                        <span class="vxf-sub vxf-num">{{ ($queuePage - 1) * $per + 1 }}–{{ min($queuePage * $per, $total) }} of {{ $total }}</span>
                    </nav>
                @endif
            @endif
        </section>

        </div>
        @endisland

        </div>
        <div class="vxf-operations-heading"><h2>Operations at a Glance</h2><span class="vxf-sub"><span class="vxf-live"><i aria-hidden="true"></i> Live</span></span></div>
        <div class="vxf-activity-col">
        @island(name: 'feed')
        @php $tz = \App\Support\BusinessTime::zone(); @endphp
        <div class="vxf-island" wire:poll.visible.30s>
        <section class="vxf-card" aria-labelledby="vxf-activity">
            <div class="vxf-card__head"><h2 id="vxf-activity">Live Activity</h2>
                <a class="vxf-btn vxf-btn--sm" href="{{ \App\Filament\Pages\FulfillmentActivity::getUrl(['tab' => 'shows']) }}">Show logs</a></div>
            @if ($this->activity->isEmpty())
                <p class="vxf-empty">Nothing packed yet today.</p>
            @else
                <ul class="vxf-feed">
                    @foreach ($this->activity->take(5) as $e)
                        @php $kind = ['buyer_packed' => 'ok', 'item_verified' => 'ok', 'approved' => 'ok', 'finished' => 'brand', 'started' => 'brand', 'issue_flagged' => 'bad', 'issue_resolved' => 'ok', 'paused' => 'warn'][$e->type] ?? 'brand'; @endphp
                        <li class="vxf-feed__item vxf-feed__item--{{ $kind }}">
                            <span class="vxf-feed__icon" aria-hidden="true">@if ($e->buyer_letter)<b>{{ $e->buyer_letter }}</b>@elseif ($kind === 'bad')<x-heroicon-o-exclamation-triangle />@elseif ($kind === 'warn')<x-heroicon-o-pause />@else<x-heroicon-o-check />@endif</span>
                            <span class="vxf-feed__text"><strong title="{{ $e->buyer_username ?? $e->station?->name ?? 'Fulfillment' }}">{{ $e->buyer_username ?? $e->station?->name ?? 'Fulfillment' }}</strong> {{ ['buyer_packed' => 'packed', 'item_verified' => 'verified', 'issue_flagged' => 'issue flagged', 'issue_resolved' => 'issue resolved', 'started' => 'started packing', 'finished' => 'finished the show', 'paused' => 'paused', 'approved' => 'signed off', 'acknowledged' => 'confirmed done'][$e->type] ?? \App\Models\FulfillmentEvent::label($e->type) }}
                                <span class="vxf-sub">{{ $e->station?->name }}{{ $e->user ? ' · ' . explode(' ', $e->user->name)[0] : '' }}</span></span>
                            <time class="vxf-sub vxf-num">{{ $e->created_at?->timezone($tz)->format('g:i A') }}</time>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
        </div>
        @endisland
        </div>

        <aside class="vxf-summary-col" style="display:flex;flex-direction:column;gap:10px;min-width:0">
            @island(name: 'side')
            @php
                $manager = $this->isManager();
                $stats = $this->stats;
                $tz = \App\Support\BusinessTime::zone();
                $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
            @endphp
            <div class="vxf-island" wire:poll.visible.30s>
            @if ($this->signoffQueue->isNotEmpty())
                @php $queue = $this->signoffQueue; @endphp
                <section class="vxf-card vxf-signq" aria-labelledby="vxf-signoff">
                    <div class="vxf-card__head">
                        <h2 id="vxf-signoff">Ready for sign-off</h2>
                        <span class="vxf-signq__count">{{ $queue->count() }}</span>
                    </div>
                    <ul class="vxf-list">
                        @foreach ($queue->take(2) as $done)
                            <li>
                                <span class="vxf-dot vxf-dot--ok" aria-hidden="true"><x-heroicon-o-check-badge /></span>
                                <div style="min-width:0;flex:1">
                                    <strong title="{{ $done->show?->title }}">{{ $done->show?->title }}</strong>
                                    <span class="vxf-sub">{{ $done->completed_packages }} packages · {{ $done->acknowledged_at ? 'confirmed by ' . $done->acknowledger?->name : 'packed by ' . ($done->packers->pluck('name')->join(', ') ?: '—') }} · {{ $done->finished_at?->diffForHumans(short: true) }}@if ($done->open_flags_count) · <b style="color:#c2410c">{{ $done->open_flags_count }} open {{ \Illuminate\Support\Str::plural('issue', $done->open_flags_count) }}</b>@endif</span>
                                </div>
                                <button type="button" class="vxf-btn vxf-btn--sm vxf-btn--primary" wire:click="openSignoff({{ $done->id }})">Review</button>
                            </li>
                        @endforeach
                    </ul>
                    @if ($queue->count() > 4)
                        <button type="button" class="vxf-signq__more" x-on:click="all = ! all" x-text="all ? 'Show fewer' : 'Show all {{ $queue->count() }}'"></button>
                    @endif
                </section>
            @endif
            @php $doneToday = $stats['completed_today']; $goal = $doneToday + $stats['remaining']; $todayPct = $goal ? min(100, round($doneToday / $goal * 100)) : 0; @endphp
            <section class="vxf-card vxf-today" aria-labelledby="vxf-today">
                <div class="vxf-card__head"><h2 id="vxf-today">Packing progress today</h2><span class="vxf-num vxf-today__pct">{{ $todayPct }}%</span></div>
                <div class="vxf-card__body">
                    <p class="vxf-today__count"><b class="vxf-num">{{ $doneToday }}</b> / {{ $goal }} packages</p>
                    <div class="vxf-bar vxf-bar--thick" role="progressbar" aria-valuenow="{{ $todayPct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Packed today"><span style="width: {{ $todayPct }}%"></span></div>
                    <p class="vxf-sub" style="margin:6px 0 0">{{ $stats['buyers_today'] }} buyers packed · {{ $stats['remaining'] }} packages left on open shows</p>
                </div>
            </section>


            <section class="vxf-card" aria-labelledby="vxf-issues">
                <div class="vxf-card__head">
                    <h2 id="vxf-issues">Open issues</h2>
                    <a class="vxf-btn vxf-btn--sm" href="{{ \App\Filament\Pages\FulfillmentActivity::getUrl(['tab' => 'issues']) }}">View all</a>
                </div>
                @if ($this->openIssues->isEmpty())
                    <p class="vxf-empty">No open issues.</p>
                @else
                    <ul class="vxf-list">
                        @foreach ($this->openIssues->take(3) as $flag)
                            <li>
                                <span class="vxf-dot vxf-dot--warn" aria-hidden="true"><x-heroicon-o-exclamation-triangle /></span>
                                <div style="min-width:0;flex:1">
                                    <strong title="{{ \App\Services\FulfillmentFlagService::label($flag->type) }}">{{ \App\Services\FulfillmentFlagService::label($flag->type) }}</strong>
                                    @if ($flag->order_number) · Order {{ $flag->order_number }}@endif
                                    <span class="vxf-sub" title="{{ $flag->show?->title }}">{{ $flag->show?->title }}@if ($flag->buyer) · {{ $flag->buyer->letter }} · {{ $flag->buyer->buyer_username }}@endif</span>
                                </div>
                                <span class="vxf-sub" style="white-space:nowrap">{{ $flag->created_at?->diffForHumans(short: true) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="vxf-card" aria-labelledby="vxf-schedule">
                <div class="vxf-card__head">
                    <h2 id="vxf-schedule">{{ $manager ? "Today's schedule" : 'My shift' }}</h2>
                    <a class="vxf-btn vxf-btn--sm" href="{{ \App\Filament\Pages\FulfillmentActivity::getUrl(['tab' => 'schedule']) }}">Schedule</a>
                </div>
                @if (! $manager && $this->nextShift)
                    <div class="vxf-card__body">
                        <p style="margin:0;font-weight:800">{{ $this->nextShift->starts_at->timezone($tz)->format('D M j · g:i A') }} – {{ $this->nextShift->ends_at->timezone($tz)->format('g:i A') }}</p>
                        <p class="vxf-sub">{{ $this->nextShift->station?->name ?? 'Station to be assigned' }}{{ $this->nextShift->crossesMidnight() ? ' · overnight' : '' }}</p>
                    </div>
                @endif
                @if ($this->todayShifts->isEmpty())
                    @if ($manager || ! $this->nextShift)<p class="vxf-empty">No shifts today.</p>@endif
                @else
                    <ul class="vxf-list">
                        @foreach ($this->todayShifts->take(3) as $shift)
                            <li>
                                <span class="vxf-avatar" aria-hidden="true">{{ $initials($shift->user?->name) }}</span>
                                <div style="min-width:0;flex:1"><strong title="{{ $shift->user?->name }}">{{ $shift->user?->name }}</strong>
                                    <span class="vxf-sub" title="{{ ($shift->station?->name ?? 'No station') . ($shift->shows->isNotEmpty() ? ' · ' . $shift->shows->pluck('title')->join(', ') : '') }}">{{ $shift->station?->name ?? 'No station' }}@if ($shift->shows->isNotEmpty()) · {{ $shift->shows->pluck('title')->join(', ') }}@endif</span></div>
                                <span class="vxf-sub vxf-num" style="white-space:nowrap">{{ $shift->starts_at->timezone($tz)->format('g:i A') }}–{{ $shift->ends_at->timezone($tz)->format('g:i A') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if (class_exists(\App\Filament\Pages\Communications::class) && \App\Filament\Pages\Communications::canAccess())
                <a class="vxf-card vxf-card__body" style="display:flex;gap:10px;align-items:center;text-decoration:none;color:inherit" href="{{ \App\Filament\Pages\Communications::getUrl() }}">
                    <span class="vxf-dot" aria-hidden="true"><x-heroicon-o-chat-bubble-left-right /></span>
                    <span><strong>Messages</strong><span class="vxf-sub">Talk to the fulfillment team</span></span>
                </a>
            @endif
            </div>
            @endisland
        </aside>
    </div>

    @if ($manager)
        @include('fulfillment.assign-modal')
    @endif

    @island(name: 'signoff')
        @include('fulfillment.signoff-review')
    @endisland
</div>

@script
<script>
window.vxfDnd = window.vxfDnd || {
    kind: null, data: null,
    start(e, kind, data) { this.kind = kind; this.data = data; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', kind + ':' + (data.runId ?? data.id)); },
    accepts(e, kinds) { return this.kind !== null && kinds.includes(this.kind); },
    async save(wire, runId, users, station) {
        const r = await wire.saveAssignment(runId, users, station ?? null);
        if (r && !r.ok && window.FilamentNotification) new FilamentNotification().title(r.message).warning().send();
        this.kind = null; this.data = null;
    },
    dropOnStation(e, wire, target) {
        if (this.kind === 'run') return this.save(wire, this.data.runId, this.data.users, target.station);
        if (this.kind === 'packer' && target.runId) return this.save(wire, target.runId, [...new Set([...(target.users || []), this.data.id])], target.station);
    },
    dropOnRun(e, wire, target) {
        if (this.kind === 'packer') return this.save(wire, target.runId, [...new Set([...(target.users || []), this.data.id])], target.station);
    },
};
if (!window.vxfDndEnd) { window.vxfDndEnd = true; document.addEventListener('dragend', () => { if (window.vxfDnd) setTimeout(() => { window.vxfDnd.kind = null }, 0) }); }
</script>
@endscript
</x-filament-panels::page>
