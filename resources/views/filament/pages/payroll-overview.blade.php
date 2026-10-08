<x-filament-panels::page>
@php
    $current = $this->currentPayRun();
    $breakdown = $this->currentBreakdown();
    $recent = $this->recentPayRuns();
    $readiness = $this->readinessSummary();
    $workflow = $this->workflowBreakdown();
    $progress = $this->periodProgress();

    $filteredShows = $this->currentWeekShows();
    $perPage = 12;
    $totalFilteredShows = $filteredShows->count();
    $lastPage = max(1, (int) ceil($totalFilteredShows / $perPage));
    $page = min(max(1, $this->queuePage), $lastPage);
    $shows = $filteredShows->forPage($page, $perPage)->values();

    $activeWorkflow = $this->workflow ?: 'all';
    $statusLabels = \App\Models\WeeklyPayoutBatch::statusLabels();
    $runTone = fn (?string $status) => match ($status) {
        'paid' => 'ok',
        'finalized', 'submitted_to_adp' => 'info',
        'draft' => 'accent',
        default => '',
    };
    $runStatus = $current ? ($statusLabels[$current->status] ?? ucfirst($current->status)) : 'No pay run yet';
    $periodStart = $current?->week_start ?? now()->startOfWeek();
    $periodEnd = $current?->week_end ?? now()->endOfWeek();

    // The pipeline reads left to right in the order a show actually moves.
    $stages = [
        'all'              => ['All shows',         $progress['total'],          false],
        'needs_assignment' => ['Needs streamer',    $workflow['needs_assignment'], true],
        'needs_log'        => ['Needs report',      $progress['needs_log'],      true],
        'review'           => ['Needs approval',    $progress['needs_review'],   true],
        'blocked'          => ['Blocked',           $workflow['blocked'],        true],
        'ready'            => ['Ready',             $workflow['ready'],          false],
        'in_run'           => ['In pay run',        $workflow['in_run'],         false],
        'paid'             => ['Paid',              $workflow['paid'],           false],
        'upcoming'         => ['Upcoming',          $progress['upcoming'],       false],
    ];
    $money = fn ($v, $d = 2) => '$' . number_format((float) $v, $d);
@endphp

<div class="vxw" data-vx-page="payroll">
    {{-- Period header: where this week stands and the one thing to do next. --}}
    <section class="vxw-card">
        <div class="vxw-card-body">
            <div class="vxw-hero">
                <div>
                    <div class="vxw-eyebrow">Pay period</div>
                    <h2 class="vxw-h1">{{ $periodStart->format('M j') }} – {{ $periodEnd->format('M j, Y') }}</h2>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="vxw-pill vxw-pill--dot vxw-pill--{{ $runTone($current?->status) }}">{{ $runStatus }}</span>
                        <span class="vxw-meta">{{ $readiness['ready'] }} of {{ $readiness['shows'] }} shows ready or in the run
                        @if($readiness['review']) · <span class="vxw-tone-warn font-semibold">{{ $readiness['review'] }} need attention</span>@endif</span>
                    </div>
                </div>
                <div class="vxw-hero-actions">
                    @if($current)
                        <a class="vxw-btn vxw-btn--primary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view', ['record' => $current]) }}">
                            Review pay run
                            <x-filament::icon icon="heroicon-m-arrow-right" />
                        </a>
                    @else
                        <button type="button" class="vxw-btn vxw-btn--primary" wire:click="prepareCurrentPayRun" wire:loading.attr="disabled" wire:target="prepareCurrentPayRun">
                            <span wire:loading wire:target="prepareCurrentPayRun" class="vxw-spin" aria-hidden="true"></span>
                            <span wire:loading.remove wire:target="prepareCurrentPayRun">Prepare this week's pay run</span>
                            <span wire:loading wire:target="prepareCurrentPayRun">Preparing…</span>
                        </button>
                    @endif
                    <a class="vxw-btn" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index') }}">History</a>
                    <details class="vxw-menu" x-data @click.outside="$el.removeAttribute('open')" @keydown.escape="$el.removeAttribute('open')">
                        <summary class="vxw-btn" aria-label="More payroll tools"><x-filament::icon icon="heroicon-m-ellipsis-horizontal" /></summary>
                        <div class="vxw-menu-panel">
                            <a href="{{ \App\Filament\Pages\PaymentStructures::getUrl() }}"><x-filament::icon icon="heroicon-o-adjustments-horizontal" /> Payment structures</a>
                            <a href="{{ \App\Filament\Pages\PayrollSimulator::getUrl() }}"><x-filament::icon icon="heroicon-o-calculator" /> Payroll simulator</a>
                        </div>
                    </details>
                </div>
            </div>
        </div>
        <div class="vxw-stats">
            <div class="vxw-stat">
                <div class="vxw-stat-label">Pay run total</div>
                <div class="vxw-stat-value">{{ $money($current?->total_payout ?? 0) }}</div>
                <div class="vxw-stat-note">{{ $current ? $current->payouts_count . ' payout ' . \Illuminate\Support\Str::plural('line', $current->payouts_count) : 'Not prepared yet' }}</div>
            </div>
            <div class="vxw-stat">
                <div class="vxw-stat-label">People paid</div>
                <div class="vxw-stat-value">{{ $breakdown['people'] }}</div>
                <div class="vxw-stat-note">{{ $breakdown['streamers'] }} streamers · {{ $breakdown['fulfillment'] }} fulfillment</div>
            </div>
            <div class="vxw-stat">
                <div class="vxw-stat-label">Reports</div>
                <div class="vxw-stat-value">{{ $progress['approved'] }}<span class="text-[14px] font-medium text-[var(--vxw-faint)]"> / {{ $progress['logged'] }}</span></div>
                <div class="vxw-stat-note">approved / submitted</div>
            </div>
            <div class="vxw-stat">
                <div class="vxw-stat-label">Needs attention</div>
                <div class="vxw-stat-value {{ $readiness['review'] ? 'vxw-tone-warn' : 'vxw-tone-ok' }}">{{ $readiness['review'] }}</div>
                <div class="vxw-stat-note">{{ $readiness['review'] ? 'shows blocking payroll' : 'Nothing blocking' }}</div>
            </div>
        </div>
    </section>

    <div class="vxw-split">
        {{-- Work queue --}}
        <section class="vxw-card" aria-labelledby="vxw-queue-heading">
            <nav class="vxw-pipeline" aria-label="Filter shows by payroll stage">
                @foreach($stages as $key => [$label, $count, $attention])
                    <button type="button" wire:click="setWorkflow('{{ $key }}')"
                        @class(['vxw-tab', 'vxw-tab--attn' => $attention && $count > 0])
                        aria-current="{{ $activeWorkflow === $key ? 'true' : 'false' }}">
                        <span class="vxw-tab-label">{{ $label }}</span>
                        <span class="vxw-tab-count">{{ $count }}</span>
                    </button>
                @endforeach
            </nav>

            <div class="vxw-card-head" style="border-top:1px solid var(--vxw-border)">
                <div>
                    <h3 id="vxw-queue-heading" class="vxw-h2">{{ $stages[$activeWorkflow][0] ?? 'Shows' }}</h3>
                    <p class="vxw-sub">Shows that need you are listed first. Upcoming shows stay in the period and join the queue after they air.</p>
                </div>
                @if($activeWorkflow !== 'all')
                    <button type="button" class="vxw-btn vxw-btn--ghost vxw-btn--sm" wire:click="setWorkflow('all')">Clear filter</button>
                @endif
            </div>

            <div class="vxw-rows" wire:loading.class="opacity-60" wire:target="setWorkflow,goToQueuePage" style="transition:opacity 150ms">
                @forelse($shows as $show)
                    @php
                        $state = $show->getAttribute('workflow_state');
                        $pnl = $show->getAttribute('pnl_summary');
                        $probs = $show->getAttribute('payrun_problems') ?? [];
                        $resolution = $this->showResolution($show);
                        $key = $state['key'] ?? '';
                        $due = ! $show->show_date || ! $show->show_date->isFuture();
                        if ($show->show_date?->isToday() && $show->start_time?->isFuture()) $due = false;
                        $blockers = array_values(array_unique(array_merge($probs, $state['blockers'] ?? [])));
                        $tone = ! $due ? '' : ($blockers !== [] ? 'warn' : (in_array($key, ['paid', 'payroll_ready'], true) ? 'ok' : ($key === 'payroll' ? 'info' : '')));
                        $nextText = ! $due
                            ? 'Upcoming — needs a streamer report after it airs.'
                            : ($blockers[0] ?? ($state['description'] ?? 'Ready for payroll review.'));
                        $margin = (float) ($pnl['margin'] ?? 0);
                        $needsPaySetup = str_contains(strtolower($nextText), 'payment structure') || str_contains(strtolower($nextText), 'compensation') || str_contains(strtolower($nextText), 'pay rate');
                    @endphp
                    <article wire:key="payroll-show-{{ $show->id }}" @class(['vxw-row', 'vxw-row--attn' => $due && $blockers !== [], 'vxw-row--muted' => ! $due])>
                        <div class="vxw-row-main">
                            <div class="vxw-row-line">
                                <span class="vxw-pill vxw-pill--dot {{ $tone ? 'vxw-pill--' . $tone : '' }}">{{ $due ? ($state['label'] ?? ucfirst(str_replace('_', ' ', $key))) : 'Upcoming' }}</span>
                                <span>{{ $show->show_date?->format('D, M j') }}</span>
                            </div>
                            <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}" class="vxw-row-title" title="{{ $show->title }}">{{ $show->title ?: 'Show #' . $show->id }}</a>
                            <div class="vxw-row-line">
                                <span class="inline-flex items-center gap-1.5"><x-filament::icon icon="heroicon-m-user" class="h-3.5 w-3.5" />{{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer assigned' }}</span>
                            </div>
                            <div class="vxw-next">
                                @if($due && $blockers !== [])<strong class="vxw-tone-warn">Next:</strong>@else<strong>Next:</strong>@endif
                                {{ $nextText }}
                                @if($due && count($blockers) > 1)
                                    <details class="vxw-disclose mt-1">
                                        <summary class="vxw-link inline-flex items-center gap-1 text-[12px]">{{ count($blockers) - 1 }} more {{ \Illuminate\Support\Str::plural('issue', count($blockers) - 1) }} <x-filament::icon icon="heroicon-m-chevron-down" class="vxw-chevron h-4 w-4" /></summary>
                                        <ul class="vxw-issues mt-2">@foreach(array_slice($blockers, 1) as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
                                    </details>
                                @endif
                            </div>
                        </div>

                        <div class="vxw-row-side">
                            <div class="vxw-figs">
                                <div class="vxw-fig"><div class="vxw-fig-label">Sales</div><div class="vxw-fig-value">{{ $money($pnl['gross'] ?? 0, 0) }}</div></div>
                                <div class="vxw-fig"><div class="vxw-fig-label">Payroll</div><div class="vxw-fig-value">{{ $money($pnl['payouts'] ?? 0, 0) }}</div></div>
                                <div class="vxw-fig"><div class="vxw-fig-label">Net</div><div class="vxw-fig-value {{ $margin < 0 ? 'vxw-tone-bad' : '' }}">{{ $money($margin, 0) }}</div></div>
                            </div>
                            <div class="w-full sm:w-auto">
                                @if($show->streamers->isEmpty())
                                    <select class="vxw-select" style="min-width:180px" aria-label="Assign a streamer to {{ $show->title }}"
                                        wire:change="assignStreamerToShow({{ $show->id }}, $event.target.value)">
                                        <option value="">Assign streamer…</option>
                                        @foreach($this->streamerOptions() as $streamerId => $streamerName)<option value="{{ $streamerId }}">{{ $streamerName }}</option>@endforeach
                                    </select>
                                @elseif($show->streamerLogEntry?->isSubmitted() && $show->streamerLogEntry?->approval_status !== 'approved')
                                    <div class="flex gap-2">
                                        <a class="vxw-btn vxw-btn--sm" href="{{ \App\Filament\Resources\StreamerLogResource::getUrl('edit', ['record' => $show->streamerLogEntry]) }}">Open</a>
                                        <button type="button" class="vxw-btn vxw-btn--success vxw-btn--sm"
                                            wire:click="approveReportInline({{ $show->id }})" wire:confirm="Approve this streamer report?"
                                            wire:loading.attr="disabled" wire:target="approveReportInline({{ $show->id }})">
                                            <span wire:loading wire:target="approveReportInline({{ $show->id }})" class="vxw-spin" aria-hidden="true"></span>
                                            Approve
                                        </button>
                                    </div>
                                @elseif($needsPaySetup)
                                    <a class="vxw-btn vxw-btn--sm" href="{{ \App\Filament\Resources\StreamerResource::getUrl('edit', ['record' => $show->streamers->first()]) }}">Fix pay setup</a>
                                @else
                                    <a class="vxw-btn vxw-btn--sm {{ in_array($resolution['tone'], ['primary', 'success'], true) ? 'vxw-btn--primary' : '' }}" href="{{ $resolution['url'] }}">{{ $resolution['label'] }}</a>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="vxw-empty">
                        <x-filament::icon icon="heroicon-o-check-circle" />
                        <div class="vxw-empty-title">{{ $activeWorkflow === 'all' ? 'No shows in this pay period' : 'Nothing here' }}</div>
                        <div class="vxw-empty-text">{{ $activeWorkflow === 'all' ? 'Shows dated inside this week appear here as they are scheduled or imported.' : 'No show in this period is at this stage right now.' }}</div>
                    </div>
                @endforelse
            </div>

            @if($totalFilteredShows > 0)
                <div class="vxw-card-foot">
                    <span class="vxw-meta">{{ (($page - 1) * $perPage) + 1 }}–{{ min($page * $perPage, $totalFilteredShows) }} of {{ $totalFilteredShows }}</span>
                    @if($lastPage > 1)
                        <nav class="vxw-pager" aria-label="Show pages">
                            <button type="button" class="vxw-page" wire:click="goToQueuePage({{ $page - 1 }})" @disabled($page <= 1) aria-label="Previous page">‹</button>
                            @for($p = max(1, $page - 1); $p <= min($lastPage, $page + 1); $p++)
                                <button type="button" @class(['vxw-page', 'is-active' => $p === $page]) wire:click="goToQueuePage({{ $p }})" @if($p === $page) aria-current="page" @endif>{{ $p }}</button>
                            @endfor
                            <button type="button" class="vxw-page" wire:click="goToQueuePage({{ $page + 1 }})" @disabled($page >= $lastPage) aria-label="Next page">›</button>
                        </nav>
                    @endif
                </div>
            @endif
        </section>

        <aside class="vxw-aside">
            <section class="vxw-card">
                <div class="vxw-card-head">
                    <h3 class="vxw-h2">Recent pay runs</h3>
                    <a class="vxw-link text-[13px]" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index') }}">All</a>
                </div>
                <div class="vxw-rows">
                    @forelse($recent as $run)
                        <a href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view', ['record' => $run]) }}" class="vxw-row" style="grid-template-columns:minmax(0,1fr) auto;text-decoration:none;padding:12px 18px">
                            <div class="vxw-row-main">
                                <span class="text-[13.5px] font-semibold text-[var(--vxw-text)]">{{ $run->week_start?->format('M j') }} – {{ $run->week_end?->format('M j') }}</span>
                                <span class="vxw-meta">{{ $run->payouts_count }} {{ \Illuminate\Support\Str::plural('line', $run->payouts_count) }}</span>
                            </div>
                            <div class="grid justify-items-end gap-1">
                                <span class="vxw-num text-[13.5px] font-semibold text-[var(--vxw-text)]">{{ $money($run->total_payout) }}</span>
                                <span class="vxw-pill vxw-pill--{{ $runTone($run->status) }}" style="height:20px;font-size:11px">{{ $statusLabels[$run->status] ?? ucfirst($run->status) }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="vxw-empty" style="padding:24px 18px"><div class="vxw-empty-text">No pay runs yet.</div></div>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</div>
</x-filament-panels::page>
