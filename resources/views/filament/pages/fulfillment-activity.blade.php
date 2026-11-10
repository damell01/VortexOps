<x-filament-panels::page>
@php
    $manager = $this->isManager();
    $tz = \App\Support\BusinessTime::zone();
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $pill = fn ($s) => ['packing' => 'packing', 'ready' => 'ready', 'paused' => 'paused', 'completed' => 'completed', 'approved' => 'approved'][$s] ?? '';
@endphp
@include('fulfillment.dev-banner')
<div class="vxf">
    <div class="vxf-head">
        <div>
            <h1>Schedule &amp; Activity</h1>
            <p>{{ $manager ? 'Team schedule, show logs, issues and productivity.' : 'Your schedule, your shows and your numbers.' }}</p>
        </div>
        <div class="vxf-actions">
            <a class="vxf-btn" href="{{ \App\Filament\Pages\FulfillmentHome::getUrl() }}"><x-heroicon-o-home /> Fulfillment Home</a>
            <a class="vxf-btn" href="{{ \App\Filament\Pages\FulfillmentStationBoard::getUrl() }}"><x-heroicon-o-qr-code /> Station Board</a>
        </div>
    </div>

    <div class="vxf-tabs" role="tablist" aria-label="Sections">
        @foreach (\App\Filament\Pages\FulfillmentActivity::TABS as $key => $label)
            <button type="button" role="tab" class="vxf-tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="setTab('{{ $key }}')">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ── Schedule ─────────────────────────────────────────────────── --}}
    @if ($tab === 'schedule')
        @php $start = $this->weekStart(); $days = collect(range(0, 6))->map(fn ($i) => $start->addDays($i)); $shifts = $this->weekShifts; @endphp
        <section class="vxf-card" aria-labelledby="vxf-week">
            <div class="vxf-card__head" style="flex-wrap:wrap">
                <div style="display:flex;align-items:center;gap:8px">
                    <button type="button" class="vxf-icon-btn" wire:click="changeWeek(-1)" aria-label="Previous week"><x-heroicon-o-chevron-left /></button>
                    <h2 id="vxf-week" class="vxf-num">{{ $start->format('M j') }} – {{ $start->addDays(6)->format('M j, Y') }}</h2>
                    <button type="button" class="vxf-icon-btn" wire:click="changeWeek(1)" aria-label="Next week"><x-heroicon-o-chevron-right /></button>
                    <button type="button" class="vxf-btn vxf-btn--sm" wire:click="$set('week', null)">This week</button>
                </div>
                <div class="vxf-actions">
                    <div class="vxf-tabs" role="group" aria-label="Schedule view">
                        <button type="button" class="vxf-tab" aria-selected="{{ $scheduleView === 'week' ? 'true' : 'false' }}" wire:click="$set('scheduleView','week')">Week</button>
                        <button type="button" class="vxf-tab" aria-selected="{{ $scheduleView === 'list' ? 'true' : 'false' }}" wire:click="$set('scheduleView','list')">List</button>
                    </div>
                    @if ($manager)
                        <button type="button" class="vxf-btn" wire:click="copyPreviousWeek" wire:confirm="Copy last week's shifts into this week? Shifts that would overlap are skipped.">Copy last week</button>
                        <button type="button" class="vxf-btn vxf-btn--primary" wire:click="newShift"><x-heroicon-o-plus /> Add shift</button>
                    @endif
                </div>
            </div>

            @if ($scheduleView === 'week')
                <div class="vxf-table-wrap">
                    <table class="vxf-week">
                        <thead><tr><th scope="col" style="width:160px">Team member</th>
                            @foreach ($days as $day)<th scope="col" class="{{ $day->isToday() ? 'vxf-week__today' : '' }}">{{ $day->format('D') }} <span class="vxf-sub">{{ $day->format('M j') }}</span></th>@endforeach</tr></thead>
                        <tbody>
                            @forelse ($this->team as $member)
                                <tr wire:key="m-{{ $member->id }}">
                                    <th scope="row" style="text-align:left;padding:8px"><span style="display:flex;align-items:center;gap:8px"><span class="vxf-avatar" aria-hidden="true">{{ $initials($member->name) }}</span>{{ $member->name }}</span></th>
                                    @foreach ($days as $day)
                                        @php $cell = $shifts->filter(fn ($s) => $s->user_id === $member->id && $s->starts_at->timezone($tz)->isSameDay($day)); @endphp
                                        <td class="{{ $day->isToday() ? 'vxf-week__today' : '' }}">
                                            @foreach ($cell as $s)
                                                @php $tag = $manager ? 'button' : 'div'; @endphp
                                                <{{ $tag }} @if ($manager) type="button" wire:click="editShift({{ $s->id }})" @endif class="vxf-shift {{ $s->kind === 'night' ? 'vxf-shift--night' : '' }}">
                                                    <strong class="vxf-num">{{ $s->starts_at->timezone($tz)->format('g:ia') }}–{{ $s->ends_at->timezone($tz)->format('g:ia') }}{{ $s->crossesMidnight() ? ' +1' : '' }}</strong>
                                                    <span class="vxf-sub">{{ ucfirst($s->kind ?? 'day') }} · {{ $s->station?->name ?? 'No station' }}</span>
                                                    @foreach ($s->shows as $sh)<span class="vxf-shift__show" title="{{ $sh->title }}">{{ $sh->title }}</span>@endforeach
                                                    @if ($s->notes)<span class="vxf-sub">{{ $s->notes }}</span>@endif
                                                </{{ $tag }}>
                                            @endforeach
                                            @if ($cell->isEmpty())
                                                @if ($manager)<button type="button" class="vxf-off" style="width:100%;cursor:pointer;background:transparent" wire:click="newShift('{{ $day->toDateString() }}', {{ $member->id }})" aria-label="Add shift for {{ $member->name }} on {{ $day->format('D M j') }}">+</button>@else<span class="vxf-off">Off</span>@endif
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="8" class="vxf-empty">No one has a fulfillment role yet. Give people the Fulfillment or Fulfillment admin role to schedule them.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                @if ($shifts->isEmpty())<p class="vxf-empty">No shifts this week.</p>@else
                <ul class="vxf-list">
                    @foreach ($shifts as $s)
                        <li>
                            <span class="vxf-avatar" aria-hidden="true">{{ $initials($s->user?->name) }}</span>
                            <div style="flex:1;min-width:0"><strong>{{ $s->starts_at->timezone($tz)->format('D M j · g:i A') }} – {{ $s->ends_at->timezone($tz)->format('g:i A') }}{{ $s->crossesMidnight() ? ' (next day)' : '' }}</strong>
                                <span class="vxf-sub">{{ $s->user?->name }} · {{ ucfirst($s->kind ?? 'day') }} shift · {{ $s->station?->name ?? 'No station' }}</span>
                                @foreach ($s->shows as $sh)<span class="vxf-sub">Show: {{ $sh->title }} · {{ $sh->channel?->name }} · {{ $sh->streamers->pluck('name')->join(', ') }}</span>@endforeach
                                @if ($s->notes)<span class="vxf-sub">{{ $s->notes }}</span>@endif</div>
                            @if ($manager)<button type="button" class="vxf-btn vxf-btn--sm" wire:click="editShift({{ $s->id }})">Edit</button>@endif
                        </li>
                    @endforeach
                </ul>
                @endif
            @endif
        </section>

        @if ($manager)
            <section class="vxf-card" aria-labelledby="vxf-stations-config">
                <div class="vxf-card__head"><h2 id="vxf-stations-config">Stations</h2><span class="vxf-sub">Camera label helps find footage later</span></div>
                <div class="vxf-table-wrap">
                    <table class="vxf-table">
                        <thead><tr><th scope="col">Name</th><th scope="col">Camera</th><th scope="col">Active</th></tr></thead>
                        <tbody>
                            @foreach ($this->stationList as $st)
                                <tr wire:key="st-{{ $st->id }}">
                                    <td><label class="sr-only" for="st-name-{{ $st->id }}">Station name</label><input id="st-name-{{ $st->id }}" class="vxf-input" value="{{ $st->name }}" wire:change="updateStation({{ $st->id }}, 'name', $event.target.value)"></td>
                                    <td><label class="sr-only" for="st-cam-{{ $st->id }}">Camera label</label><input id="st-cam-{{ $st->id }}" class="vxf-input" value="{{ $st->camera_label }}" placeholder="e.g. Cam 3" wire:change="updateStation({{ $st->id }}, 'camera_label', $event.target.value)"></td>
                                    <td><label style="display:flex;gap:6px;align-items:center"><input type="checkbox" @checked($st->active) wire:change="updateStation({{ $st->id }}, 'active', $event.target.checked ? '1' : '0')"> Active</label></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <form class="vxf-card__body" style="display:flex;gap:8px;align-items:flex-start" wire:submit="addStation">
                    <label class="sr-only" for="vxf-new-station">New station name</label>
                    <input id="vxf-new-station" class="vxf-input" style="max-width:260px" placeholder="New station name" wire:model="newStation">
                    <button type="submit" class="vxf-btn">Add station</button>
                    @error('newStation')<span class="vxf-sub" style="color:#b91c1c">{{ $message }}</span>@enderror
                </form>
            </section>
        @endif

        @if ($shiftOpen)
            <div class="vxf-shift-modal-backdrop" wire:click="$set('shiftOpen', false)"></div>
            <section class="vxf-shift-modal" role="dialog" aria-modal="true" aria-labelledby="vxf-shift-title" @keydown.escape.window="$wire.set('shiftOpen', false)">
                <header class="vxf-shift-modal__header"><div><h2 id="vxf-shift-title">{{ $shiftId ? 'Edit Shift' : 'Add Shift' }}</h2><p class="vxf-sub">Create a shift for a team member.</p></div><button type="button" class="vxf-icon-btn" wire:click="$set('shiftOpen', false)" aria-label="Close"><x-heroicon-o-x-mark /></button></header>
                <form wire:submit.prevent="saveShift" class="vxf-shift-modal__form">
                    <div class="vxf-shift-modal__body">
                        <label class="vxf-field">Team Member * <select class="vxf-select" wire:model="shift.user_id" required><option value="">Choose team member…</option>@foreach ($this->team as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select></label>
                        <fieldset class="vxf-shift-types"><legend>Shift Type *</legend>
                            @foreach (['day' => ['Day Shift','8:00 AM – 4:00 PM'], 'night' => ['Night Shift','7:00 PM – 11:00 PM'], 'custom' => ['Custom Time','Choose your own hours']] as $type => $details)
                                <button type="button" class="vxf-shift-type {{ ($shift['selection'] ?? 'day') === $type ? 'is-active' : '' }}" wire:click="chooseShiftKind('{{ $type }}')" aria-pressed="{{ ($shift['selection'] ?? 'day') === $type ? 'true' : 'false' }}"><strong>{{ $details[0] }}</strong><span>{{ $details[1] }}</span></button>
                            @endforeach
                        </fieldset>
                        <div class="vxf-shift-time-row"><label class="vxf-field">Date * <input type="date" class="vxf-input" wire:model="shift.date" required></label><label class="vxf-field">Start Time * <input type="time" class="vxf-input" wire:model.live.debounce.350ms="shift.start" required></label><label class="vxf-field">End Time * <input type="time" class="vxf-input" wire:model.live.debounce.350ms="shift.end" required></label></div>
                        <p class="vxf-shift-duration" aria-live="polite">Shift Duration: {{ $this->shiftDuration() }}</p>
                        <div class="vxf-grid2">
                            <label class="vxf-field">Station (Optional)<select class="vxf-select" wire:model="shift.station_id"><option value="">No station assigned</option>@foreach ($this->stationList->where('active', true) as $st)<option value="{{ $st->id }}">{{ $st->name }}</option>@endforeach</select></label>
                            <div class="vxf-field vxf-shift-shows"><label for="vxf-show-search">Shows (Optional)</label><input id="vxf-show-search" type="search" class="vxf-input" placeholder="Search and select shows…" wire:model.live.debounce.300ms="showSearch" wire:focus="$set('showPickerOpen', true)" autocomplete="off">
                                @if ($showPickerOpen)<div class="vxf-shift-shows__options">
                                    @forelse (collect($this->assignableShows)->filter(fn ($label) => $showSearch === '' || str_contains(mb_strtolower($label), mb_strtolower($showSearch)))->take(15) as $id => $label)
                                        <button type="button" wire:click="toggleShiftShow({{ $id }})" title="{{ $label }}"><span>{{ $label }}</span><span>{{ in_array((string) $id, $shift['show_ids'] ?? [], true) ? '✓' : '+' }}</span></button>
                                    @empty <p class="vxf-sub">No matching shows.</p> @endforelse
                                    <button type="button" class="vxf-shift-shows__close" wire:click="$set('showPickerOpen', false)">Done selecting</button>
                                </div>@endif
                                <div class="vxf-shift-chips">@foreach ($shift['show_ids'] ?? [] as $id)<button type="button" wire:key="shift-show-{{ $id }}" wire:click="toggleShiftShow({{ (int) $id }})" title="Remove show">{{ $this->assignableShows[$id] ?? 'Show #' . $id }} ×</button>@endforeach</div>
                            </div>
                        </div>
                        @if (! $shiftId)
                            <label class="vxf-shift-repeat"><input type="checkbox" wire:model.live="repeatShift"> Repeat Shift (Optional)</label>
                            @if ($repeatShift)<fieldset class="vxf-shift-weekdays"><legend>Repeat on weekdays</legend>@foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $index => $name)<label><input type="checkbox" wire:model="repeatDays" value="{{ $index + 1 }}"> {{ $name }}</label>@endforeach</fieldset><label class="vxf-field">Repeat until <input type="date" class="vxf-input" wire:model="repeatUntil"></label>@endif
                        @endif
                        <details class="vxf-shift-notes"><summary>Notes (Optional)</summary><textarea class="vxf-input" rows="3" maxlength="255" wire:model="shift.notes" placeholder="Shift instructions…"></textarea></details>
                        @error('shift')<p class="vxf-alert" role="alert">{{ $message }}</p>@enderror
                        @error('shift.*')<p class="vxf-alert" role="alert">{{ $message }}</p>@enderror
                        @error('repeatDays')<p class="vxf-alert" role="alert">{{ $message }}</p>@enderror
                        @error('repeatUntil')<p class="vxf-alert" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <footer class="vxf-shift-modal__footer">@if ($shiftId)<button type="button" class="vxf-btn" style="margin-right:auto;color:#b91c1c" wire:click="deleteShift" wire:confirm="Remove this shift?">Delete</button>@endif<button type="button" class="vxf-btn" wire:click="$set('shiftOpen', false)">Cancel</button><button type="submit" class="vxf-btn vxf-btn--primary" wire:loading.attr="disabled" wire:target="saveShift">{{ $shiftId ? 'Save Shift' : 'Add Shift' }}</button></footer>
                </form>
            </section>
        @endif
    @endif

    {{-- ── Show logs ────────────────────────────────────────────────── --}}
    @if ($tab === 'shows')
        <section class="vxf-card" aria-labelledby="vxf-runs">
            <div class="vxf-card__head" style="flex-wrap:wrap">
                <h2 id="vxf-runs">Show logs</h2>
                <label class="vxf-field" style="flex-direction:row;align-items:center;gap:8px">Status
                    <select class="vxf-select" style="width:auto" wire:model.live="showsFilter">
                        <option value="all">All</option><option value="ready">Ready</option><option value="packing">Packing</option><option value="paused">Paused</option><option value="completed">Needs sign-off</option><option value="approved">Signed off</option>
                    </select></label>
            </div>
            <div class="vxf-table-wrap">
                <table class="vxf-table">
                    <thead><tr><th scope="col">Show</th><th scope="col">Station</th><th scope="col">Packers</th><th scope="col">Packed</th><th scope="col">Started → Finished</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Open</span></th></tr></thead>
                    <tbody>
                        @forelse ($this->runs as $run)
                            <tr wire:key="r-{{ $run->id }}" @class(["vxf-row--attention" => $run->status === "completed"])>
                                <td style="min-width:200px"><strong>{{ $run->show?->title }}</strong><span class="vxf-sub">{{ $run->show?->channel?->name }} · {{ $run->show?->show_date?->format('M j, Y') }}</span></td>
                                <td>{{ $run->station?->name ?? '—' }}</td>
                                <td>{{ $run->packers->pluck('name')->join(', ') ?: '—' }}</td>
                                @php $logPct = $run->expected_packages ? min(100, (int) round($run->completed_packages / max(1, $run->expected_packages) * 100)) : 0; @endphp
                                <td class="vxf-num" style="min-width:110px">{{ $run->completed_packages }}{!! $run->expected_packages !== null ? ' / ' . $run->expected_packages : ' <span class="vxf-unknown">/ ?</span>' !!}
                                    @if ($run->expected_packages)<div class="vxf-bar vxf-bar--mini" aria-hidden="true"><span style="width: {{ $logPct }}%"></span></div>@endif</td>
                                <td class="vxf-num" style="white-space:nowrap">{{ $run->started_at?->timezone($tz)->format('M j g:i A') ?? '—' }}<span class="vxf-sub">{{ $run->finished_at?->timezone($tz)->format('M j g:i A') ?? '' }}</span></td>
                                <td><span class="vxf-pill vxf-pill--{{ $pill($run->status) }}">{{ \App\Models\FulfillmentRun::statusLabel($run->status) }}</span>
                                    @if ($run->open_flags_count)<span class="vxf-pill vxf-pill--issue">{{ $run->open_flags_count }} issue</span>@endif</td>
                                <td style="text-align:right;white-space:nowrap">
                                    @if ($run->status === 'completed' && $manager)<button type="button" class="vxf-btn vxf-btn--sm vxf-btn--primary" wire:click="openSignoff({{ $run->id }})"><x-heroicon-o-check-badge /> Review &amp; sign off</button>
                                    @else<button type="button" class="vxf-btn vxf-btn--sm" wire:click="openLog({{ $run->id }})">Open log</button>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="vxf-empty">No show logs yet. A log is created as soon as a show is ready or assigned.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="vxf-card__body">{{ $this->runs->links() }}</div>
        </section>
    @endif

    {{-- ── Issues ───────────────────────────────────────────────────── --}}
    @if ($tab === 'issues')
        <section class="vxf-card" aria-labelledby="vxf-issue-list">
            <div class="vxf-card__head" style="flex-wrap:wrap">
                <h2 id="vxf-issue-list">Issues</h2>
                <div class="vxf-tabs" role="group" aria-label="Issue filter">
                    @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'all' => 'All'] as $k => $l)
                        <button type="button" class="vxf-tab" aria-selected="{{ $issueFilter === $k ? 'true' : 'false' }}" wire:click="$set('issueFilter','{{ $k }}')">{{ $l }}</button>
                    @endforeach
                </div>
            </div>
            <ul class="vxf-list">
                @forelse ($this->issues as $flag)
                    <li wire:key="f-{{ $flag->id }}" style="flex-wrap:wrap">
                        <span class="vxf-dot {{ $flag->status === 'open' ? 'vxf-dot--warn' : '' }}" aria-hidden="true">@if ($flag->status === 'open')<x-heroicon-o-exclamation-triangle />@else<x-heroicon-o-check />@endif</span>
                        <div style="flex:1;min-width:220px">
                            <strong>{{ \App\Services\FulfillmentFlagService::label($flag->type) }}</strong>@if ($flag->order_number) · Order {{ $flag->order_number }}@endif
                            @unless ($flag->blocking)<span class="vxf-pill" style="margin-left:4px">Not blocking</span>@endunless
                            <span class="vxf-sub">{{ $flag->show?->title }}@if ($flag->buyer) · {{ $flag->buyer->letter }} · {{ $flag->buyer->buyer_username }}@endif @if ($flag->station) · {{ $flag->station->name }}@endif</span>
                            <span class="vxf-sub" style="white-space:pre-line;color:var(--vxf-ink)">{{ $flag->details }}</span>
                            <span class="vxf-sub">Reported by {{ $flag->reporter?->name }} · {{ $flag->created_at?->timezone($tz)->format('M j, g:i A') }}</span>
                            @if ($flag->status === 'resolved')<span class="vxf-sub">Resolved by {{ $flag->resolver?->name }} · {{ $flag->resolution }}</span>@endif
                        </div>
                        @if ($manager && $flag->status === 'open')
                            <form wire:submit="resolveIssue({{ $flag->id }})" style="display:flex;gap:6px;flex:1 1 260px;min-width:220px">
                                <label class="sr-only" for="res-{{ $flag->id }}">Resolution</label>
                                <input id="res-{{ $flag->id }}" class="vxf-input" placeholder="How was it resolved?" wire:model="resolution.{{ $flag->id }}">
                                <button type="submit" class="vxf-btn vxf-btn--primary vxf-btn--sm">Resolve</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="vxf-empty" style="justify-content:center">No issues here.</li>
                @endforelse
            </ul>
            <div class="vxf-card__body">{{ $this->issues->links() }}</div>
        </section>
    @endif

    {{-- ── Productivity ─────────────────────────────────────────────── --}}
    @if ($tab === 'productivity')
        <section class="vxf-card" aria-labelledby="vxf-prod">
            <div class="vxf-card__head" style="flex-wrap:wrap">
                <h2 id="vxf-prod">Productivity</h2>
                <div class="vxf-tabs" role="group" aria-label="Period">
                    @foreach ([1 => 'Today', 7 => '7 days', 30 => '30 days'] as $d => $l)
                        <button type="button" class="vxf-tab" aria-selected="{{ $days === $d ? 'true' : 'false' }}" wire:click="$set('days', {{ $d }})">{{ $l }}</button>
                    @endforeach
                </div>
            </div>
            <div class="vxf-table-wrap">
                <table class="vxf-table">
                    <thead><tr><th scope="col">Team member</th><th scope="col">Packages</th><th scope="col">Buyers</th><th scope="col">Shows</th><th scope="col">Completed</th><th scope="col">Packing time</th><th scope="col">Verified items</th><th scope="col">Issues flagged</th></tr></thead>
                    <tbody>
                        @php $max = max(1, $this->productivity->max('packages') ?: $this->productivity->max('buyers')); @endphp
                        @php $idle = $this->productivity->filter(fn ($r) => ! $r['packages'] && ! $r['buyers'] && ! $r['shows']); @endphp
                        @forelse ($this->productivity->reject(fn ($r) => ! $r['packages'] && ! $r['buyers'] && ! $r['shows']) as $row)
                            <tr>
                                <th scope="row" style="text-align:left;padding:10px 12px"><span style="display:flex;gap:8px;align-items:center"><span class="vxf-avatar" aria-hidden="true">{{ $initials($row['user']->name) }}</span>{{ $row['user']->name }}</span></th>
                                <td class="vxf-num" style="min-width:120px"><strong>{{ $row['packages'] }}</strong><div class="vxf-bar" style="margin-top:4px;height:5px"><span style="width: {{ round(($row['packages'] ?: $row['buyers']) / $max * 100) }}%"></span></div></td>
                                <td class="vxf-num">{{ $row['buyers'] }}</td>
                                <td class="vxf-num">{{ $row['shows'] }}</td>
                                <td class="vxf-num">{{ $row['completed'] }}</td>
                                <td class="vxf-num">{{ $row['hours'] }} h</td>
                                <td class="vxf-num">{{ $row['verified'] }}</td>
                                <td class="vxf-num">{{ $row['flags'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="vxf-empty">No fulfillment team members yet.</td></tr>
                        @endforelse
                    @if ($idle->isNotEmpty())
                            <tr><td colspan="8" class="vxf-sub" style="padding:10px 12px">No packing yet for {{ $idle->count() }} {{ \Illuminate\Support\Str::plural('teammate', $idle->count()) }}: {{ $idle->map(fn ($r) => $r['user']->name)->join(', ') }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <p class="vxf-sub" style="margin:0;padding:10px 16px">Packages and buyers are credited to whoever marked them packed, so a shared show is never counted twice. Packing time is from start to finish, less pauses.</p>
        </section>
    @endif

    {{-- ── Footage lookup ───────────────────────────────────────────── --}}
    @if ($tab === 'lookup')
        <section class="vxf-card" aria-labelledby="vxf-lookup">
            <div class="vxf-card__head"><h2 id="vxf-lookup">Find footage for an order</h2></div>
            <div class="vxf-card__body">
                <label class="vxf-field">Order number or buyer username
                    <input type="search" class="vxf-input" wire:model.live.debounce.400ms="lookup" placeholder="e.g. 7452330 or cardcollector99" autofocus></label>
                <p class="vxf-sub" style="margin:6px 0 0">Shows the station, camera, buyer letter and exact times it was packed, so you can jump straight to the right moment in the recording.</p>
                @if (mb_strlen(trim($lookup)) < 2 && $this->recentBuyers->isNotEmpty())
                    <div class="vxf-recent">
                        <span class="vxf-sub">Recently packed:</span>
                        @foreach ($this->recentBuyers as $r)
                            <button type="button" class="vxf-chip vxf-chip--btn" wire:click="$set('lookup', @js($r->buyer_username))"><b>{{ $r->buyer_letter }}</b> {{ $r->buyer_username }}</button>
                        @endforeach
                    </div>
                @endif
            </div>
            @if (mb_strlen(trim($lookup)) >= 2)
                <div class="vxf-table-wrap">
                    <table class="vxf-table">
                        <thead><tr><th scope="col">Letter</th><th scope="col">Buyer</th><th scope="col">Show</th><th scope="col">Station · Camera</th><th scope="col">Packer</th><th scope="col">Started</th><th scope="col">Packed</th></tr></thead>
                        <tbody>
                            @forelse ($this->lookupResults as $b)
                                <tr>
                                    <td><span class="vxf-avatar" style="width:auto;min-width:30px;border-radius:8px;padding:0 6px;background:#7c3aed;color:#fff">{{ $b->letter }}</span></td>
                                    <td><strong>{{ $b->buyer_username }}</strong><span class="vxf-sub">{{ implode(', ', (array) $b->order_ids) }}</span></td>
                                    <td>{{ $b->run?->show?->title }}<span class="vxf-sub">{{ $b->run?->show?->show_date?->format('M j, Y') }}</span></td>
                                    <td>{{ $b->run?->station?->name ?? '—' }}<span class="vxf-sub">{{ $b->run?->station?->camera_label ?? 'No camera label' }}</span></td>
                                    <td>{{ $b->packer?->name ?? '—' }}</td>
                                    <td class="vxf-num" style="white-space:nowrap">{{ $b->packing_started_at?->timezone($tz)->format('M j g:i:s A') ?? '—' }}</td>
                                    <td class="vxf-num" style="white-space:nowrap">{{ $b->packed_at?->timezone($tz)->format('M j g:i:s A') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="vxf-empty">No packed buyer matches “{{ $lookup }}”.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    {{-- ── Show log drawer ──────────────────────────────────────────── --}}
    @if ($this->logRun)
        @php $run = $this->logRun; $d = $this->logData; @endphp
        <div class="vxf-drawer-backdrop" wire:click="closeLog"></div>
        <div class="vxf-drawer" style="width:min(820px,100vw)" role="dialog" aria-modal="true" aria-labelledby="vxf-log-title" @keydown.escape.window="$wire.closeLog()">
            <div class="vxf-drawer__head">
                <div style="min-width:0"><p class="vxf-sub" style="margin:0">Show log · digital tracking record</p><h2 id="vxf-log-title">{{ $run->show?->title }}</h2></div>
                <div style="display:flex;gap:6px;align-items:center">
                    <span class="vxf-pill vxf-pill--{{ $pill($run->status) }}">{{ \App\Models\FulfillmentRun::statusLabel($run->status) }}</span>
                    <button type="button" class="vxf-icon-btn" wire:click="closeLog" aria-label="Close"><x-heroicon-o-x-mark /></button>
                </div>
            </div>
            <div class="vxf-drawer__body">
                <div class="vxf-tabs" role="tablist" aria-label="Show log sections">
                    @foreach (['overview' => 'Overview', 'buyers' => 'Buyers', 'packages' => 'Packages', 'activity' => 'Activity', 'issues' => 'Issues'] as $k => $l)
                        <button type="button" role="tab" class="vxf-tab" aria-selected="{{ $logTab === $k ? 'true' : 'false' }}" wire:click="$set('logTab','{{ $k }}')">{{ $l }}@if ($k === 'issues' && $d['flags']->where('status','open')->count()) ({{ $d['flags']->where('status','open')->count() }})@endif</button>
                    @endforeach
                </div>

                @if ($logTab === 'overview')
                    <dl class="vxf-kv">
                        <div><dt>Stream date</dt><dd>{{ $run->show?->show_date?->format('M j, Y') }}{{ $run->show?->start_time ? ' · ' . $run->show->start_time->timezone($tz)->format('g:i A') : '' }}</dd></div>
                        <div><dt>Channel</dt><dd>{{ $run->show?->channel?->name ?? '—' }}</dd></div>
                        <div><dt>Streamer</dt><dd>{{ $run->show?->streamers->pluck('name')->join(', ') ?: '—' }}</dd></div>
                        <div><dt>Whatnot show</dt><dd>{{ $run->show?->whatnot_show_id ?: '—' }}</dd></div>
                        <div><dt>Station</dt><dd>{{ $run->station?->name ?? '—' }}{{ $run->station?->camera_label ? ' · ' . $run->station->camera_label : '' }}</dd></div>
                        <div><dt>Packers</dt><dd>{{ $run->packers->pluck('name')->join(', ') ?: '—' }}</dd></div>
                        <div><dt>Packing start</dt><dd>{{ $run->started_at?->timezone($tz)->format('M j · g:i A') ?? '—' }}</dd></div>
                        <div><dt>Packing complete</dt><dd>{{ $run->finished_at?->timezone($tz)->format('M j · g:i A') ?? '—' }}</dd></div>
                        <div><dt>Packing time</dt><dd>{{ $run->started_at ? gmdate('G\h i\m', $run->activeSeconds()) : '—' }}</dd></div>
                        <div><dt>Buyers</dt><dd>{{ $run->buyer_count ?? '—' }}</dd></div>
                        <div><dt>Orders</dt><dd>{{ $run->order_count ?? '—' }}</dd></div>
                        <div><dt>Packages</dt><dd>{{ $run->completed_packages }}{{ $run->expected_packages !== null ? ' / ' . $run->expected_packages : '' }}</dd></div>
                        <div><dt>PWE</dt><dd>{{ $run->reported_pwes ?? '—' }}</dd></div>
                        <div><dt>Items verified</dt><dd>{{ $d['verified'] }}{{ $d['rejected'] ? ' · ' . $d['rejected'] . ' rejected scans' : '' }}</dd></div>
                        <div><dt>Confirmed done</dt><dd>{{ $run->acknowledged_at ? $run->acknowledger?->name . ' · ' . $run->acknowledged_at->timezone($tz)->format('M j g:i A') : 'Not yet' }}</dd></div>
                        <div><dt>Final sign-off</dt><dd>{{ $run->approved_at ? $run->approver?->name . ' · ' . $run->approved_at->timezone($tz)->format('M j g:i A') : 'Not yet' }}</dd></div>
                    </dl>
                    @if ($d['contributions']->isNotEmpty())
                        <div class="vxf-card vxf-card__body"><strong>Who packed what</strong>
                            @foreach ($d['contributions'] as $c)<span class="vxf-sub">{{ $c['name'] }}: {{ $c['packages'] ?? $c['buyers'] }} {{ isset($c['packages']) ? 'packages' : 'buyers' }}</span>@endforeach</div>
                    @endif
                    @if ($run->discrepancies)
                        <div class="vxf-alert" role="note"><div><strong>Discrepancies</strong><ul style="margin:4px 0 0;padding-left:18px">@foreach ($run->discrepancies as $x)<li>{{ $x }}</li>@endforeach</ul></div></div>
                    @endif
                    @if ($run->approval_notes)<p class="vxf-sub">Sign-off note: {{ $run->approval_notes }}</p>@endif
                @endif

                @if ($logTab === 'buyers')
                    <div class="vxf-table-wrap"><table class="vxf-table">
                        <thead><tr><th scope="col">Letter</th><th scope="col">Buyer</th><th scope="col">Orders</th><th scope="col">Packages</th><th scope="col">Packed by</th><th scope="col">Started</th><th scope="col">Packed</th></tr></thead>
                        <tbody>@forelse ($d['buyers'] as $b)
                            <tr><td><strong class="vxf-mono">{{ $b->letter }}</strong></td><td>{{ $b->buyer_username }}<span class="vxf-sub">{{ ucfirst($b->status) }}</span></td>
                                <td class="vxf-num">{{ $b->order_count }}</td><td class="vxf-num">{{ $b->packages_count ? $b->packed_packages_count . '/' . $b->packages_count : '—' }}</td>
                                <td>{{ $b->packer?->name ?? '—' }}</td><td class="vxf-num">{{ $b->packing_started_at?->timezone($tz)->format('g:i:s A') ?? '—' }}</td><td class="vxf-num">{{ $b->packed_at?->timezone($tz)->format('g:i:s A') ?? '—' }}</td></tr>
                        @empty<tr><td colspan="7" class="vxf-empty">Buyers load when packing starts.</td></tr>@endforelse</tbody></table></div>
                @endif

                @if ($logTab === 'packages')
                    <div class="vxf-table-wrap"><table class="vxf-table">
                        <thead><tr><th scope="col">Buyer</th><th scope="col">Order</th><th scope="col">Tracking</th><th scope="col">Status</th><th scope="col">Packed by</th><th scope="col">Packed</th></tr></thead>
                        <tbody>@forelse ($d['packages'] as $p)
                            <tr><td><strong class="vxf-mono">{{ $p->buyer?->letter }}</strong> {{ $p->buyer?->buyer_username }}</td><td>{{ ! empty($p->order_ids) ? implode(', ', (array) $p->order_ids) : '—' }}</td>
                                <td>{{ $p->shipment?->tracking_number ? $p->shipment->carrier . ' ' . $p->shipment->tracking_number : '—' }}</td><td>{{ ucfirst($p->status) }}</td>
                                <td>{{ $p->packer?->name ?? '—' }}</td><td class="vxf-num">{{ $p->packed_at?->timezone($tz)->format('M j g:i:s A') ?? '—' }}</td></tr>
                        @empty<tr><td colspan="6" class="vxf-empty">No shipment records for this show, so packing is tracked per buyer.</td></tr>@endforelse</tbody></table></div>
                @endif

                @if ($logTab === 'activity')
                    <ol class="vxf-timeline">
                        @forelse ($d['events'] as $e)
                            <li><span class="vxf-timeline__mark {{ in_array($e->type, ['issue_flagged', 'scan_rejected', 'paused'], true) ? 'vxf-timeline__mark--warn' : (in_array($e->type, ['finished', 'approved', 'buyer_packed'], true) ? 'vxf-timeline__mark--ok' : '') }}" aria-hidden="true"></span>
                                <div><strong>{{ \App\Models\FulfillmentEvent::label($e->type) }}</strong>@if ($e->buyer_letter) · {{ $e->buyer_letter }} {{ $e->buyer_username }}@endif
                                    <span class="vxf-sub">{{ $e->user?->name ?? 'System' }}@if (is_array($e->data))@foreach (array_intersect_key($e->data, array_flip(['reason','resolution','signature','notes','note','barcode','count','range','buyers','packages'])) as $k => $v) · {{ is_array($v) ? implode(', ', $v) : $v }}@endforeach @endif</span></div>
                                <time class="vxf-num" datetime="{{ $e->created_at?->toIso8601String() }}">{{ $e->created_at?->timezone($tz)->format('M j g:i:s A') }}</time></li>
                        @empty
                            <li class="vxf-empty">No activity yet.</li>
                        @endforelse
                    </ol>
                @endif

                @if ($logTab === 'issues')
                    <ul class="vxf-list">
                        @forelse ($d['flags'] as $flag)
                            <li style="flex-wrap:wrap"><div style="flex:1;min-width:220px"><strong>{{ \App\Services\FulfillmentFlagService::label($flag->type) }}</strong> · {{ ucfirst($flag->status) }}
                                <span class="vxf-sub">{{ $flag->buyer ? $flag->buyer->letter . ' · ' . $flag->buyer->buyer_username : '' }} {{ $flag->details }}</span>
                                @if ($flag->resolution)<span class="vxf-sub">Resolved by {{ $flag->resolver?->name }}: {{ $flag->resolution }}</span>@endif</div>
                                @if ($manager && $flag->status === 'open')
                                    <form wire:submit="resolveIssue({{ $flag->id }})" style="display:flex;gap:6px;flex:1 1 240px"><label class="sr-only" for="lres-{{ $flag->id }}">Resolution</label>
                                        <input id="lres-{{ $flag->id }}" class="vxf-input" placeholder="Resolution" wire:model="resolution.{{ $flag->id }}"><button type="submit" class="vxf-btn vxf-btn--sm vxf-btn--primary">Resolve</button></form>
                                @endif</li>
                        @empty
                            <li class="vxf-empty">No issues on this show.</li>
                        @endforelse
                    </ul>
                @endif
            </div>
            <div class="vxf-drawer__foot" style="flex-wrap:wrap">
                <button type="button" class="vxf-btn" wire:click="exportRun"><x-heroicon-o-arrow-down-tray /> Export CSV</button>
                <a class="vxf-btn" href="{{ route('fulfillment.run.print', $run) }}" target="_blank" rel="noopener"><x-heroicon-o-printer /> Print</a>
                @if ($run->status === 'completed' && ! $run->acknowledged_at && $run->packers->contains('id', auth()->id()))
                    <button type="button" class="vxf-btn" wire:click="acknowledge">Confirm show is done</button>
                @endif
                @if ($manager && $run->status === 'completed')
                    <button type="button" class="vxf-btn vxf-btn--primary" wire:click="openSignoff({{ $run->id }})"><x-heroicon-o-check-badge /> Review &amp; sign off</button>
                @endif
            </div>
        </div>
    @endif
    @include('fulfillment.signoff-review')
</div>
</x-filament-panels::page>
