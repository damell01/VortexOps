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
    [$periodStart,$periodEnd] = $this->periodBounds();
    $trend = $this->payoutTrend();
    $trendMax = max(1, max(array_column($trend, 'total')));
    $processed = $workflow['paid'] + $workflow['in_run'] + $workflow['ready'];
    $processedPercent = $progress['total'] ? min(100, (int)round($processed / $progress['total'] * 100)) : 0;

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
    <style>
        .vx-payroll-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px}.vx-payroll-period{display:flex;align-items:center;gap:12px;padding:10px 12px;border:1px solid var(--vxw-border);border-radius:12px;background:var(--vxw-surface)}.vx-payroll-period button{padding:4px 9px;border-radius:7px;font-size:18px}.vx-payroll-period strong{font-size:14px}.vx-payroll-period input{max-width:130px;border:0;background:transparent;font-size:12px;color:var(--vxw-muted)}
        .vx-payroll-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:18px}.vx-payroll-kpi{display:flex;align-items:center;gap:12px;padding:18px;border:1px solid var(--vxw-border);border-radius:15px;background:var(--vxw-surface)}.vx-payroll-kpi strong{font-size:24px;font-variant-numeric:tabular-nums}.vx-payroll-kpi p{font-size:12px;color:var(--vxw-muted)}.vx-payroll-icon{padding:12px;border-radius:14px;background:rgb(124 58 237 / .1);color:#7c3aed;flex-shrink:0}.vx-payroll-icon svg{width:22px;height:22px}
        .vx-payroll-insights{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:16px;margin-bottom:18px}.vx-payroll-trend{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:10px;align-items:end;height:180px;margin-top:24px;border-bottom:1px solid var(--vxw-border)}.vx-payroll-bar{border-radius:5px 5px 0 0;background:linear-gradient(#ac86ff,#7c3aed);min-height:3px}.vx-payroll-trend-point{display:flex;flex-direction:column;justify-content:flex-end;gap:8px;height:100%;text-align:center;font-size:10px;color:var(--vxw-muted)}.vx-payroll-status-row{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--vxw-border);font-size:12px;width:100%;text-align:left}.vx-payroll-progress{height:12px;border-radius:20px;background:rgb(124 58 237 / .14);overflow:hidden;margin:16px 0}.vx-payroll-progress span{display:block;height:100%;background:#8b5cf6}.vx-payroll-quick{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px;border-radius:12px;background:rgb(124 58 237 / .06);margin-top:10px;font-size:13px;font-weight:650}.vx-payroll-quick.primary{background:#7c3aed;color:white}.vx-payroll-quick small{display:block;font-size:11px;font-weight:400;opacity:.75}
        .vx-payroll-table{width:100%;border-collapse:collapse;font-size:12px}.vx-payroll-table th{text-align:left;padding:12px;background:var(--vxw-surface-2);color:var(--vxw-muted);font-size:10px;text-transform:uppercase}.vx-payroll-table td{padding:14px 12px;border-top:1px solid var(--vxw-border);vertical-align:middle}.vx-payroll-table .show-title{display:block;font-weight:650;max-width:210px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.vx-payroll-mobile-queue{display:none}.vx-payroll-search{margin:14px 18px;display:flex;gap:12px;align-items:center}.vx-payroll-search input{width:100%;max-width:400px;border:1px solid var(--vxw-border);border-radius:10px;padding:10px 12px;background:var(--vxw-surface);font-size:13px}
        @media(max-width:1200px){.vx-payroll-insights{grid-template-columns:1fr 1fr}.vx-payroll-insights>section:last-child{grid-column:1/-1}.vx-payroll-kpi{flex-direction:column;align-items:flex-start;padding:14px}.vx-payroll-kpi strong{font-size:22px}}
        @media(max-width:900px){.vx-payroll-table{display:none}.vx-payroll-mobile-queue{display:block}.vx-payroll-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.vx-payroll-kpi:first-child{grid-column:1/-1}.vx-payroll-insights{grid-template-columns:1fr}.vx-payroll-insights>section:last-child{grid-column:auto}.vx-payroll-head{align-items:stretch}.vx-payroll-period{width:100%;justify-content:space-between}.vx-payroll-period input{display:none}.vx-payroll-search{flex-wrap:wrap}.vx-payroll-search input{max-width:none}}
        .vx-payroll-kpi:nth-child(1) .vx-payroll-icon{background:#d1fae5;color:#047857}.vx-payroll-kpi:nth-child(3) .vx-payroll-icon{background:#dbeafe;color:#2563eb}.vx-payroll-kpi:nth-child(4) .vx-payroll-icon{background:#fef3c7;color:#d97706}.vx-payroll-kpi:nth-child(5) .vx-payroll-icon{background:#ffe4e6;color:#e11d48}
        [data-vx-page=payroll] .vxw-pipeline{flex-wrap:wrap;overflow:visible;gap:6px}[data-vx-page=payroll] .vxw-pipeline .vxw-tab{flex:0 0 auto;padding:9px 12px}[data-vx-page=payroll] .vxw-split{grid-template-columns:minmax(0,1fr) 260px}
        @media(max-width:1100px){[data-vx-page=payroll] .vxw-split{grid-template-columns:1fr}}
    </style>
    <div class="vx-payroll-head">
        <div class="vx-payroll-period"><button type="button" wire:click="changePeriod(-1)" aria-label="Previous pay period">‹</button><div><p class="vxw-meta">Pay period · {{ $runStatus }}</p><strong>{{ $periodStart->format('M j') }} – {{ $periodEnd->format('M j, Y') }}</strong></div><input type="date" wire:model.live="period_date" aria-label="Choose pay period"><button type="button" wire:click="changePeriod(1)" aria-label="Next pay period">›</button></div>
        <div class="flex flex-wrap items-center gap-2"><a class="vxw-btn" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index') }}">History</a>@if($current)<a class="vxw-btn" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view',['record'=>$current]) }}">Review run</a>@endif<a class="vxw-btn vxw-btn--primary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('create') }}">+ Create Pay Run</a></div>
    </div>
    <div class="vx-payroll-kpis">
        @foreach([['Selected run total',$money($current?->total_payout ?? 0),$current ? 'Saved pay lines' : 'No run created','heroicon-o-banknotes'],['People in run',$breakdown['people'],'Streamers & fulfillment','heroicon-o-user-group'],['Ready for payroll',$readiness['ready'],'Ready or in a pay run','heroicon-o-check-circle'],['Needs attention',$readiness['review'],'Reports, time or pay setup','heroicon-o-clock'],['Pending approval',$progress['needs_review'],'Awaiting report review','heroicon-o-document-text']] as [$label,$value,$hint,$icon])
            <div class="vx-payroll-kpi"><span class="vx-payroll-icon"><x-filament::icon :icon="$icon" /></span><div><p>{{ $label }}</p><strong>{{ $value }}</strong><p>{{ $hint }}</p></div></div>
        @endforeach
    </div>
    <div class="vx-payroll-insights">
        <section class="vxw-card"><div class="vxw-card-body"><div class="flex items-center justify-between gap-3"><h3 class="vxw-h2">Payout trend</h3><span class="vxw-meta">Last 8 weeks</span></div><p class="vxw-sub">Paid runs grouped by period start.</p><div class="vx-payroll-trend" role="img" aria-label="Paid pay run totals for the last eight weeks">@foreach($trend as $point)<div class="vx-payroll-trend-point" title="{{ $point['label'] }}: {{ $money($point['total']) }}"><div class="vx-payroll-bar" style="height:{{ $point['total'] ? round($point['total']/$trendMax*145) : 3 }}px"></div><span>{{ $point['label'] }}</span></div>@endforeach</div>@if(!array_sum(array_column($trend,'total')))<p class="vxw-meta mt-3">No paid runs in this range.</p>@endif</div></section>
        <section class="vxw-card"><div class="vxw-card-body"><h3 class="vxw-h2">Pay period status</h3><p class="vxw-sub">{{ $processed }} of {{ $progress['total'] }} shows ready, in a run or paid</p><div class="vx-payroll-progress" role="progressbar" aria-valuenow="{{ $processedPercent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Payroll progress"><span style="width:{{ $processedPercent }}%"></span></div>@foreach([['paid','Completed & paid',$workflow['paid']],['ready','Ready for a run',$workflow['ready']],['in_run','In a pay run',$workflow['in_run']],['review','Pending approval',$progress['needs_review']],['blocked','Blocked',$workflow['blocked']]] as [$filter,$label,$count])<button type="button" class="vx-payroll-status-row" wire:click="setWorkflow('{{ $filter }}')"><span>{{ $label }}</span><strong>{{ $count }} ›</strong></button>@endforeach</div></section>
        <section class="vxw-card"><div class="vxw-card-body"><h3 class="vxw-h2">Quick actions</h3><a class="vx-payroll-quick primary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('create') }}"><span>Create a pay run<small>Choose a saved type or a custom run</small></span><span>›</span></a><a class="vx-payroll-quick" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index') }}"><span>View history<small>Review past pay runs and details</small></span><span>›</span></a><a class="vx-payroll-quick" href="{{ \App\Filament\Pages\PaymentStructures::getUrl() }}"><span>Rates & Adjustments<small>Manage rates and reusable run types</small></span><span>›</span></a><a class="vx-payroll-quick" href="{{ \App\Filament\Pages\PaymentStructures::getUrl() }}#rates-people"><span>Individual overrides<small>Adjust a person’s compensation</small></span><span>›</span></a></div></section>
    </div>

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

            <div class="vx-payroll-search"><input type="search" wire:model.live.debounce.300ms="queue_search" placeholder="Search shows or streamers…" aria-label="Search payroll shows"><span class="vxw-meta">{{ $totalFilteredShows }} matching shows</span></div>
            <table class="vx-payroll-table"><thead><tr><th>Show / streamer</th><th>Channel</th><th>Date</th><th>Sales</th><th>Pay lines</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                @forelse($shows as $show)
                    @php $state=$show->getAttribute('workflow_state'); $resolution=$this->showResolution($show); @endphp
                    <tr wire:key="payroll-table-{{ $show->id }}"><td><a class="show-title" href="{{ \App\Filament\Resources\ShowResource::getUrl('view',['record'=>$show]) }}" title="{{ $show->title }}">{{ $show->title ?: 'Show #'.$show->id }}</a><p class="vxw-meta">{{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer assigned' }}</p></td><td>{{ $show->channel?->name ?? '—' }}</td><td>{{ $show->show_date?->format('D, M j') }}</td><td class="font-semibold">{{ $money($show->gross_revenue) }}</td><td class="font-semibold">{{ $show->payouts_sum_calculated_payout === null ? '—' : $money($show->payouts_sum_calculated_payout) }}</td><td><span class="vxw-pill vxw-pill--dot {{ in_array($state['key'] ?? '', ['paid','payroll_ready'], true) ? 'vxw-pill--ok' : (in_array($state['key'] ?? '', ['streamer_log','admin_review','payroll_review'], true) ? 'vxw-pill--warn' : (($state['key'] ?? '') === 'payroll' ? 'vxw-pill--info' : '')) }}">{{ $state['label'] ?? 'Needs review' }}</span></td><td><a class="vxw-btn vxw-btn--sm" href="{{ $resolution['url'] }}">{{ $resolution['label'] }}</a></td></tr>
                @empty <tr><td colspan="7" class="text-center vxw-meta">No shows match this pay period and filter.</td></tr> @endforelse
            </tbody></table>
            <div class="vxw-rows vx-payroll-mobile-queue" wire:loading.class="opacity-60" wire:target="setWorkflow,goToQueuePage" style="transition:opacity 150ms">
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
                        <div class="vxw-empty-text">{{ $activeWorkflow === 'all' ? 'Shows dated inside this pay period appear here as they are scheduled or imported.' : 'No show in this period is at this stage right now.' }}</div>
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
                                <span class="vxw-meta">{{ $run->payouts_count }} {{ \Illuminate\Support\Str::plural('line', $run->payouts_count) }}@unless(\App\Support\BusinessContext::isScoped()) · {{ $run->businessLabel() }}@endunless</span>
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
