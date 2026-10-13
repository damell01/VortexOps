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
    <x-dashboard-shortcuts />
    <div class="vxw" style="grid-template-columns:minmax(0,1fr)" data-vx-page="streamer-shows" x-data="{ tab: @js($defaultTab), sort: 'newest' }">
        @if (\App\Support\AdminModules::isEnabled('timekeeping'))
            @livewire('clock-widget')
        @endif

        @php [$pFrom, $pTo, $pLabel] = $this->periodRange(); $n = $this->numbers(); @endphp
        <section class="vxw-card" data-vx="my-numbers" style="min-width:0">
            <div class="vxw-card-head">
                <div>
                    <h3 class="vxw-h2">Your numbers · {{ $pLabel }}</h3>
                    <p class="vxw-sub">Across the shows you hosted. Pay waiting on a pay run is an estimate until payroll finalizes it.</p>
                </div>
                <div class="vxw-chips" role="tablist" aria-label="Period">
                    @foreach (['month' => 'This month', 'last_month' => 'Last month', 'year' => 'This year', 'last_year' => 'Last year'] as $key => $label)
                        <button type="button" role="tab" wire:click="setPeriod('{{ $key }}')" @class(['vxw-chip', 'is-active' => $period === $key]) aria-selected="{{ $period === $key ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <div class="vxw-stats" style="grid-template-columns:repeat(auto-fit,minmax(min(130px,100%),1fr))">
                <div class="vxw-stat"><div class="vxw-stat-label">Shows</div><div class="vxw-stat-value">{{ number_format($n['shows']) }}</div></div>
                <div class="vxw-stat"><div class="vxw-stat-label">Hours worked</div><div class="vxw-stat-value">{{ rtrim(rtrim(number_format($n['hours'], 2), '0'), '.') ?: '0' }}</div></div>
                <div class="vxw-stat"><div class="vxw-stat-label">Gross sales</div><div class="vxw-stat-value">${{ number_format($n['gross'], 0) }}</div></div>
                <div class="vxw-stat"><div class="vxw-stat-label">Units sold</div><div class="vxw-stat-value">{{ number_format($n['units']) }}</div></div>
                <div class="vxw-stat"><div class="vxw-stat-label">Paid</div><div class="vxw-stat-value">${{ number_format($n['paid'], 2) }}</div></div>
                <div class="vxw-stat"><div class="vxw-stat-label">Awaiting payment</div><div class="vxw-stat-value">${{ number_format($n['awaiting'], 2) }}</div></div>
            </div>
        </section>

        {{-- What needs doing, in one line, with the one button that does it. --}}
        <section class="vxw-card">
            <div class="vxw-card-body">
                <div class="vxw-hero">
                    <div>
                        <div class="vxw-eyebrow">Your shows</div>
                        @if($summary['needs_you'] > 0)
                            <h2 class="vxw-h1">{{ $summary['needs_you'] }} {{ \Illuminate\Support\Str::plural('show', $summary['needs_you']) }} waiting on you</h2>
                            <p class="vxw-sub">Log the items you used so your report can be approved and paid.</p>
                        @else
                            <h2 class="vxw-h1">You're all caught up</h2>
                            <p class="vxw-sub">Every show you've streamed has a report. New shows appear here after they air.</p>
                        @endif
                    </div>
                    <div class="vxw-hero-actions">
                        @if($calendarUrl)
                            <a class="vxw-btn" href="{{ $calendarUrl }}">
                                <x-filament::icon icon="heroicon-m-calendar-days" /> Calendar
                            </a>
                        @endif
                        @if($nextUp && $nextUp['url'])
                            <a class="vxw-btn vxw-btn--primary" href="{{ $nextUp['url'] }}">
                                Log next show
                                <x-filament::icon icon="heroicon-m-arrow-right" />
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="vxw-chips" role="tablist" aria-label="Filter your shows">
                @foreach($tabs as [$key, $label, $count])
                    <button type="button" role="tab" class="vxw-chip" :class="tab==='{{ $key }}' && 'is-active'" :aria-selected="tab==='{{ $key }}'" @click="tab='{{ $key }}'">
                        {{ $label }} <span class="vxw-chip-count">{{ $count }}</span>
                    </button>
                @endforeach
            </div>
            <label class="flex items-center gap-2">
                <span class="sr-only">Sort shows</span>
                <select x-model="sort" class="vxw-select" style="min-height:36px;width:auto;padding-block:0">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                </select>
            </label>
        </div>

        <section id="show-grid" class="vxw-tiles" aria-live="polite">
            @foreach ($allShows as $show)
                <article wire:key="streamer-show-{{ $show['id'] }}"
                    x-show="tab==='all' || tab==='{{ $show['bucket'] }}'" x-cloak
                    :style="{ order: sort==='newest' ? {{ -$show['day'] }} : {{ $show['day'] }} }"
                    @class(['vxw-tile', 'vxw-tile--attn' => $show['bucket'] === 'needs_you'])
                    style="cursor:default">
                    <div class="flex items-start justify-between gap-3">
                        <span class="vxw-pill vxw-pill--dot {{ ($tones[$show['tone']] ?? '') ? 'vxw-pill--' . $tones[$show['tone']] : '' }}">{{ $show['state'] }}</span>
                        <span class="vxw-meta whitespace-nowrap">
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

