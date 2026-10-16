<x-filament-panels::page>
    @php
        $entries = $this->entries;
        $user = auth()->user();
        $seesTeam = ($user?->isOwner() || $user?->isAdmin()) ?? false;
        $team = $seesTeam && $tab === 'team' ? $this->teamHours : [];
        $tz = $this->userTimezone();
        $periodOptions = ['this_week' => 'This week', 'last_week' => 'Last week', 'this_month' => 'This month', 'custom' => 'Custom range'];
        $fmt = fn (?int $m) => $m === null ? '—' : intdiv($m, 60) . 'h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT) . 'm';
    @endphp

    <div class="vxw" style="grid-template-columns:minmax(0,1fr)" data-vx-page="timekeeping">
        @if (! $user?->timezone)
            <div class="vxw-alert vxw-alert--warn" x-data="{ busy: false }" style="align-items:center">
                <x-filament::icon icon="heroicon-m-globe-americas" />
                <span class="flex-1">Times are shown in {{ str_replace('_', ' ', $tz) }}. Set your own timezone so clock times match your watch.</span>
                <button type="button" class="vxw-btn vxw-btn--sm" :disabled="busy" @click="busy = true; $wire.saveDetectedTimezone(Intl.DateTimeFormat().resolvedOptions().timeZone)">Use this device's timezone</button>
            </div>
        @endif

        @livewire('clock-widget', ['withStats' => true])

        <x-vx.tip id="tk-missed">Forgot to clock in? Use <b>Add missed time</b> below — it gets a quick review before payroll.</x-vx.tip>

        @if ($seesTeam)
            @php $tiles = $this->adminTiles(); $h = intdiv($tiles['hours']['minutes'], 60).'h '.str_pad((string) ($tiles['hours']['minutes'] % 60), 2, '0', STR_PAD_LEFT).'m'; @endphp
            {{-- One place for admins: each card opens the list it counts. --}}
            <div class="vxr-stats" data-vx-timekeeping-tiles>
                <x-vxr.stat label="Needs review" :value="$tiles['review']['count']" icon="heroicon-o-clipboard-document-check" :tone="$tiles['review']['count'] ? 'warn' : 'ok'" :note="$tiles['review']['count'] ? 'Settle before payroll' : 'Nothing waiting'" :href="$tiles['review']['url']" />
                <x-vxr.stat label="Clocked in now" :value="$tiles['now']['count']" icon="heroicon-o-play-circle" tone="ok" note="Who is on the clock" :href="$tiles['now']['url']" />
                <x-vxr.stat label="Team hours this week" :value="$h" icon="heroicon-o-users" tone="info" :note="$tiles['hours']['people'].' '.\Illuminate\Support\Str::plural('person', $tiles['hours']['people']).($tiles['hours']['report_minutes'] ? ' · + '.$fmt($tiles['hours']['report_minutes']).' on show reports' : '')" :href="$tiles['hours']['url']" />
                <x-vxr.stat label="Entries this week" :value="$tiles['entries']['count']" icon="heroicon-o-list-bullet" tone="accent" note="Every entry, editable" :href="$tiles['entries']['url']" />
            </div>
        @endif

        <section class="vxw-card">
            <div class="vxw-card-head" style="flex-wrap:wrap;gap:12px">
                <div class="vxw-chips" role="tablist" aria-label="Timekeeping views">
                    <button type="button" role="tab" wire:click="setTab('history')" @class(['vxw-chip', 'is-active' => $tab === 'history'])>{{ $seesTeam ? 'All entries' : 'My history' }} <span class="vxw-chip-count">{{ number_format($this->entryTotal) }}</span></button>
                    @if ($seesTeam)
                        <button type="button" role="tab" wire:click="setTab('team')" @class(['vxw-chip', 'is-active' => $tab === 'team'])>Team hours</button>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($tab === 'history')
                        {{ $this->addTimeAction }}
                        <button type="button" wire:click="exportCsv" class="vxw-btn vxw-btn--sm"><x-filament::icon icon="heroicon-m-arrow-down-tray" /> Export CSV</button>
                    @elseif (count($team))
                        <button type="button" wire:click="exportTeamHoursCsv" class="vxw-btn vxw-btn--sm"><x-filament::icon icon="heroicon-m-arrow-down-tray" /> Export CSV</button>
                    @endif
                </div>
            </div>

            {{-- The period applies to both views: this week by default, older weeks a tap away. --}}
            <div class="vxw-card-body" style="display:grid;gap:12px;padding-top:0">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($periodOptions as $mode => $label)
                        <button type="button" wire:click="setPeriodMode('{{ $mode }}')" @class(['vxw-chip', 'is-active' => $periodMode === $mode])>{{ $label }}</button>
                    @endforeach
                    <span class="vxw-meta ml-auto">{{ $this->periodLabel }}</span>
                </div>
                @if ($periodMode === 'custom')
                    <div class="flex flex-wrap items-center gap-2">
                        <input wire:model.live="periodFrom" type="date" class="vxw-input" style="width:auto" aria-label="From">
                        <span class="vxw-meta">to</span>
                        <input wire:model.live="periodTo" type="date" class="vxw-input" style="width:auto" aria-label="To">
                    </div>
                @endif
            </div>

            @if ($tab === 'history')
                @if ($entries->isEmpty())
                    <div class="vxw-empty">
                        <x-filament::icon icon="heroicon-o-clock" />
                        <div class="vxw-empty-title">No time in this period</div>
                        <div class="vxw-empty-text">Clocked time and hours from show reports show up here. Try an earlier period, clock in above, or add time you forgot to clock.</div>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" style="min-width:860px">
                            <thead>
                                <tr class="text-left text-[11px] font-bold uppercase tracking-wide" style="color:var(--vxw-muted)">
                                    @if ($seesTeam)<th class="px-4 py-2.5">Person</th>@endif
                                    <th class="px-4 py-2.5">Date</th><th class="px-4 py-2.5">Clock in</th><th class="px-4 py-2.5">Clock out</th><th class="px-4 py-2.5 text-right">Duration</th>
                                    <th class="px-4 py-2.5">Show or reason</th><th class="px-4 py-2.5">Status</th><th class="px-4 py-2.5 text-right"></th>
                                </tr>
                            </thead>
                            {{-- One line per person; their clocked time and show reports open underneath. --}}
                            @foreach ($entries as $person)
                            <tbody x-data="{ open: {{ ! $seesTeam || $entries->count() === 1 ? 'true' : 'false' }} }" wire:key="person-{{ $person['key'] }}" data-vx-person>
                                <tr class="cursor-pointer" style="border-top:1px solid var(--vxw-border);background:var(--vxw-sunken)" @click="open = ! open">
                                    <td class="px-4 py-3 font-semibold" colspan="{{ $seesTeam ? 4 : 3 }}">
                                        <span class="inline-flex items-center gap-2">
                                            <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 transition-transform" x-bind:style="open ? 'transform:rotate(90deg)' : ''" />
                                            {{ $person['name'] }}
                                            @if ($person['needs_review'])<span class="vxw-pill vxw-pill--warn">Needs review</span>@endif
                                        </span>
                                        <div class="vxw-meta mt-0.5" style="padding-left:1.5rem">
                                            {{ collect([$person['clock_count'] ? $person['clock_count'].' clocked '.\Illuminate\Support\Str::plural('entry', $person['clock_count']) : null, $person['report_count'] ? $person['report_count'].' show '.\Illuminate\Support\Str::plural('report', $person['report_count']) : null, $person['shows'] ? $person['shows'].' '.\Illuminate\Support\Str::plural('show', $person['shows']) : null])->filter()->implode(' · ') }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ $fmt($person['clock_minutes'] + $person['report_minutes']) }}</td>
                                    <td class="px-4 py-3 vxw-meta" colspan="3">
                                        @if ($person['clock_count'])Clocked {{ $fmt($person['clock_minutes']) }}@endif
                                        @if ($person['clock_count'] && $person['report_count']) · @endif
                                        @if ($person['report_count'])From show reports {{ $fmt($person['report_minutes']) }}@endif
                                    </td>
                                </tr>
                                @foreach ($person['rows'] as $row)
                                    @if ($r = $row['report'])
                                        {{-- Hours written on a show report: shown alongside clocked time, edited on the report. --}}
                                        @php $rm = (int) round((float) $r->hours_streamed * 60); @endphp
                                        <tr wire:key="rep-{{ $r->id }}" style="border-top:1px solid var(--vxw-border)" data-vx-report-hours x-show="open" x-cloak>
                                            @if ($seesTeam)<td class="px-4 py-3"></td>@endif
                                            <td class="px-4 py-3 whitespace-nowrap">{{ $r->vxWorkedAt->format('D, M j') }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap tabular-nums vxw-meta" colspan="2">From the show report</td>
                                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $fmt($rm) }}</td>
                                            <td class="px-4 py-3" style="max-width:320px">
                                                <div class="flex items-center gap-1.5"><x-filament::icon icon="heroicon-m-video-camera" class="h-4 w-4 shrink-0" style="color:#7c3aed" /><span class="truncate">{{ $r->show?->title ?? 'Show #'.$r->show_id }}</span></div>
                                            </td>
                                            <td class="px-4 py-3"><span class="vxw-pill">Show report</span></td>
                                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                                @if ($seesTeam)<a class="vxw-btn vxw-btn--sm" href="{{ \App\Filament\Resources\StreamerLogResource::getUrl('edit', ['record' => $r->id]) }}" wire:navigate>Open report</a>@endif
                                            </td>
                                        </tr>
                                        @continue
                                    @endif
                                    @php $e = $row['clock']; $mins = $e->duration_minutes; @endphp
                                    <tr wire:key="te-{{ $e->id }}" style="border-top:1px solid var(--vxw-border)" x-show="open" x-cloak>
                                        @if ($seesTeam)<td class="px-4 py-3"></td>@endif
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $e->clocked_in_at->copy()->setTimezone($tz)->format('D, M j') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap tabular-nums">{{ $this->formatTimeInUserTz($e->clocked_in_at) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap tabular-nums">
                                            @if ($e->clocked_out_at){{ $this->formatTimeInUserTz($e->clocked_out_at) }}@if(! $e->clocked_out_at->copy()->setTimezone($tz)->isSameDay($e->clocked_in_at->copy()->setTimezone($tz)))<span class="vxw-meta"> +1d</span>@endif
                                            @else<span class="vxw-pill vxw-pill--ok vxw-pill--dot">Active</span>@endif
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $fmt($mins) }}</td>
                                        <td class="px-4 py-3" style="max-width:320px">
                                            @if ($e->show_id)
                                                <div class="flex items-center gap-1.5"><x-filament::icon icon="heroicon-m-video-camera" class="h-4 w-4 shrink-0" style="color:#7c3aed" /><span class="truncate">{{ $e->show?->title ?? 'Show #'.$e->show_id }}</span></div>
                                            @elseif ($e->reason)
                                                <span class="vxw-pill">{{ \App\Models\TimeEntry::REASONS[$e->reason] ?? $e->reason }}</span>
                                            @else
                                                <span class="vxw-meta">Not set yet</span>
                                            @endif
                                            @if ($e->notes)<div class="vxw-meta mt-0.5 truncate">{{ $e->notes }}</div>@endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($e->status === 'flagged')
                                                <span class="vxw-pill vxw-pill--warn" title="{{ implode(', ', $e->flagLabels()) }}">Needs review</span>
                                                <div class="vxw-meta mt-0.5 text-xs">{{ implode(', ', $e->flagLabels()) }}</div>
                                            @elseif ($e->status === 'approved')
                                                <span class="vxw-pill vxw-pill--ok">Approved{{ $e->approved_minutes !== null && $e->approved_minutes !== $mins ? ' · ' . $fmt((int) $e->approved_minutes) : '' }}</span>
                                            @elseif ($e->status === 'rejected')
                                                <span class="vxw-pill vxw-pill--bad">Not paid</span>
                                            @else
                                                <span class="vxw-meta">{{ $e->source === 'manual' ? 'Added by hand' : 'OK' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">@if ($this->canEdit($e)){{ ($this->editEntryAction)(['entry' => $e->id]) }}@endif @if ($seesTeam)<span class="ml-2">{{ ($this->deleteEntryAction)(['entry' => $e->id]) }}</span>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            @endforeach
                        </table>
                    </div>
                    @if ($entries->lastPage() > 1)
                        <div class="flex items-center justify-between px-4 py-3" style="border-top:1px solid var(--vxw-border)">
                            <span class="vxw-meta">Page {{ $entries->currentPage() }} of {{ $entries->lastPage() }}</span>
                            <div class="flex gap-2">
                                <button type="button" wire:click="prevPage" class="vxw-btn vxw-btn--sm" @disabled($entries->currentPage() === 1)>Previous</button>
                                <button type="button" wire:click="nextPage" class="vxw-btn vxw-btn--sm" @disabled($entries->currentPage() === $entries->lastPage())>Next</button>
                            </div>
                        </div>
                    @endif
                @endif
            @else
                @if (count($team) === 0)
                    <div class="vxw-empty"><div class="vxw-empty-title">No hours for this period</div><div class="vxw-empty-text">Clocked time and hours from show reports appear here. Try an earlier period.</div></div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" style="min-width:520px">
                            <thead><tr class="text-left text-[11px] font-bold uppercase tracking-wide" style="color:var(--vxw-muted)"><th class="px-4 py-2.5">Person</th><th class="px-4 py-2.5 text-right">Clocked</th><th class="px-4 py-2.5 text-right">From show reports</th><th class="px-4 py-2.5 text-right">Total</th><th class="px-4 py-2.5" style="width:28%"></th></tr></thead>
                            <tbody>
                                @foreach ($team as $member)
                                    @php $pct = $team[0]['total'] > 0 ? round(($member['total'] / $team[0]['total']) * 100) : 0; @endphp
                                    <tr style="border-top:1px solid var(--vxw-border)">
                                        <td class="px-4 py-3 font-medium">{{ $member['name'] }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $member['entries'] ? $fmt($member['minutes']) : '—' }}@if ($member['entries'])<div class="vxw-meta">{{ $member['entries'] }} {{ \Illuminate\Support\Str::plural('entry', $member['entries']) }}</div>@endif</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $member['reports'] ? $fmt($member['report_minutes']) : '—' }}@if ($member['reports'])<div class="vxw-meta">{{ $member['reports'] }} {{ \Illuminate\Support\Str::plural('show', $member['reports']) }}</div>@endif</td>
                                        <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $fmt($member['total']) }}</td>
                                        <td class="px-4 py-3"><div style="height:8px;border-radius:4px;background:var(--vxw-sunken)"><div style="height:8px;border-radius:4px;width:{{ $pct }}%;background:#7c3aed"></div></div></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </section>
    </div>
</x-filament-panels::page>
