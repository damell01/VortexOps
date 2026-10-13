<x-filament-panels::page>
@php
    $tz = $this->tz();
    $fmt = fn ($m) => $m === null ? '—' : \App\Models\TimeEntry::formatMinutes((int) $m);
    $items = $tab === 'review' ? $this->reviewItems() : collect();
    $panel = $this->panel();
    $tabs = [
        'review' => ['Needs review', $tab === 'review' ? $items->count() : \App\Filament\Pages\TimeAttendance::reviewCount()],
        'people' => ['By person', null],
        'now' => ['Clocked in now', $this->openEntries()->count()],
        'all' => ['All entries', null],
    ];
@endphp
<div class="vxw" data-vx-page="time-attendance">
    <div class="vxw-chips" role="tablist" aria-label="Time & Attendance views">
        @foreach ($tabs as $key => [$label, $count])
            <button type="button" role="tab" wire:click="setTab('{{ $key }}')" @class(['vxw-chip', 'is-active' => $tab === $key]) aria-selected="{{ $tab === $key ? 'true' : 'false' }}">
                {{ $label }} @if($count !== null)<span class="vxw-chip-count">{{ $count }}</span>@endif
            </button>
        @endforeach
    </div>

    @if ($tab === 'review')
        <section class="vxw-card">
            @if ($items->isEmpty())
                <div class="vxw-empty">
                    <x-filament::icon icon="heroicon-o-check-badge" />
                    <div class="vxw-empty-title">Nothing waiting for review</div>
                    <div class="vxw-empty-text">Edited, hand-added, overlapping and forgotten clock-outs land here, with shows whose report hours are missing or differ from the clock by more than {{ \App\Services\TimeTrackingService::MISMATCH_MINUTES }} minutes.</div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" style="min-width:760px">
                        <thead>
                            <tr class="text-left text-[11px] font-bold uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-2.5">Person</th><th class="px-4 py-2.5">Date</th><th class="px-4 py-2.5">Show or reason</th>
                                <th class="px-4 py-2.5">Issue</th><th class="px-4 py-2.5 text-right">Clock</th><th class="px-4 py-2.5 text-right">Report</th><th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($items as $item)
                                <tr wire:key="review-{{ $item['key'] }}" @class(['cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5', 'bg-primary-50/60 dark:bg-primary-500/10' => $reviewing === $item['key']]) wire:click="openPanel('{{ $item['key'] }}')">
                                    <td class="px-4 py-3 font-medium">{{ $item['person'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $item['when'] ? ($item['key'][0] === 'e' ? $item['when']->copy()->setTimezone($tz)->format('D M j') : $item['when']->format('D M j')) : '—' }}</td>
                                    <td class="px-4 py-3">{{ \Illuminate\Support\Str::limit($item['what'], 60) }}</td>
                                    <td class="px-4 py-3"><span class="vxw-pill vxw-pill--{{ $item['tone'] }}">{{ $item['issue'] }}</span></td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $fmt($item['clock']) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $item['key'][0] === 'r' ? $fmt($item['report']) : '' }}</td>
                                    <td class="px-4 py-3 text-right"><span class="vxw-link text-xs font-semibold">Review →</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    @if ($tab === 'people')
        @php [$from, $to] = $this->weekRange(); $people = $this->people(); @endphp
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="shiftWeek(-1)" class="vxw-btn vxw-btn--sm" aria-label="Previous week"><x-filament::icon icon="heroicon-m-chevron-left" /></button>
            <div class="vxw-h2" style="min-width:12rem;text-align:center">{{ $from->format('M j') }} – {{ $to->format('M j, Y') }}</div>
            <button type="button" wire:click="shiftWeek(1)" class="vxw-btn vxw-btn--sm" aria-label="Next week"><x-filament::icon icon="heroicon-m-chevron-right" /></button>
            <button type="button" wire:click="$set('week', '')" class="vxw-btn vxw-btn--ghost vxw-btn--sm">This week</button>
        </div>
        @forelse ($people as $row)
            @php $s = $row['summary']; @endphp
            <section class="vxw-card" x-data="{ open: false }" wire:key="person-{{ $row['user']->id }}-{{ $from->toDateString() }}">
                <button type="button" class="vxw-card-body flex w-full flex-wrap items-center gap-4 text-left" @click="open = !open" :aria-expanded="open">
                    <div class="min-w-0 flex-1"><div class="vxw-h2">{{ $row['user']->name }}</div>@if($s['flagged'])<div class="vxw-meta" style="color:var(--vxw-warn)">{{ $s['flagged'] }} flagged {{ \Illuminate\Support\Str::plural('entry', $s['flagged']) }}</div>@endif</div>
                    <div class="text-right"><div class="vxw-meta">Clocked</div><div class="font-semibold tabular-nums">{{ $fmt($s['clock']) }}</div></div>
                    <div class="text-right"><div class="vxw-meta">Show reports</div><div class="font-semibold tabular-nums">{{ $fmt($s['report']) }}</div></div>
                    <div class="text-right"><div class="vxw-meta">Approved to pay</div><div class="font-bold tabular-nums" style="color:var(--vxw-ok, #047857)">{{ $fmt($s['approved']) }}</div></div>
                    <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 transition" x-bind:style="open && 'transform:rotate(180deg)'" />
                </button>
                <div x-show="open" x-cloak class="border-t border-gray-100 dark:border-gray-800">
                    @forelse ($s['days'] as $day => $d)
                        <div class="px-5 py-3">
                            <div class="flex justify-between text-sm font-semibold"><span>{{ \Illuminate\Support\Carbon::parse($day)->format('l, M j') }}</span><span class="tabular-nums">{{ $fmt($d['clock']) }}</span></div>
                            @foreach ($d['entries'] as $e)
                                <div class="mt-1 flex flex-wrap gap-x-3 text-xs text-gray-500">
                                    <span class="tabular-nums">{{ $e->clocked_in_at->copy()->setTimezone($tz)->format('g:i A') }} – {{ $e->clocked_out_at ? $e->clocked_out_at->copy()->setTimezone($tz)->format('g:i A') : 'open' }}</span>
                                    <span>{{ $e->purposeLabel() }}</span>
                                    @if ($e->status !== 'ok')<span class="vxw-pill vxw-pill--{{ $e->status === 'flagged' ? 'warn' : ($e->status === 'approved' ? 'ok' : 'bad') }}" style="padding:0 6px">{{ ucfirst($e->status) }}</span>@endif
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="px-5 py-3 text-sm text-gray-500">No clocked time this week — hours come from show reports only.</div>
                    @endforelse
                </div>
            </section>
        @empty
            <section class="vxw-card"><div class="vxw-empty"><div class="vxw-empty-title">No hours this week</div></div></section>
        @endforelse
        <p class="vxw-hint">Approved to pay = show report hours (as settled) plus clocked time not on a show that is fine or approved. Clock and report hours on the same show are compared, never added.</p>
    @endif

    @if ($tab === 'now')
        <section class="vxw-card">
            @php $open = $this->openEntries(); @endphp
            @forelse ($open as $e)
                @php $mins = (int) $e->clocked_in_at->diffInMinutes(now()); @endphp
                <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-5 py-3 last:border-0 dark:border-gray-800">
                    <span class="h-2 w-2 rounded-full {{ $mins >= 720 ? 'bg-red-500' : 'bg-emerald-500' }}"></span>
                    <span class="font-medium">{{ $e->user?->name }}</span>
                    <span class="text-sm text-gray-500">since {{ $e->clocked_in_at->copy()->setTimezone($tz)->format('D g:i A') }} · {{ $e->purposeLabel() }}</span>
                    <span class="ml-auto font-semibold tabular-nums {{ $mins >= 720 ? 'text-red-600' : '' }}">{{ $fmt($mins) }}@if($mins >= 720) · missed clock-out?@endif</span>
                </div>
            @empty
                <div class="vxw-empty"><div class="vxw-empty-title">Nobody is clocked in</div></div>
            @endforelse
        </section>
    @endif

    @if ($tab === 'all')
        @php $all = $this->allEntries(); @endphp
        <div class="flex flex-wrap gap-3">
            <select wire:model.live="person" class="vxw-select" style="width:auto;min-width:12rem" aria-label="Person"><option value="">Everyone</option>@foreach ($this->peopleOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
            <select wire:model.live="status" class="vxw-select" style="width:auto" aria-label="Status"><option value="">Any status</option><option value="ok">OK</option><option value="flagged">Needs review</option><option value="approved">Approved</option><option value="rejected">Not paid</option></select>
        </div>
        <section class="vxw-card overflow-x-auto">
            <table class="w-full text-sm" style="min-width:720px">
                <thead><tr class="text-left text-[11px] font-bold uppercase tracking-wide text-gray-500"><th class="px-4 py-2.5">Person</th><th class="px-4 py-2.5">In</th><th class="px-4 py-2.5">Out</th><th class="px-4 py-2.5 text-right">Time</th><th class="px-4 py-2.5">Show or reason</th><th class="px-4 py-2.5">Status</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($all as $e)
                        <tr wire:key="all-{{ $e->id }}" class="cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5" wire:click="openPanel('entry:{{ $e->id }}')">
                            <td class="px-4 py-2.5 font-medium">{{ $e->user?->name }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap tabular-nums">{{ $e->clocked_in_at->copy()->setTimezone($tz)->format('M j g:i A') }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap tabular-nums">{{ $e->clocked_out_at?->copy()->setTimezone($tz)->format('M j g:i A') ?? 'open' }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $fmt($e->duration_minutes) }}</td>
                            <td class="px-4 py-2.5">{{ \Illuminate\Support\Str::limit($e->purposeLabel(), 50) }}</td>
                            <td class="px-4 py-2.5">{{ ['ok' => 'OK', 'flagged' => 'Needs review', 'approved' => 'Approved', 'rejected' => 'Not paid'][$e->status] ?? $e->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No entries.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        @if ($all->lastPage() > 1)
            <div class="flex items-center justify-between text-sm"><span class="vxw-meta">Page {{ $all->currentPage() }} of {{ $all->lastPage() }}</span>
                <div class="flex gap-2"><button type="button" class="vxw-btn vxw-btn--sm" wire:click="gotoPage({{ $all->currentPage() - 1 }})" @disabled($all->currentPage() === 1)>Previous</button><button type="button" class="vxw-btn vxw-btn--sm" wire:click="gotoPage({{ $all->currentPage() + 1 }})" @disabled($all->currentPage() === $all->lastPage())>Next</button></div>
            </div>
        @endif
    @endif
</div>

{{-- Review side panel --}}
@if ($panel)
    <div class="fixed inset-0 z-40" style="background:rgb(3 7 18 / .45)" wire:click="closePanel" aria-hidden="true"></div>
    <aside class="vxw fixed inset-y-0 right-0 z-50 flex w-full flex-col overflow-y-auto shadow-2xl" style="max-width:30rem;background:var(--vxw-surface);gap:0;display:flex;align-content:start;justify-content:flex-start" role="dialog" aria-modal="true" aria-label="Review time" wire:key="panel-{{ $reviewing }}">
        <div class="flex items-start justify-between gap-3 border-b px-5 py-4" style="border-color:var(--vxw-border)">
            <div>
                <div class="vxw-eyebrow">{{ $panel['kind'] === 'entry' ? 'Time entry' : 'Show Hours Worked' }}</div>
                <div class="vxw-h2">{{ $panel['kind'] === 'entry' ? $panel['entry']->user?->name : $panel['report']->streamer?->name }}</div>
            </div>
            <button type="button" wire:click="closePanel" class="vxw-btn vxw-btn--ghost vxw-btn--sm" aria-label="Close"><x-filament::icon icon="heroicon-m-x-mark" /></button>
        </div>

        <div class="space-y-4 px-5 py-4">
            @if ($panel['kind'] === 'entry')
                @php $e = $panel['entry']; @endphp
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="vxw-meta">Clocked in</dt><dd class="font-semibold">{{ $e->clocked_in_at->copy()->setTimezone($tz)->format('D M j, g:i A') }}</dd></div>
                    <div><dt class="vxw-meta">Clocked out</dt><dd class="font-semibold">{{ $e->clocked_out_at?->copy()->setTimezone($tz)->format('D M j, g:i A') ?? 'Never' }}</dd></div>
                    <div><dt class="vxw-meta">Clock time</dt><dd class="font-semibold">{{ $fmt($e->duration_minutes ?? (int) $e->clocked_in_at->diffInMinutes(now())) }}</dd></div>
                    <div><dt class="vxw-meta">For</dt><dd class="font-semibold">{{ $e->purposeLabel() }}</dd></div>
                </dl>
                @if ($e->flags)<div class="flex flex-wrap gap-1.5">@foreach ($e->flagLabels() as $f)<span class="vxw-pill vxw-pill--warn">{{ $f }}</span>@endforeach</div>@endif
                @if ($e->show?->show_duration)<p class="vxw-hint">Reference only — show length on Whatnot: {{ number_format($e->show->show_duration / 60, 2) }} hrs</p>@endif

                <fieldset class="space-y-2">
                    <legend class="vxw-label mb-1">Decision</legend>
                    @if ($e->clocked_out_at)
                        <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model.live="decision" value="clock"> Approve clock time ({{ $fmt($e->duration_minutes) }})</label>
                    @endif
                    <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model.live="decision" value="corrected"> {{ $e->clocked_out_at ? 'Approve a corrected time' : 'Enter the time actually worked' }}</label>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model.live="decision" value="reject"> Don't pay this time</label>
                </fieldset>
            @else
                @php $r = $panel['report']; $reportMin = $r->hours_streamed !== null ? (int) round((float) $r->hours_streamed * 60) : null; @endphp
                <div class="text-sm"><div class="vxw-meta">{{ $r->show?->show_date?->format('D M j, Y') }}</div><div class="font-semibold">{{ $r->show?->title }}</div></div>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="vxw-meta">Report Hours Worked</dt><dd class="font-semibold">{{ $fmt($reportMin) }}</dd></div>
                    <div><dt class="vxw-meta">Clocked for this show</dt><dd class="font-semibold">{{ $fmt($panel['clock_minutes']) }}</dd></div>
                    @if ($reportMin !== null && $panel['clock_minutes'] > 0)<div><dt class="vxw-meta">Difference</dt><dd class="font-semibold">{{ $fmt(abs($reportMin - $panel['clock_minutes'])) }}</dd></div>@endif
                    <div><dt class="vxw-meta">Whatnot length (reference)</dt><dd>{{ $r->show?->show_duration ? number_format($r->show->show_duration / 60, 2) . ' hrs' : '—' }}</dd></div>
                </dl>
                @if ($panel['clock_entries']->isNotEmpty())
                    <ul class="space-y-1 text-xs text-gray-500">@foreach ($panel['clock_entries'] as $c)<li>{{ $c->clocked_in_at->copy()->setTimezone($tz)->format('g:i A') }} – {{ $c->clocked_out_at?->copy()->setTimezone($tz)->format('g:i A') ?? 'open' }} · {{ $fmt($c->duration_minutes) }}@if($c->status !== 'ok') · {{ $c->status }}@endif</li>@endforeach</ul>
                @endif
                <fieldset class="space-y-2">
                    <legend class="vxw-label mb-1">Pay this show's Hours Worked as</legend>
                    @if ($panel['clock_minutes'] > 0)<label class="flex items-center gap-2 text-sm"><input type="radio" wire:model.live="decision" value="clock"> Clock time ({{ $fmt($panel['clock_minutes']) }})</label>@endif
                    @if ($reportMin !== null)<label class="flex items-center gap-2 text-sm"><input type="radio" wire:model.live="decision" value="report"> Report hours ({{ $fmt($reportMin) }})</label>@endif
                    <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model.live="decision" value="corrected"> A corrected figure</label>
                </fieldset>
            @endif

            @if ($decision === 'corrected')
                <div class="flex items-end gap-2">
                    <label class="vxw-field" style="width:6rem"><span class="vxw-label">Hours</span><input type="number" min="0" max="24" wire:model="correctedHours" class="vxw-input vxw-num"></label>
                    <label class="vxw-field" style="width:6rem"><span class="vxw-label">Minutes</span><input type="number" min="0" max="59" wire:model="correctedMinutes" class="vxw-input vxw-num"></label>
                </div>
            @endif
            <label class="vxw-field"><span class="vxw-label">Note {{ $decision === 'corrected' ? '(required)' : '(optional)' }}</span><textarea wire:model="reviewNote" rows="2" class="vxw-textarea" style="min-height:64px" placeholder="Why — kept with the entry"></textarea></label>
            @error('decision')<div class="vxw-alert vxw-alert--bad text-sm">{{ $message }}</div>@enderror
            <div class="flex gap-2">
                <button type="button" wire:click="saveReview" wire:loading.attr="disabled" class="vxw-btn vxw-btn--primary" @disabled($decision === '')>Save decision</button>
                <button type="button" wire:click="closePanel" class="vxw-btn vxw-btn--ghost">Cancel</button>
            </div>

            <div>
                <div class="vxw-label mb-1">History</div>
                @forelse ($panel['history'] as $h)
                    <div class="border-l-2 py-1 pl-3 text-xs" style="border-color:var(--vxw-border)">
                        <div class="font-semibold">{{ \Illuminate\Support\Str::headline(str_replace(':', ' ', $h->action)) }} · {{ $h->user?->name ?? 'System' }}</div>
                        <div class="text-gray-500">{{ $h->created_at->copy()->setTimezone($tz)->format('M j, g:i A') }}</div>
                        @if ($h->old_values && $h->new_values)
                            @foreach (['clocked_in_at' => 'In', 'clocked_out_at' => 'Out', 'minutes' => 'Time', 'approved_minutes' => 'Approved', 'hours_worked' => 'Hours worked'] as $k => $lab)
                                @if (array_key_exists($k, $h->new_values) && ($h->old_values[$k] ?? null) !== $h->new_values[$k])
                                    @php $show = fn ($v) => $v === null ? '—' : (in_array($k, ['minutes', 'approved_minutes']) ? $fmt($v) : (in_array($k, ['clocked_in_at', 'clocked_out_at']) ? \Illuminate\Support\Carbon::parse($v)->setTimezone($tz)->format('M j g:i A') : $v)); @endphp
                                    <div>{{ $lab }}: <span class="text-gray-400">{{ $show($h->old_values[$k] ?? null) }}</span> → {{ $show($h->new_values[$k]) }}</div>
                                @endif
                            @endforeach
                        @endif
                        @if ($h->reason)<div class="italic">“{{ $h->reason }}”</div>@endif
                    </div>
                @empty
                    <div class="vxw-meta">No changes recorded.</div>
                @endforelse
            </div>
        </div>
    </aside>
@endif
</x-filament-panels::page>
