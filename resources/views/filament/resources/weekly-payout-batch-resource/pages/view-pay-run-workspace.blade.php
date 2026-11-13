<x-filament-panels::page>
<style>
/* Pay-run header: keep the title readable when Filament has many actions. */
/* Keep the pay-run left and right columns in one document scroll flow. */
@media (min-width: 1100px) {
    .fi-page:has(.vxw[data-vx-page="pay-run"]) .vxw-split > .vxw-aside {
        position: static !important;
        top: auto !important;
    }
}
.fi-page:has(.vxw[data-vx-page="pay-run"]) .fi-header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 1rem 1.5rem;
}
.fi-page:has(.vxw[data-vx-page="pay-run"]) .fi-header > :first-child {
    flex: 1 1 22rem;
    min-width: min(100%, 22rem);
}
.fi-page:has(.vxw[data-vx-page="pay-run"]) .fi-header-heading {
    overflow-wrap: normal;
    word-break: normal;
    hyphens: none;
    line-height: 1.2;
}
.fi-page:has(.vxw[data-vx-page="pay-run"]) .fi-header-actions {
    flex: 1 1 auto;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-start;
    gap: .5rem;
}
@media (max-width: 640px) {
    .fi-page:has(.vxw[data-vx-page="pay-run"]) .fi-header > :first-child {
        flex-basis: 100%;
        min-width: 0;
    }
}
</style>

@php
    $run = $this->record;
    $run->loadMissing(['payouts.streamer','payouts.show','createdBy','finalizedBy']);
    $payouts = $run->payouts;
    // Show-level approved tip attribution is read-only here; never modify a saved pay run.
    $tipShowIds = $payouts->pluck('show_id')->filter()->unique()->values()->all();
    $approvedTipsByShow = empty($tipShowIds) ? collect() : \Illuminate\Support\Facades\DB::table('ledger_attributions as a')
        ->join('whatnot_ledger_entries as le', 'le.id', '=', 'a.whatnot_ledger_entry_id')
        ->whereIn('a.show_id', $tipShowIds)
        ->where('a.status', 'approved')
        ->where(fn ($q) => $q->where('a.category', 'tips')->orWhereRaw('LOWER(TRIM(le.transaction_type)) IN (?, ?)', ['tip', 'tips']))
        ->selectRaw('a.show_id, SUM(le.amount) as tip_total')
        ->groupBy('a.show_id')->pluck('tip_total', 'show_id');

    $periodShows = $this->periodShows();
    $problems = $run->status === 'draft' ? app(\App\Services\PayRunReadinessService::class)->problems($run) : [];
    $allPeople = $payouts->groupBy('streamer_id');
    $byPerson = $allPeople;
    $peopleSearch = trim($this->peopleSearch);
    if ($peopleSearch !== '') {
        $byPerson = $byPerson->filter(fn($rows) => str_contains(strtolower((string)($rows->first()?->streamer?->name ?? '')), strtolower($peopleSearch)));
    }
    // One section per role (streamers, assistants, fulfillment, other), and
    // largest payouts first inside each: the order a reviewer checks them in.
    $roleOf = fn ($rows) => \App\Support\PayrollRoles::of($rows->first()?->streamer);
    $byPerson = $byPerson->sortBy([
        fn ($a, $b) => \App\Support\PayrollRoles::rank($roleOf($a)) <=> \App\Support\PayrollRoles::rank($roleOf($b)),
        fn ($a, $b) => (float) $b->sum('calculated_payout') <=> (float) $a->sum('calculated_payout'),
    ]);
    $roleTotals = $payouts->groupBy(fn ($p) => \App\Support\PayrollRoles::of($p->streamer))
        ->map(fn ($rows) => ['total' => (float) $rows->sum('calculated_payout'), 'people' => $rows->pluck('streamer_id')->filter()->unique()->count()]);
    $peopleTotal = $byPerson->count();
    $peopleLastPage = max(1, (int) ceil($peopleTotal / $this::PEOPLE_PER_PAGE));
    $peoplePage = min(max(1, $this->peoplePage), $peopleLastPage);
    $visiblePeople = $byPerson->slice(($peoplePage - 1) * $this::PEOPLE_PER_PAGE, $this::PEOPLE_PER_PAGE);
    $statusLabel = \App\Models\WeeklyPayoutBatch::statusLabels()[$run->status] ?? ucfirst($run->status);
    $statusSteps = ['draft'=>'Review','finalized'=>'Finalized','submitted_to_adp'=>'Sent to ADP','paid'=>'Paid'];
    $statusKeys = array_keys($statusSteps);
    $statusIndex = array_search($run->status,$statusKeys,true);
    $statusIndex = $statusIndex === false ? 0 : $statusIndex;
    $showCount = $payouts->pluck('show_id')->filter()->unique()->count();
    $blockedUrl = \App\Filament\Pages\PayrollOverview::getUrl(['workflow'=>'blocked']);
    $nextAction = match ($run->status) {
        'draft' => $problems !== []
            ? ['title'=>'Resolve '.count($problems).' '.\Illuminate\Support\Str::plural('blocker', count($problems)),'detail'=>'Clear these before the run can be finalized.','tone'=>'warn']
            : ($payouts->isEmpty() ? ['title'=>'No pay lines yet','detail'=>'Review the shows below. Reports and approvals are still required before pay can be included.','tone'=>'info'] : ['title'=>'Ready to finalize','detail'=>'All readiness checks are clear. Finalizing locks every payout amount.','tone'=>'ok']),
        'finalized' => ['title'=>'Send to ADP','detail'=>'Amounts are locked. Export the CSV, submit it in ADP, then mark it submitted.','tone'=>'info'],
        'submitted_to_adp' => ['title'=>'Confirm payment','detail'=>'Mark the run paid once ADP confirms the payments went out.','tone'=>'info'],
        'paid' => ['title'=>'Pay run complete','detail'=>'This run is paid and team balances have been updated.','tone'=>'ok'],
        default => ['title'=>$statusLabel,'detail'=>'Review this pay run.','tone'=>'info'],
    };
    $runTone = ['paid'=>'ok','finalized'=>'info','submitted_to_adp'=>'info','draft'=>count($problems) ? 'warn' : 'accent'][$run->status] ?? '';
    $money = fn ($v) => '

@php
    $assistantReport = $this->assistantAndTipSummary();
    $assistantRows = collect($assistantReport['assistants']);
    // Assistants clocked hours in this period but have no pay line yet: they
    // are listed in the assistants section so nobody is missed.
    $paidUserIds = $payouts->map(fn ($p) => $p->streamer?->user_id)->filter()->unique();
    $assistantHoursByUser = $assistantRows->keyBy('user_id');
    $unpaidAssistants = $assistantRows->filter(fn ($a) => $a['hours'] > 0 && ! $paidUserIds->contains($a['user_id']))->values();
    $tipChannels = $assistantReport['tip_channels'];
@endphp

<div class="vxw" data-vx-page="pay-run">




    <section class="vxw-card">
        <div class="vxw-card-body grid gap-5">
            <div class="vxw-hero">
                <div>
                    <div class="vxw-eyebrow">Pay run</div>
                    <h2 class="vxw-h1">{{ $run->week_start?->format('M j') }} – {{ $run->week_end?->format('M j, Y') }}</h2>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="vxw-pill vxw-pill--dot vxw-pill--{{ $runTone }}">{{ $statusLabel }}</span>
                        <span class="vxw-meta">{{ $allPeople->count() }} {{ \Illuminate\Support\Str::plural('person', $allPeople->count()) }} · {{ $showCount }} {{ \Illuminate\Support\Str::plural('show', $showCount) }} · {{ $payouts->count() }} payout {{ \Illuminate\Support\Str::plural('line', $payouts->count()) }}</span>
                    </div>
                </div>
                <div class="vxw-hero-actions" style="width:auto">
                    <a href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}" class="vxw-btn vxw-btn--ghost vxw-btn--sm"><x-filament::icon icon="heroicon-m-arrow-left" /> Payroll</a>
                    <a href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index') }}" class="vxw-btn vxw-btn--ghost vxw-btn--sm">History</a>
                </div>
            </div>

            @if(count($reconciliationFlags) > 0)
                <details class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/20">
                    <summary class="cursor-pointer text-sm font-semibold text-amber-900 dark:text-amber-200">
                        {{ count($reconciliationFlags) }} financial reconciliation {{ \Illuminate\Support\Str::plural('notice', count($reconciliationFlags)) }} — informational
                    </summary>
                    <p class="mt-2 text-xs text-amber-800 dark:text-amber-300">These notices do not change pay amounts, payroll readiness, or finalization. Existing pay lines remain authoritative.</p>
                    <ul class="mt-3 space-y-3">
                        @foreach($reconciliationFlags as $flag)
                            <li class="text-sm">
                                <a class="font-semibold underline text-amber-900 dark:text-amber-200" href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $flag['show_id']]) }}">{{ $flag['label'] }}</a>
                                <span class="font-semibold"> — {{ $flag['title'] }}</span>
                                <p class="mt-1 text-xs text-amber-800 dark:text-amber-300">{{ $flag['detail'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            <ol class="vxw-stages" aria-label="Pay run progress">
                @foreach($statusSteps as $key => $label)
                    @php
                        $i = array_search($key, $statusKeys, true);
                    @endphp
                    <li @class(['vxw-stage', 'is-done' => $i < $statusIndex || $run->status === 'paid', 'is-current' => $i === $statusIndex && $run->status !== 'paid']) @if($i === $statusIndex) aria-current="step" @endif>
                        <span class="vxw-stage-dot">@if($i < $statusIndex || $run->status === 'paid')<x-filament::icon icon="heroicon-m-check" />@else
                            {{ $i + 1 }}
                        @endif</span>
                        <span class="vxw-stage-label">{{ $label }}</span>
                    </li>
                @endforeach
            </ol>

            {{-- The single next step, with the button that does it. --}}
            <div class="vxw-alert vxw-alert--{{ $nextAction['tone'] }}" style="align-items:center;flex-wrap:wrap">
                <x-filament::icon :icon="$nextAction['tone'] === 'warn' ? 'heroicon-o-exclamation-triangle' : ($nextAction['tone'] === 'ok' ? 'heroicon-o-check-circle' : 'heroicon-o-arrow-right-circle')" />
                <div class="flex-1" style="min-width:200px">
                    <div class="font-semibold">{{ $nextAction['title'] }}</div>
                    <div class="vxw-meta">{{ $nextAction['detail'] }}</div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($run->status === 'draft' && count($problems))
                        <a href="{{ $blockedUrl }}" class="vxw-btn vxw-btn--sm">Fix blocked shows</a>
                    @elseif($run->status === 'draft' && $payouts->isNotEmpty())
                        <button type="button" wire:click="mountAction('finalize')" wire:loading.attr="disabled" wire:target="mountAction" class="vxw-btn vxw-btn--success vxw-btn--sm"><x-filament::icon icon="heroicon-m-lock-closed" /> Finalize</button>
                    @elseif($run->status === 'finalized')
                        <button type="button" wire:click="mountAction('export_adp')" wire:loading.attr="disabled" wire:target="mountAction" class="vxw-btn vxw-btn--sm"><x-filament::icon icon="heroicon-m-arrow-down-tray" /> Export ADP CSV</button>
                        <button type="button" wire:click="mountAction('mark_submitted')" wire:loading.attr="disabled" wire:target="mountAction" class="vxw-btn vxw-btn--primary vxw-btn--sm">Mark submitted</button>
                    @elseif($run->status === 'submitted_to_adp')
                        <button type="button" wire:click="mountAction('mark_paid')" wire:loading.attr="disabled" wire:target="mountAction" class="vxw-btn vxw-btn--success vxw-btn--sm">Mark paid</button>
                    @endif
                </div>
            </div>
        </div>
        <div class="vxw-stats">
            <div class="vxw-stat"><div class="vxw-stat-label">Total payroll</div><div class="vxw-stat-value">{{ $money($run->total_payout) }}</div></div>
            @foreach(\App\Support\PayrollRoles::ORDER as $role)
                @if(($roleTotals[$role]['people'] ?? 0) > 0 || $role === 'streamer')
                    <div class="vxw-stat"><div class="vxw-stat-label">{{ \App\Support\PayrollRoles::plural($role) }}</div><div class="vxw-stat-value">{{ $money($roleTotals[$role]['total'] ?? 0) }}</div></div>
                @endif
            @endforeach
            <div class="vxw-stat"><div class="vxw-stat-label">Blockers</div><div class="vxw-stat-value {{ count($problems) ? 'vxw-tone-warn' : 'vxw-tone-ok' }}">{{ count($problems) }}</div></div>
        </div>
    </section>

    @if($periodShows !== null)
        <section class="vxw-card" aria-label="Shows in this period" x-data="{ showsExpanded: false }">
            <div class="vxw-card-head">
                <div><h3 class="vxw-h2">Shows in this period · {{ $periodShows->total() }}</h3>
                <p class="vxw-sub">Shows assigned to people in this business, including shows awaiting reports or approval. Pending shows do not add to pay.</p></div>
                <button type="button" class="vxw-btn vxw-btn--sm shrink-0" @click="showsExpanded = !showsExpanded" :aria-expanded="showsExpanded.toString()" aria-controls="payrun-period-shows">
                    <span x-text="showsExpanded ? 'Hide shows' : 'View shows'">View shows</span>
                    <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 transition-transform" x-bind:class="showsExpanded ? 'rotate-180' : ''" />
                </button>
            </div>
            <div id="payrun-period-shows" x-show="showsExpanded" x-cloak class="vxw-card-body grid gap-4">
                @forelse($periodShows as $show)
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <a class="font-semibold" href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}">{{ $show->title }}</a>
                            <div class="vxw-meta">{{ $show->show_date?->format('M j, Y') }} · {{ $show->channel?->name }} · {{ $show->streamers->pluck('name')->join(', ') }}</div>
                        </div>
                        <span class="vxw-pill">{{ $this->periodShowStatus($show) }}</span>
                    </div>
                @empty
                    <p class="vxw-meta">No shows are assigned to this business during these dates.</p>
                @endforelse
                @if($periodShows->hasPages())
                    <div class="flex items-center justify-between gap-3">
                        <button type="button" wire:click="previousShowsPage" wire:loading.attr="disabled" wire:target="previousShowsPage,nextShowsPage" @disabled($periodShows->onFirstPage()) class="vxw-btn vxw-btn--sm">Previous shows</button>
                        <span class="vxw-meta">Page {{ $periodShows->currentPage() }} of {{ $periodShows->lastPage() }}</span>
                        <button type="button" wire:click="nextShowsPage" wire:loading.attr="disabled" wire:target="previousShowsPage,nextShowsPage" @disabled(! $periodShows->hasMorePages()) class="vxw-btn vxw-btn--sm">Next shows</button>
                    </div>
                @endif
            </div>
        </section>
    @endif

    <div class="vxw-split">
        <section class="vxw-card">
            <div class="vxw-card-head">
                <div>
                    <h3 class="vxw-h2">People in this run</h3>
                    <p class="vxw-sub">Grouped by role, largest payouts first. Open a person to see the lines behind their total.</p>
                </div>
                @if($run->status === 'draft' && $payouts->isNotEmpty())
                    <button type="button" wire:click="mountAction('other_payment')" wire:loading.attr="disabled" wire:target="mountAction" class="vxw-btn vxw-btn--primary vxw-btn--sm"><x-filament::icon icon="heroicon-m-plus-circle" /> Add Payment</button>
                @endif
                <div class="vxw-search w-full sm:w-56">
                    <x-filament::icon icon="heroicon-m-magnifying-glass" />
                    <input type="search" wire:model.live.debounce.300ms="peopleSearch" placeholder="Search people" class="vxw-input" aria-label="Search people in this pay run">
                </div>
            </div>

            <div class="vxw-rows vx-payroll-people-cards" wire:loading.class="opacity-60" wire:target="peopleSearch,previousPeoplePage,nextPeoplePage" style="transition:opacity 150ms">
                @php
                    $lastRole = null;
                @endphp
                @forelse($visiblePeople as $streamerId => $rows)
                    @php
                        $person = $rows->first()->streamer;
                        $role = \App\Support\PayrollRoles::of($person);
                        $assistantHours = $role === 'assistant' ? $assistantHoursByUser->get($person?->user_id) : null;
                        $personTotal = (float) $rows->sum('calculated_payout');
                        $hours = (float) $rows->sum('hours_worked');
                        $labels = (int) $rows->sum('label_count');
                        $pwe = (int) $rows->sum('pwe_count');
                        $personShows = $rows->pluck('show_id')->filter()->unique()->count();
                    @endphp
                    @if($role !== $lastRole)
                        @php
                            $lastRole = $role;
                        @endphp
                        <div class="vx-payroll-role-head" wire:key="pay-role-{{ $role }}" data-payroll-role="{{ $role }}">
                            <span>{{ \App\Support\PayrollRoles::plural($role) }}</span>
                            <span class="vxw-meta">{{ $roleTotals[$role]['people'] ?? 0 }} {{ \Illuminate\Support\Str::plural('person', $roleTotals[$role]['people'] ?? 0) }} · {{ $money($roleTotals[$role]['total'] ?? 0) }}</span>
                        </div>
                    @endif
                    <details class="vxw-disclose vx-payroll-person-card" wire:key="pay-person-{{ $streamerId ?: 'none' }}">
                        <summary class="vxw-row" style="border-top:0">
                            <div class="vxw-row-main">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="vxw-row-title" style="white-space:normal">{{ $person?->name ?? 'Unassigned team member' }}</span>
                                    <span class="vxw-pill {{ $role === 'streamer' ? 'vxw-pill--accent' : 'vxw-pill--info' }}" style="height:20px;font-size:11px">{{ \App\Support\PayrollRoles::labels()[$role] }}</span>
                                </div>
                                <div class="vxw-row-line">
                                    <span>{{ $personShows }} {{ \Illuminate\Support\Str::plural('show', $personShows) }}</span>
                                    <span>{{ number_format($hours, 2) }} hrs</span>
                                    @if($labels || $pwe)<span>{{ $labels }} labels · {{ $pwe }} PWE</span>@endif
                                    @if($assistantHours)<span>{{ number_format($assistantHours['hours'], 2) }} h clocked @if($assistantHours['pending_review'])<span class="text-amber-600">· {{ $assistantHours['pending_review'] }} to review</span>@endif</span>@endif
                                </div>
                            </div>
                            <div class="vxw-row-side">
                                <div class="vxw-fig"><div class="vxw-fig-label">Payout</div><div class="vxw-fig-value" style="font-size:15px">{{ $money($personTotal) }}</div></div>
                                <x-filament::icon icon="heroicon-m-chevron-down" class="vxw-chevron" />
                            </div>
                        </summary>
                        <div class="vxw-disclose-body vx-payroll-line-cards">
                            @if($run->status === 'draft')
                                <div class="flex flex-wrap justify-end gap-2 pb-2">
                                    <button type="button" wire:click="mountAction('other_payment', { payout_id: {{ (int) $rows->first()->id }} })" class="vxw-btn vxw-btn--sm"><x-filament::icon icon="heroicon-m-plus-circle" /> Add Payment for {{ $person?->name ?? 'this person' }}</button>
                                </div>
                            @endif
                            @foreach($rows as $payout)
                                <article class="vx-payroll-line-card">
                                    <div>
                                        <div class="truncate font-semibold text-[var(--vxw-text)]">{{ $payout->show?->title ?? 'General payroll line' }}</div>
                                    @if($payout->show)
                                        @php
                                            $recordedTip = max((float) ($payout->show->tips ?? 0), (float) ($approvedTipsByShow[$payout->show_id] ?? 0));
                                        @endphp
                                        @if($recordedTip > 0)
                                            <span class="vxw-pill" style="background:#dcfce7;color:#166534;border-color:#86efac" title="Recorded show tips; whether they are included in payout depends on the pay formula">
                                                Tips recorded: {{ $money($recordedTip) }}
                                            </span>
                                            @if(! $payout->streamer?->include_tips)
                                                <span class="vxw-meta" title="This streamer's settings do not include tips in their payout formula">Not included in payout</span>
                                            @endif
                                        @endif
                                    @endif
                                        <div class="vxw-meta">{{ $payout->payout_type === \App\Services\OtherPayService::TYPE ? 'Other Pay' : (\App\Models\Streamer::payoutTypeLabels()[$payout->payout_type] ?? ucfirst(str_replace('_',' ',$payout->payout_type ?? ''))) }}@if($payout->show?->show_date) · {{ $payout->show->show_date->format('M j') }}@endif</div>
                                        @if(str_contains((string) $payout->calculation_notes, '[OTHER_PAYMENT:'))
                                            <div class="vxw-meta" style="margin-top:4px">
                                                @foreach(preg_split('/\\r?\\n/', (string) $payout->calculation_notes) as $note)
                                                    @if(str_starts_with(trim($note), '[OTHER_PAYMENT:'))
                                                        <div>{{ trim(str_replace(['[OTHER_PAYMENT:PERSON]', '[OTHER_PAYMENT:SHOW]'], ['General payment:', 'Show payment:'], $note)) }}</div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    @if($payout->show)
                                        <div class="col-span-full text-xs text-gray-500 dark:text-gray-400">
                                            Hours source: {{ $payout->show->streamerLogEntry?->hours_streamed !== null ? 'Streamer report' : 'Time clock / missing' }} ·
                                            Counts source: {{ $payout->show->streamerLogEntry ? 'Streamer report / fulfillment fallback' : 'Show / fulfillment fallback' }}.
                                            Show duration is not automatically paid hours.
                                        </div>
                                    @endif
                                    @if($payout->calculation_notes)
                                        <details class="col-span-full rounded-lg border border-gray-200 p-2 text-xs dark:border-gray-700">
                                            <summary class="cursor-pointer font-semibold">How this payout was calculated</summary>
                                            <div class="mt-2 whitespace-pre-wrap break-words text-gray-600 dark:text-gray-300">{{ $payout->calculation_notes }}</div>
                                        </details>
                                    @endif
                                    @if($run->status === 'draft')
                                        <div class="col-span-full flex justify-end">
                                            <button type="button" class="vxw-btn vxw-btn--sm" wire:click="mountAction('override_payout', { payout_id: {{ (int) $payout->id }} })">
                                                <x-filament::icon icon="heroicon-m-pencil-square" /> Adjust payout
                                            </button>
                                            @if($payout->show)
                                                <button type="button" class="vxw-btn vxw-btn--sm" wire:click="mountAction('edit_show_inputs', { payout_id: {{ (int) $payout->id }} })">
                                                    <x-filament::icon icon="heroicon-m-pencil-square" /> Edit inputs
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                    <div><div class="vxw-fig-label">Hours</div><div class="vxw-num">{{ number_format((float)$payout->hours_worked, 2) }}</div></div>
                                    <div><div class="vxw-fig-label">Shipments</div><div class="vxw-num">{{ number_format((int)$payout->shipments_count) }}</div></div>
                                    <div><div class="vxw-fig-label">Labels</div><div class="vxw-num">{{ number_format((int)$payout->label_count) }}</div></div>
                                    <div><div class="vxw-fig-label">PWE</div><div class="vxw-num">{{ number_format((int)$payout->pwe_count) }}</div></div>
                                    <div class="text-right max-[900px]:text-left"><div class="vxw-fig-label">Amount</div><div class="vxw-fig-value">{{ $money($payout->calculated_payout) }}</div></div>
                                </article>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <div class="vxw-empty">
                        <x-filament::icon icon="heroicon-o-users" />
                        <div class="vxw-empty-text">{{ $peopleSearch !== '' ? 'No people match your search.' : 'No payout lines are attached to this run yet.' }}</div>
                    </div>
                @endforelse

                @if($unpaidAssistants->isNotEmpty() && $peopleSearch === '' && $peoplePage === $peopleLastPage)
                    <div class="vx-payroll-role-head" data-payroll-role="assistant-unpaid">
                        <span>Streamer assistants with hours but no pay line</span>
                        <span class="vxw-meta">Add them before finalizing if they should be paid in this run</span>
                    </div>
                    @foreach($unpaidAssistants as $assistant)
                        <div class="vxw-row" wire:key="pay-assistant-hours-{{ $assistant['user_id'] }}">
                            <div class="vxw-row-main">
                                <div class="flex flex-wrap items-center gap-2"><span class="vxw-row-title">{{ $assistant['name'] }}</span><span class="vxw-pill vxw-pill--warn" style="height:20px;font-size:11px">No pay line</span></div>
                                <div class="vxw-row-line"><span>{{ number_format($assistant['hours'], 2) }} h clocked</span>@if($assistant['pending_review'])<span class="text-amber-600">{{ $assistant['pending_review'] }} to review</span>@endif</div>
                            </div>
                            <div class="vxw-row-side"><div class="vxw-fig"><div class="vxw-fig-label">Estimated</div><div class="vxw-fig-value" style="font-size:15px">{{ $assistant['estimated_pay'] !== null ? $money($assistant['estimated_pay']) : 'No rate set' }}</div></div></div>
                        </div>
                    @endforeach
                @endif
            </div>

            @if($peopleLastPage > 1)
                <div class="vxw-card-foot">
                    <span class="vxw-meta">{{ (($peoplePage-1)*$this::PEOPLE_PER_PAGE)+1 }}–{{ min($peoplePage*$this::PEOPLE_PER_PAGE,$peopleTotal) }} of {{ $peopleTotal }} people</span>
                    <div class="vxw-pager">
                        <button type="button" class="vxw-page" wire:click="previousPeoplePage" wire:loading.attr="disabled" wire:target="previousPeoplePage,nextPeoplePage" @disabled($peoplePage <= 1) aria-label="Previous page">‹</button>
                        <span class="vxw-meta px-2">{{ $peoplePage }} / {{ $peopleLastPage }}</span>
                        <button type="button" class="vxw-page" wire:click="nextPeoplePage({{ $peopleLastPage }})" wire:loading.attr="disabled" wire:target="previousPeoplePage,nextPeoplePage" @disabled($peoplePage >= $peopleLastPage) aria-label="Next page">›</button>
                    </div>
                </div>
            @endif
        </section>

        <aside class="vxw-aside">
            @php
                // Read-only approved tip attribution; never changes this pay run or distributes unmatched tips.
                // Only the people on this run: tips of streamers it doesn't pay stay off it.
                $streamerTips = $this->runStreamerTips();
                $streamerTips = collect($streamerTips)->sortByDesc('net')->values();
                $approvedTipNet = $streamerTips->sum('net');
                $importedTipNet = array_sum(array_column($tipChannels, 'net'));
            @endphp
            @php $bizNet = $this->businessNet(); @endphp
            @if($bizNet['business'])
                <section class="vxw-card" data-vx-business-net>
                    <div class="vxw-card-head">
                        <h3 class="vxw-h2">{{ $bizNet['business'] }} streams · Whatnot net</h3>
                        <span class="vxw-pill vxw-pill--accent">{{ $money($bizNet['total']) }}</span>
                    </div>
                    <div class="vxw-card-body">
                        @if($bizNet['channels'])
                            <div data-vx-pair-net style="border:1px solid #ddd6fe;background:linear-gradient(135deg,#f5f3ff,#ede9fe);border-radius:14px;padding:12px 14px;margin-bottom:12px">
                                <p class="vxw-meta" style="margin:0;font-weight:600">{{ \App\Services\BusinessChannelNet::PAIR_LABEL }}</p>
                                <strong style="font-size:24px;font-variant-numeric:tabular-nums">{{ $money($bizNet['pair']['net']) }}</strong>
                                <p class="vxw-meta" style="margin:0">{{ $bizNet['pair']['shows'] }} {{ \Illuminate\Support\Str::plural('show', $bizNet['pair']['shows']) }} · Whatnot net, this run's dates</p>
                            </div>
                            <dl class="vxw-dl">
                                @foreach($bizNet['channels'] as $c)
                                    <div><dt>{{ $c['channel'] }} <span class="vxw-meta">({{ $c['shows'] }} {{ \Illuminate\Support\Str::plural('show', $c['shows']) }})</span></dt><dd>{{ $money($c['net']) }}</dd></div>
                                @endforeach
                                <div><dt><strong>Total</strong></dt><dd><strong>{{ $money($bizNet['total']) }}</strong></dd></div>
                            </dl>
                        @else
                            <p class="vxw-meta">No {{ $bizNet['business'] }} shows in this period.</p>
                        @endif
                        <p class="vxw-meta mt-2">Shows hosted by {{ $bizNet['business'] }} streamers, {{ $run->week_start?->format('M j') }} – {{ $run->week_end?->format('M j') }}.</p>
                    </div>
                </section>
            @endif
            <section class="vxw-card" data-vx-tip-streamers>
                <div class="vxw-card-head">
                    <h3 class="vxw-h2">Verified tips by streamer</h3>
                    <span class="vxw-pill">{{ $money($approvedTipNet / 100) }}</span>
                </div>
                <div class="vxw-card-body">
                    @if($streamerTips->isNotEmpty())
                        <dl class="vxw-dl">
                            @foreach($streamerTips as $tip)
                                <div>
                                    <dt>{{ $tip['streamer'] }} <span class="vxw-meta">({{ number_format($tip['entries']) }} tips)</span></dt>
                                    <dd>{{ $money($tip['net'] / 100) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @else
                        <p class="vxw-meta">No approved streamer tip assignments for this period yet.</p>
                    @endif
                    <p class="vxw-meta mt-2">Approved tips for the people on this run, {{ $run->week_start?->format('M j') }} – {{ $run->week_end?->format('M j') }}. Tips in a streamer's pay formula are on their show tips line.</p>
                    <details class="mt-3">
                        <summary class="vxw-meta cursor-pointer font-semibold">Imported tips by channel and reconciliation</summary>
                        <div class="mt-2">
                            <p class="vxw-meta">{{ $run->business_id ? $run->businessLabel() . ' imported' : 'All-channel imported' }} total: {{ $money($importedTipNet / 100) }}. Verified streamer total: {{ $money($approvedTipNet / 100) }}. These are different attribution scopes; the difference is not necessarily an error.</p>
                            @if($tipChannels)
                                <dl class="vxw-dl mt-2">
                                    @foreach($tipChannels as $channel)
                                        <div><dt>{{ $channel['channel'] }}</dt><dd>{{ $money($channel['net'] / 100) }}</dd></div>
                                    @endforeach
                                </dl>
                            @endif
                        </div>
                    </details>
                </div>
            </section>
            @if(auth()->user()?->canSeeBusinessFinancials() && ($transfer = \App\Support\BusinessTransfer::forRun($run)))
            @php
                $signed = fn ($v) => ((float) $v < 0 ? '−' : '') . '$' . number_format(abs((float) $v), 2);
            @endphp
            <section class="vxw-card" data-vx-transfer>
                <div class="vxw-card-head">
                    <h3 class="vxw-h2">Business account transfer<x-vx-help topic="payrun.transfer" /></h3>
                    <span class="vxw-pill vxw-pill--accent">{{ $signed($transfer['transfer']) }}</span>
                </div>
                <div class="vxw-card-body">
                    <p class="vxw-meta">To the separate bank account, for the {{ $transfer['shows'] }} {{ \Illuminate\Support\Str::plural('show', $transfer['shows']) }} on this run. Pay counts everything for them except tips{{ $run->kind === 'wages' ? ', including profit share paid on a later run' : '' }}.</p>
                    <dl class="vxw-dl mt-2">
                        <div><dt>Product cost</dt><dd>{{ $signed($transfer['product_cost']) }}</dd></div>
                        <div><dt>Whatnot net earnings</dt><dd>{{ $signed($transfer['net']) }}</dd></div>
                        <div><dt>− Pay for these shows</dt><dd>{{ $signed($transfer['payouts']) }}</dd></div>
                        <div><dt>= Business revenue</dt><dd>{{ $signed($transfer['business_revenue']) }}</dd></div>
                        <div><dt><strong>Transfer</strong></dt><dd><strong>{{ $signed($transfer['transfer']) }}</strong></dd></div>
                    </dl>
                </div>
            </section>
            @endif
            <section class="vxw-card">
                <details class="vxw-disclose">
                    <summary class="vxw-card-head" style="border-bottom:0">
                        <h3 class="vxw-h2">Run details</h3>
                        <x-filament::icon icon="heroicon-m-chevron-down" class="vxw-chevron" />
                    </summary>
                    <div class="vxw-card-body" style="padding-top:0">
                        <dl class="vxw-dl">
                            @foreach([['Status',$statusLabel],['Created by',$run->createdBy?->name],['Finalized by',$run->finalizedBy?->name],['Finalized at',$run->finalized_at?->copy()->timezone(\App\Support\BusinessTime::zone())->format('M j, Y g:i A')],['Notes',$run->notes]] as [$label,$value])
                                <div><dt>{{ $label }}</dt><dd>{{ $value ?: '—' }}</dd></div>
                            @endforeach
                        </dl>
                    </div>
                </details>
            </section>
        </aside>
    </div>
</div>
</x-filament-panels::page>
