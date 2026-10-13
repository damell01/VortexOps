<x-filament-panels::page>
    @php
        $entries = $this->entries;
        $user = auth()->user();
        $seesTeam = ($user?->isOwner() || $user?->isAdmin()) ?? false;
        $team = $seesTeam && $tab === 'team' ? $this->teamHours : [];
        $tz = $this->userTimezone();
        $periodOptions = ['this_week' => 'This week', 'last_week' => 'Last week', 'pay_period' => 'Current pay period', 'this_month' => 'This month', 'custom' => 'Custom range'];
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

        @if ($seesTeam && \App\Filament\Pages\TimeAttendance::canAccess())
            @php $review = \App\Filament\Pages\TimeAttendance::reviewCount(); @endphp
            <a href="{{ \App\Filament\Pages\TimeAttendance::getUrl() }}" class="vxw-alert {{ $review ? 'vxw-alert--warn' : 'vxw-alert--info' }}" style="align-items:center;text-decoration:none">
                <x-filament::icon icon="heroicon-m-clipboard-document-check" />
                <span class="flex-1"><b>Time &amp; Attendance</b> — {{ $review ? $review . ' ' . \Illuminate\Support\Str::plural('item', $review) . ($review === 1 ? ' needs' : ' need') . ' review before payroll' : 'nothing waiting for review' }}</span>
                <x-filament::icon icon="heroicon-m-arrow-right" />
            </a>
        @endif

        <section class="vxw-card">
            <div class="vxw-card-head" style="flex-wrap:wrap;gap:12px">
                <div class="vxw-chips" role="tablist" aria-label="Timekeeping views">
                    <button type="button" role="tab" wire:click="setTab('history')" @class(['vxw-chip', 'is-active' => $tab === 'history'])>{{ $seesTeam ? 'All entries' : 'My history' }} <span class="vxw-chip-count">{{ number_format($entries->total()) }}</span></button>
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

            @if ($tab === 'history')
                @if ($entries->isEmpty())
                    <div class="vxw-empty">
                        <x-filament::icon icon="heroicon-o-clock" />
                        <div class="vxw-empty-title">No time yet</div>
                        <div class="vxw-empty-text">Clock in above, or add time you forgot to clock.</div>
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
                            <tbody>
                                @foreach ($entries as $e)
                                    @php $mins = $e->duration_minutes; @endphp
                                    <tr wire:key="te-{{ $e->id }}" style="border-top:1px solid var(--vxw-border)">
                                        @if ($seesTeam)<td class="px-4 py-3 font-medium">{{ $e->user?->name ?? '—' }}</td>@endif
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
                                        <td class="px-4 py-3 text-right">@if ($this->canEdit($e)){{ ($this->editEntryAction)(['entry' => $e->id]) }}@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
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
                <div class="vxw-card-body" style="display:grid;gap:12px">
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
                @if (count($team) === 0)
                    <div class="vxw-empty"><div class="vxw-empty-title">No hours logged by the team for this period</div><div class="vxw-empty-text">Hourly team members' clocked time appears here.</div></div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" style="min-width:520px">
                            <thead><tr class="text-left text-[11px] font-bold uppercase tracking-wide" style="color:var(--vxw-muted)"><th class="px-4 py-2.5">Person</th><th class="px-4 py-2.5 text-right">Entries</th><th class="px-4 py-2.5 text-right">Hours</th><th class="px-4 py-2.5" style="width:35%"></th></tr></thead>
                            <tbody>
                                @foreach ($team as $member)
                                    @php $pct = $team[0]['minutes'] > 0 ? round(($member['minutes'] / $team[0]['minutes']) * 100) : 0; @endphp
                                    <tr style="border-top:1px solid var(--vxw-border)">
                                        <td class="px-4 py-3 font-medium">{{ $member['name'] }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $member['entries'] }}</td>
                                        <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $fmt($member['minutes']) }}</td>
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
