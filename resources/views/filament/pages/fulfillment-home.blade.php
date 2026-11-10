<x-filament-panels::page>
@php
    $manager = $this->isManager();
@endphp
@include('fulfillment.motion')
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
            <div class="vxf-more" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
                <button type="button" class="vxf-btn vxf-btn--icon" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-controls="vxf-more-menu" aria-label="More fulfillment actions" title="More actions"><x-heroicon-o-ellipsis-horizontal /></button>
                <div id="vxf-more-menu" class="vxf-more__menu" x-show="open" x-cloak>
                    <button type="button" class="vxf-btn" @click="toggleGuide(); open = false"><x-heroicon-o-light-bulb /> How it works</button>
                    <a class="vxf-btn" href="{{ route('fulfillment.whiteboard') }}" target="_blank" rel="noopener"><x-heroicon-o-tv /> TV Whiteboard</a>
                </div>
            </div>
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
                    <article class="vxf-card vxf-station vxf-station--{{ $tone }}" wire:key="station-{{ $row['station']->id }}" x-show="status === 'all' || status === @js($state)" x-cloak>
                        <div class="vxf-station__top">
                            <span class="vxf-station__name">{{ $row['station']->name }}</span>
                            <span style="display:inline-flex;gap:6px;align-items:center">
                                <span class="vxf-pill vxf-pill--{{ $this->statusPill($state) }}">{{ $this->stateLabel($state) }}</span>
                                <a class="vxf-station__tv" href="{{ route('fulfillment.whiteboard', ['station' => $row['station']->id]) }}" target="_blank" rel="noopener" title="Open {{ $row['station']->name }} on the TV whiteboard" aria-label="Open {{ $row['station']->name }} on the TV whiteboard"><x-heroicon-o-tv /></a>
                            </span>
                        </div>
                        @if ($run && $show)
                            <p class="vxf-station__show">{{ $show->title }}</p>
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
        <section class="vxf-card" aria-labelledby="vxf-queue">
            <div class="vxf-card__head">
                <h2 id="vxf-queue">{{ $manager ? "Today's Shows / Ready to Pack" : 'My shows' }} <span class="vxf-pill vxf-num">{{ $total }}</span></h2>
                <div class="vxf-tabs" role="group" aria-label="Job length" x-data="{ tab: @js($jobs) }">
                    @foreach (['all' => 'All', 'slow' => 'Long jobs', 'quick' => 'Quick'] as $key => $label)
                        <button type="button" class="vxf-tab" x-on:click="tab = '{{ $key }}'" wire:click="$set('jobs', '{{ $key }}')" x-bind:aria-pressed="tab === '{{ $key }}' ? 'true' : 'false'" aria-pressed="{{ $jobs === $key ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
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
                                <tr wire:key="q-{{ $run->id }}">
                                    <td style="min-width:200px"><strong>{{ $show->title }}</strong>@if ($show->is_slow_pack) <span class="vxf-pill vxf-pill--paused" title="Marked as a slow pack">Long job</span>@endif<span class="vxf-sub">{{ $show->channel?->name ?? 'No channel' }} · {{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer' }}</span></td>
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
                                            <a class="vxf-btn vxf-btn--sm vxf-btn--primary" href="{{ \App\Filament\Pages\FulfillmentStationBoard::getUrl(['run' => $run->id]) }}">{{ $run->isOnStation() ? 'Open' : 'Start' }}</a>
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
</x-filament-panels::page>
