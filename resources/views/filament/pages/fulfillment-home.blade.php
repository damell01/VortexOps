<x-filament-panels::page>
@php
    $manager = $this->isManager();
    $stats = $this->stats;
    $tz = \App\Support\BusinessTime::zone();
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp
@include('fulfillment.dev-banner')
<div class="vxf" wire:poll.20s>
    <div class="vxf-head">
        <div>
            <h1>{{ $manager ? 'Fulfillment Operations' : 'My Fulfillment' }}</h1>
            <p>{{ $manager ? 'Live stations, shows ready to pack, and packing activity.' : 'Your shows, your station and your shift.' }}</p>
        </div>
        <div class="vxf-actions">
            <a class="vxf-btn" href="{{ \App\Filament\Pages\FulfillmentStationBoard::getUrl() }}"><x-heroicon-o-qr-code /> Station Board</a>
            <a class="vxf-btn" href="{{ \App\Filament\Pages\FulfillmentActivity::getUrl() }}"><x-heroicon-o-calendar-days /> Schedule &amp; Activity</a>
            <a class="vxf-btn" href="{{ route('fulfillment.whiteboard') }}" target="_blank" rel="noopener"><x-heroicon-o-tv /> TV Whiteboard</a>
        </div>
    </div>

    <section class="vxf-stats" aria-label="Today at a glance">
        @foreach (array_filter([
            ['Stations packing', $stats['stations_active'] . ' / ' . $stats['stations_total'], null, 'heroicon-o-squares-2x2'],
            ['Ready to pack', $stats['ready'], 'shows', 'heroicon-o-inbox-stack'],
            ['Expected packages', $stats['expected'], $stats['expected_unknown'] ? $stats['expected_unknown'] . ' show(s) pending count' : 'open shows', 'heroicon-o-cube'],
            ['Packed today', $stats['completed_today'], $stats['buyers_today'] . ' buyers', 'heroicon-o-check-badge'],
            ['Remaining', $stats['remaining'], 'packages in open shows', 'heroicon-o-clock'],
            ['Open issues', $stats['issues'], null, 'heroicon-o-flag'],
            $manager ? ['Working now', $stats['working'], 'clocked in', 'heroicon-o-user-group'] : null,
        ]) as [$label, $value, $sub, $icon])
            <div class="vxf-card vxf-stat">
                <span class="vxf-stat__icon" aria-hidden="true"><x-dynamic-component :component="$icon" /></span>
                <div>
                    <p class="vxf-stat__label">{{ $label }}</p>
                    <p class="vxf-stat__value vxf-num">{{ $value }}</p>
                    @if ($sub)<span class="vxf-stat__sub">{{ $sub }}</span>@endif
                </div>
            </div>
        @endforeach
    </section>

    <section class="vxf-card" aria-labelledby="vxf-stations">
        <div class="vxf-card__head">
            <h2 id="vxf-stations">Packing stations <span class="vxf-pill vxf-pill--packing" style="margin-left:6px">Live</span></h2>
        </div>
        <div class="vxf-card__body">
            <div class="vxf-stations">
                @forelse ($this->stations as $row)
                    @php $run = $row['run']; $state = $row['state']; $show = $run?->show;
                        $done = (int) ($run->completed_packages ?? 0); $total = $run?->expected_packages;
                        $pct = $total ? min(100, round($done / max(1, $total) * 100)) : 0; @endphp
                    <article class="vxf-card vxf-station {{ $state === 'packing' ? 'vxf-station--packing' : '' }}" wire:key="station-{{ $row['station']->id }}">
                        <div class="vxf-station__top">
                            <span class="vxf-station__name">{{ $row['station']->name }}</span>
                            <span class="vxf-pill vxf-pill--{{ $this->statusPill($state) }}">{{ $this->stateLabel($state) }}</span>
                        </div>
                        @if ($run && $show)
                            <p class="vxf-station__show">{{ $show->title }}</p>
                            <p class="vxf-station__meta">
                                {{ $show->channel?->name ?? 'No channel' }}
                                · {{ ($run->packers->pluck('name')->join(', ')) ?: ($show->fulfillmentUsers->pluck('name')->join(', ') ?: 'No packer') }}
                                @if ($run->started_at)<br>Started {{ $run->started_at->timezone($tz)->format('M j, g:i A') }}@endif
                            </p>
                            <div class="vxf-station__foot">
                                <div class="vxf-station__count"><span><strong class="vxf-num">{{ $done }}</strong> / {!! $total !== null ? '<strong class="vxf-num">'.$total.'</strong>' : '<span class="vxf-unknown">pending</span>' !!} packages</span><span class="vxf-num">{{ $total ? $pct . '%' : '' }}</span></div>
                                <div class="vxf-bar" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Packing progress"><span style="width: {{ $pct }}%"></span></div>
                                <div class="vxf-actions">
                                    <a class="vxf-btn vxf-btn--sm" href="{{ \App\Filament\Pages\FulfillmentStationBoard::getUrl(['run' => $run->id]) }}">View station</a>
                                    @if ($manager && $state === 'ready')<button type="button" class="vxf-btn vxf-btn--sm" wire:click="openAssign({{ $run->id }})">Assign</button>@endif
                                </div>
                            </div>
                        @else
                            <p class="vxf-station__meta" style="margin:auto 0">No show assigned.</p>
                            <div class="vxf-station__foot">
                                <a class="vxf-btn vxf-btn--sm" href="{{ \App\Filament\Pages\FulfillmentStationBoard::getUrl(['station' => $row['station']->id]) }}">Open station</a>
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="vxf-empty">No active stations. A fulfillment admin can add stations in Schedule &amp; Activity.</p>
                @endforelse
            </div>
        </div>
    </section>

    <div class="vxf-cols">
        <section class="vxf-card" aria-labelledby="vxf-queue">
            <div class="vxf-card__head">
                <h2 id="vxf-queue">{{ $manager ? 'Ready to pack' : 'My shows' }}</h2>
                <span class="vxf-sub">Ended shows with orders to fulfill</span>
            </div>
            @if ($this->queue->isEmpty())
                <p class="vxf-empty">{{ $manager ? 'Nothing waiting. New shows appear here once they end and their orders sync.' : 'No shows are assigned to you right now.' }}</p>
            @else
                <div class="vxf-table-wrap">
                    <table class="vxf-table">
                        <thead><tr><th scope="col">Show</th><th scope="col">Streamed</th><th scope="col">Orders</th><th scope="col">Packages</th><th scope="col">Station · Packer</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                        <tbody>
                            @foreach ($this->queue as $run)
                                @php $show = $run->show; $people = $run->packers->isNotEmpty() ? $run->packers : $show->fulfillmentUsers;
                                    $state = ($run->isOnStation() && $run->open_flags_count) ? 'hold' : $run->status;
                                    $needsReport = \App\Services\FulfillmentQueue::requiresApprovedReport() && $run->status === 'ready' && ! \App\Services\FulfillmentQueue::reportApproved($show); @endphp
                                <tr wire:key="q-{{ $run->id }}">
                                    <td style="min-width:200px"><strong>{{ $show->title }}</strong><span class="vxf-sub">{{ $show->channel?->name ?? 'No channel' }} · {{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer' }}</span></td>
                                    <td class="vxf-num" style="white-space:nowrap">{{ $show->show_date?->format('M j') }}@if ($show->start_time)<span class="vxf-sub">{{ $show->start_time->timezone($tz)->format('g:i A') }}</span>@endif</td>
                                    <td class="vxf-num">{!! $run->order_count !== null ? $run->order_count : '<span class="vxf-unknown">pending</span>' !!}</td>
                                    <td class="vxf-num">{!! $run->expected_packages !== null ? $run->expected_packages : '<span class="vxf-unknown">pending</span>' !!}</td>
                                    <td>{{ $run->station?->name ?? '—' }}<span class="vxf-sub">
                                        @if ($people->isEmpty())<span style="color:#c2410c;font-weight:700">Unassigned</span>@else{{ $people->pluck('name')->join(', ') }}@endif</span></td>
                                    <td>
                                        <span class="vxf-pill vxf-pill--{{ $this->statusPill($state) }}">{{ $this->stateLabel($state) }}</span>
                                        @if ($needsReport)<span class="vxf-sub">Waiting on report approval</span>@endif
                                    </td>
                                    <td style="text-align:right;white-space:nowrap">
                                        @if ($manager)<button type="button" class="vxf-btn vxf-btn--sm" wire:click="openAssign({{ $run->id }})">{{ $people->isEmpty() ? 'Assign' : 'Edit' }}</button>@endif
                                        <a class="vxf-btn vxf-btn--sm vxf-btn--primary" href="{{ \App\Filament\Pages\FulfillmentStationBoard::getUrl(['run' => $run->id]) }}">{{ $run->isOnStation() ? 'Open' : 'Start' }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <aside style="display:flex;flex-direction:column;gap:14px;min-width:0">
            <section class="vxf-card" aria-labelledby="vxf-issues">
                <div class="vxf-card__head">
                    <h2 id="vxf-issues">Open issues</h2>
                    <a class="vxf-btn vxf-btn--sm" href="{{ \App\Filament\Pages\FulfillmentActivity::getUrl(['tab' => 'issues']) }}">View all</a>
                </div>
                @if ($this->openIssues->isEmpty())
                    <p class="vxf-empty">No open issues.</p>
                @else
                    <ul class="vxf-list">
                        @foreach ($this->openIssues as $flag)
                            <li>
                                <span class="vxf-dot vxf-dot--warn" aria-hidden="true"><x-heroicon-o-exclamation-triangle /></span>
                                <div style="min-width:0;flex:1">
                                    <strong>{{ \App\Services\FulfillmentFlagService::label($flag->type) }}</strong>
                                    @if ($flag->order_number) · Order {{ $flag->order_number }}@endif
                                    <span class="vxf-sub">{{ $flag->show?->title }}@if ($flag->buyer) · {{ $flag->buyer->letter }} · {{ $flag->buyer->buyer_username }}@endif</span>
                                </div>
                                <span class="vxf-sub" style="white-space:nowrap">{{ $flag->created_at?->diffForHumans(short: true) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="vxf-card" aria-labelledby="vxf-today">
                <div class="vxf-card__head">
                    <h2 id="vxf-today">{{ $manager ? "Today's schedule" : 'My shift' }}</h2>
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
                        @foreach ($this->todayShifts as $shift)
                            <li>
                                <span class="vxf-avatar" aria-hidden="true">{{ $initials($shift->user?->name) }}</span>
                                <div style="min-width:0;flex:1"><strong>{{ $shift->user?->name }}</strong>
                                    <span class="vxf-sub">{{ $shift->station?->name ?? 'No station' }}@if ($shift->shows->isNotEmpty()) · {{ $shift->shows->pluck('title')->join(', ') }}@endif</span></div>
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
        </aside>
    </div>

    @if ($assignRunId)
        @php $assignRun = \App\Models\FulfillmentRun::query()->with('show:id,title')->find($assignRunId); @endphp
        <div class="vxf-drawer-backdrop" wire:click="closeAssign"></div>
        <div class="vxf-drawer" role="dialog" aria-modal="true" aria-labelledby="vxf-assign-title" x-data x-init="$nextTick(() => $el.querySelector('select')?.focus())" @keydown.escape.window="$wire.closeAssign()">
            <div class="vxf-drawer__head">
                <h2 id="vxf-assign-title">Assign show</h2>
                <button type="button" class="vxf-icon-btn" wire:click="closeAssign" aria-label="Close"><x-heroicon-o-x-mark /></button>
            </div>
            <div class="vxf-drawer__body">
                <p style="margin:0;font-weight:700">{{ $assignRun?->show?->title }}</p>
                <label class="vxf-field">Packers (one by default; add more to share the show)
                    <select class="vxf-select" multiple size="6" wire:model="assignUsers">
                        @foreach ($this->teamOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </label>
                @error('assignUsers')<p class="vxf-alert" role="alert">{{ $message }}</p>@enderror
                <label class="vxf-field">Station (optional)
                    <select class="vxf-select" wire:model="assignStation">
                        <option value="">Choose when starting</option>
                        @foreach (\App\Models\FulfillmentStation::query()->where('active', true)->orderBy('sort')->get() as $station)<option value="{{ $station->id }}">{{ $station->name }}</option>@endforeach
                    </select>
                </label>
                <p class="vxf-sub">Newly assigned people get a notification. Hold Ctrl / ⌘ to pick more than one.</p>
            </div>
            <div class="vxf-drawer__foot">
                <button type="button" class="vxf-btn" wire:click="closeAssign">Cancel</button>
                <button type="button" class="vxf-btn vxf-btn--primary" wire:click="saveAssign" wire:loading.attr="disabled">Save assignment</button>
            </div>
        </div>
    @endif
</div>
</x-filament-panels::page>
