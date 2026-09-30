<x-filament-panels::page>
@php
    $current = $this->currentPayRun();
    $attention = $this->needsAttention();
    $breakdown = $this->currentBreakdown();
    $recent = $this->recentPayRuns();
    $filteredShows = $this->currentWeekShows();
    $perPage = 12;
    $page = max(1, (int) request()->integer('page', 1));
    $totalFilteredShows = $filteredShows->count();
    $lastPage = max(1, (int) ceil($totalFilteredShows / $perPage));
    $page = min($page, $lastPage);
    $shows = $filteredShows->forPage($page, $perPage)->values();
    $readiness = $this->readinessSummary();
    $workflow = $this->workflowBreakdown();
    $progress = $this->periodProgress();
    $mock = $this->mockPayRun();
    $mockCalc = $this->mockShowCalculation();
    $activeWorkflow = request()->string('workflow')->toString() ?: 'all';
    $baseUrl = \App\Filament\Pages\PayrollOverview::getUrl();
    $runStatus = $current ? (\App\Models\WeeklyPayoutBatch::statusLabels()[$current->status] ?? ucfirst($current->status)) : 'No run created';
@endphp
<style>
.vx-pay{max-width:1440px;margin:0 auto;display:grid;gap:14px}.vx-card{border:1px solid #e5e7eb;background:#fff;border-radius:18px;box-shadow:0 1px 2px rgba(15,23,42,.04);overflow:hidden}.dark .vx-card{border-color:#263248;background:#101827}.vx-pad{padding:18px}.vx-actions{display:flex;flex-wrap:wrap;gap:8px}.vx-btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;border-radius:12px;padding:8px 13px;font-size:11px;font-weight:800;border:1px solid #d1d5db;color:#374151;background:#fff}.dark .vx-btn{border-color:#475569;color:#e5e7eb;background:#111827}.vx-btn.primary{background:#2563eb;border-color:#2563eb;color:#fff}.vx-btn.success{background:#059669;border-color:#059669;color:#fff}.vx-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:16px}.vx-kpi{border-radius:14px;padding:12px;background:#f8fafc}.dark .vx-kpi{background:#1f2937}.vx-kpi label{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:#94a3b8}.vx-kpi strong{display:block;margin-top:4px;font-size:20px;color:#111827}.dark .vx-kpi strong{color:#fff}.vx-flow{display:grid;grid-template-columns:repeat(6,1fr);gap:7px}.vx-flow a{border:1px solid #e5e7eb;border-radius:14px;padding:12px;text-decoration:none}.dark .vx-flow a{border-color:#374151}.vx-flow .active{border-color:#2563eb;background:#eff6ff}.dark .vx-flow .active{background:#172554}.vx-flow label{display:block;font-size:9px;text-transform:uppercase;font-weight:800;color:#94a3b8}.vx-flow strong{display:block;margin-top:3px;font-size:19px;color:#111827}.dark .vx-flow strong{color:#fff}.vx-grid{display:block}.vx-show-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;padding:12px}.vx-show{border:1px solid #e5e7eb;border-radius:14px;padding:12px;background:#fff;min-width:0;display:flex;flex-direction:column}.dark .vx-show{border-color:#263248;background:#101827}.vx-show.blocked-card{border-color:#fed7aa}.dark .vx-show.blocked-card{border-color:#7c2d12}.vx-show.upcoming-card{background:#f8fafc}.dark .vx-show.upcoming-card{background:#111827}.vx-show-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.vx-name{font-size:14px;font-weight:800;color:#111827}.dark .vx-name{color:#fff}.vx-meta{font-size:10px;color:#6b7280;margin-top:2px}.vx-chip{display:inline-flex;border-radius:999px;padding:5px 8px;font-size:9px;font-weight:800;background:#f3f4f6;color:#4b5563}.dark .vx-chip{background:#1f2937;color:#d1d5db}.vx-chip.ready{background:#ecfdf5;color:#047857}.vx-chip.blocked{background:#fff7ed;color:#c2410c}.vx-chip.run{background:#eff6ff;color:#1d4ed8}.vx-chip.upcoming{background:#f8fafc;color:#64748b}.vx-period-summary{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}.vx-period-summary span{display:inline-flex;gap:5px;align-items:center;border:1px solid #e5e7eb;border-radius:999px;padding:5px 8px;font-size:10px;color:#64748b}.dark .vx-period-summary span{border-color:#374151;color:#cbd5e1}.vx-period-summary b{color:#111827}.dark .vx-period-summary b{color:#fff}.vx-money{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;margin-top:9px}.vx-money div{border-radius:9px;background:#f8fafc;padding:7px}.vx-money div:nth-child(4),.vx-money div:nth-child(5){display:none}.dark .vx-money div{background:#1f2937}.vx-money label{display:block;font-size:8px;text-transform:uppercase;font-weight:800;color:#94a3b8}.vx-money strong{display:block;margin-top:2px;font-size:12px}.vx-next{margin-top:auto;padding-top:10px;display:flex;align-items:stretch;flex-direction:column;gap:8px}.vx-next>div:first-child{border-radius:9px;background:#f8fafc;padding:8px}.dark .vx-next>div:first-child{background:#1f2937}.dark .vx-next{background:#1f2937}.vx-alert{display:flex;gap:8px;border-radius:11px;background:#fff7ed;color:#9a3412;padding:9px 11px;font-size:11px;margin-top:7px}.dark .vx-alert{background:rgba(154,52,18,.16);color:#fdba74}.vx-ok{border-radius:11px;background:#ecfdf5;color:#047857;padding:10px 11px;font-size:11px;font-weight:750;margin-top:9px}.vx-runrow{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 0;border-top:1px solid #eef0f3}.dark .vx-runrow{border-color:#263248}.vx-runrow strong{font-size:12px}.vx-runrow span{font-size:10px;color:#6b7280}.vx-sim{border-style:dashed;border-color:#a78bfa;background:#faf5ff}.dark .vx-sim{background:#211634;border-color:#7c3aed}.vx-sim-badge{display:inline-flex;border-radius:999px;padding:4px 8px;font-size:9px;font-weight:800;background:#ede9fe;color:#6d28d9}.vx-sim-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:12px}.vx-sim-stat{padding:10px;border-radius:10px;background:rgba(255,255,255,.75)}.dark .vx-sim-stat{background:rgba(17,24,39,.65)}.vx-sim-stat label{display:block;font-size:9px;text-transform:uppercase;font-weight:800;color:#8b5cf6}.vx-sim-stat strong{display:block;margin-top:3px;font-size:18px}.vx-lab{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr);gap:12px;margin-top:14px}.vx-lab-card{border:1px solid rgba(139,92,246,.2);border-radius:12px;padding:12px;background:rgba(255,255,255,.68)}.dark .vx-lab-card{background:rgba(17,24,39,.55)}.vx-input{width:100%;min-height:38px;border:1px solid #d1d5db;border-radius:9px;padding:6px 8px;font-size:11px;background:#fff}.dark .vx-input{background:#111827;border-color:#475569;color:#fff}.vx-readonly{width:100%;min-height:38px;border:1px solid rgba(139,92,246,.18);border-radius:9px;padding:7px 8px;font-size:11px;background:rgba(255,255,255,.55);color:#374151}.dark .vx-readonly{background:rgba(17,24,39,.5);color:#e5e7eb}.vx-lab-fields{display:grid;grid-template-columns:repeat(5,1fr);gap:8px}.vx-product-row{display:grid;grid-template-columns:minmax(160px,1fr) 90px 110px 110px 40px;gap:7px;align-items:end;padding:8px 0;border-top:1px solid rgba(139,92,246,.12)}.vx-label{display:block;font-size:8px;text-transform:uppercase;letter-spacing:.05em;font-weight:800;color:#8b5cf6;margin-bottom:3px}.vx-calc-row{display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-top:1px solid rgba(139,92,246,.12);font-size:11px}.vx-formula{margin-top:10px;padding:9px;border-radius:9px;background:#ede9fe;color:#5b21b6;font-size:10px;line-height:1.4}.dark .vx-formula{background:#2e1065;color:#ddd6fe}.vx-sub{font-size:11px;color:#6b7280;margin-top:2px}.vx-pager{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 12px;border-top:1px solid #eef0f3}.dark .vx-pager{border-color:#263248}.vx-pager-links{display:flex;gap:5px}.vx-page{display:inline-flex;min-width:34px;height:34px;align-items:center;justify-content:center;border:1px solid #e5e7eb;border-radius:9px;font-size:11px;font-weight:800}.vx-page.active{background:#2563eb;border-color:#2563eb;color:#fff}.vx-inline-fix{width:100%}.vx-inline-fix .vx-btn,.vx-inline-fix .vx-inline-select{width:100%;max-width:none}.vx-show .vx-name{white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:38px}
@media(max-width:1100px){.vx-show-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:1000px){.vx-grid,.vx-lab{grid-template-columns:1fr}.vx-flow{grid-template-columns:repeat(3,1fr)}.vx-kpis{grid-template-columns:repeat(2,1fr)}.vx-money{grid-template-columns:repeat(3,1fr)}.vx-lab-fields{grid-template-columns:1fr 1fr}}
@media(max-width:640px){.vx-show-grid{grid-template-columns:1fr;padding:8px}.vx-pay{gap:10px}.vx-pad{padding:14px}.vx-actions{display:grid;grid-template-columns:1fr 1fr}.vx-btn{min-height:44px}.vx-flow{grid-template-columns:1fr 1fr}.vx-kpis{grid-template-columns:1fr 1fr}.vx-money{grid-template-columns:1fr 1fr}.vx-show-top{flex-direction:column}.vx-next{align-items:stretch;flex-direction:column}.vx-next .vx-btn{width:100%}.vx-sim-grid,.vx-lab-fields{grid-template-columns:1fr 1fr}.vx-product-row{grid-template-columns:1fr 1fr}.vx-product-row>div:first-child{grid-column:1/-1}.vx-product-row>button{grid-column:1/-1;width:100%}}
</style>

<div class="vx-pay">
    <section class="vx-card vx-pad vx-payroll-control">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-primary-600">Payroll Command Center</div>
                <h1 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ ($current?->week_start ?? now()->startOfWeek())->format('M j') }} – {{ ($current?->week_end ?? now()->endOfWeek())->format('M j, Y') }}</h1>
                <p class="mt-1 max-w-2xl text-xs text-gray-500">Fix issues, review the week, and move payroll forward from one screen.</p>
            </div>
            <div class="vx-actions">
                @if($current)
                    <a class="vx-btn primary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view',['record'=>$current]) }}">Continue This Week's Pay Run</a>
                @else
                    <button type="button" wire:click="prepareCurrentPayRun" wire:loading.attr="disabled" wire:target="prepareCurrentPayRun" class="vx-btn primary">
                        <span wire:loading.remove wire:target="prepareCurrentPayRun">Prepare This Week's Pay Run</span>
                        <span wire:loading wire:target="prepareCurrentPayRun">Preparing…</span>
                    </button>
                @endif
                <a class="vx-btn" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index') }}">History</a>
                <details class="relative">
                    <summary class="vx-btn cursor-pointer list-none">Settings</summary>
                    <div class="absolute right-0 z-20 mt-2 min-w-[190px] rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                        <a class="block rounded-lg px-3 py-2 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-800" href="{{ \App\Filament\Pages\PaymentStructures::getUrl() }}">Payment Structures</a>
                        <a class="block rounded-lg px-3 py-2 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-800" href="{{ \App\Filament\Pages\PayrollSimulator::getUrl() }}">Payroll Simulator</a>
                    </div>
                </details>
            </div>
        </div>
        <div class="vx-kpis">
            <div class="vx-kpi"><label>Payroll Total</label><strong>${{ number_format((float)($current?->total_payout ?? 0),2) }}</strong></div>
            <div class="vx-kpi"><label>People</label><strong>{{ $breakdown['people'] }}</strong></div>
            <div class="vx-kpi"><label>Ready / In Run / Paid</label><strong class="text-emerald-600">{{ $readiness['ready'] }}</strong></div>
            <div class="vx-kpi"><label>Blocked Shows</label><strong class="{{ $readiness['review'] ? 'text-amber-600' : 'text-emerald-600' }}">{{ $readiness['review'] }}</strong></div>
        </div>
    </section>

    <section class="vx-card vx-pad vx-payroll-statusbar">
        <div>
            <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="text-[10px] font-extrabold uppercase tracking-[.12em] text-gray-400">This pay period</div>
                    <div class="mt-1 text-sm font-bold text-gray-950 dark:text-white">Every show is here. Work the exceptions first; upcoming shows stay visible without counting as blocked.</div>
                </div>
                <a href="{{ $baseUrl }}" class="text-xs font-bold text-primary-600">All {{ $progress['total'] }} shows</a>
            </div>
            <div class="vx-flow mt-3">
                @foreach([
                    'needs_log'=>['Needs Log',$progress['needs_log']],
                    'review'=>['Needs Review',$progress['needs_review']],
                    'blocked'=>['Other Blockers',$workflow['blocked']],
                    'ready'=>['Ready',$workflow['ready']],
                    'in_run'=>['In Pay Run',$workflow['in_run']],
                    'upcoming'=>['Upcoming',$progress['upcoming']],
                ] as $key=>[$label,$count])
                    <a class="{{ $activeWorkflow===$key?'active':'' }}" href="{{ $baseUrl.'?workflow='.$key }}"><label>{{ $label }}</label><strong>{{ $count }}</strong></a>
                @endforeach
            </div>
            <div class="vx-period-summary">
                <span><b>{{ $progress['logged'] }}</b> logged</span>
                <span><b>{{ $progress['approved'] }}</b> approved</span>
                <span><b>{{ $workflow['paid'] }}</b> paid</span>
            </div>
        </div>
    </section>

    <div class="vx-grid">
        <section class="vx-card">
            <div class="vx-pad flex items-start justify-between gap-3 border-b border-gray-100 dark:border-gray-800"><div><h2 class="text-sm font-bold text-gray-950 dark:text-white">{{ $activeWorkflow==='all' ? 'All Shows in This Pay Period' : 'Filtered Pay-Period Shows' }}</h2><div class="vx-sub">Actionable shows are sorted first. Upcoming shows remain part of this week's run and move into the queue automatically.</div></div>@if($activeWorkflow!=='all')<a href="{{ $baseUrl }}" class="text-xs font-semibold text-primary-600">Clear filter</a>@endif</div>
            <div class="vx-show-grid">
                @forelse($shows as $show)
                    @php
                        $state=$show->getAttribute('workflow_state');
                        $pnl=$show->getAttribute('pnl_summary');
                        $probs=$show->getAttribute('payrun_problems')??[];
                        $resolution=$this->showResolution($show);
                        $key=$state['key']??'';
                        $due = ! $show->show_date || ! $show->show_date->isFuture();
                        if ($show->show_date?->isToday() && $show->start_time?->isFuture()) $due = false;
                        $tone=!$due?'upcoming':($probs!==[]?'blocked':($key==='paid'?'ready':($key==='payroll'?'run':($key==='payroll_ready'?'ready':''))));
                        $nextText = !$due ? 'Upcoming — stays in this pay period and will require a streamer log after the show.' : ($probs[0] ?? ($state['blockers'][0] ?? $state['description'] ?? 'Ready for payroll review.'));
                    @endphp
                    <article class="vx-show {{ $tone==='blocked'?'blocked-card':($tone==='upcoming'?'upcoming-card':'') }}">
                        <div class="vx-show-top">
                            <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="vx-chip {{ $tone }}">{{ $state['label'] ?? ucfirst(str_replace('_',' ',$key)) }}</span><span class="text-[10px] text-gray-400">{{ $show->show_date?->format('M j') }}</span></div><a href="{{ \App\Filament\Resources\ShowResource::getUrl('view',['record'=>$show]) }}" class="vx-name mt-2 block truncate">{{ $show->title ?: 'Show #'.$show->id }}</a><div class="vx-meta">{{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer assigned' }}</div></div>
                            <div class="text-right"><div class="text-[9px] font-bold uppercase tracking-wide text-gray-400">Show Net</div><div class="mt-1 text-lg font-bold {{ ($pnl['margin']??0)<0?'text-red-600':'text-emerald-600' }}">${{ number_format((float)($pnl['margin']??0),0) }}</div></div>
                        </div>
                        <div class="vx-money"><div><label>Sales</label><strong>${{ number_format((float)($pnl['gross']??0),0) }}</strong></div><div><label>Whatnot Net</label><strong>${{ number_format((float)($pnl['net']??0),0) }}</strong></div><div><label>COGS</label><strong>${{ number_format((float)($pnl['cogs']??0),0) }}</strong></div><div><label>Payroll</label><strong>${{ number_format((float)($pnl['payouts']??0),0) }}</strong></div><div><label>Margin</label><strong>{{ number_format((float)($pnl['margin_pct']??0),1) }}%</strong></div></div>
                        <div class="vx-next">
                            <div class="min-w-0 flex-1"><div class="text-[9px] font-bold uppercase tracking-[.1em] text-gray-400">Next step</div><div class="mt-1 text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $nextText }}</div></div>
                            <div class="vx-inline-fix">
                                @if($show->streamers->isEmpty())
                                    <select class="vx-inline-select" aria-label="Assign streamer to {{ $show->title }}" onchange="if(this.value){ $wire.assignStreamerToShow({{ $show->id }}, Number(this.value)); this.value=''; }">
                                        <option value="">Assign streamer…</option>
                                        @foreach($this->streamerOptions() as $streamerId=>$streamerName)<option value="{{ $streamerId }}">{{ $streamerName }}</option>@endforeach
                                    </select>
                                @elseif($show->streamerLogEntry?->isSubmitted() && $show->streamerLogEntry?->approval_status !== 'approved')
                                    <button type="button" wire:click="approveReportInline({{ $show->id }})" wire:confirm="Approve this streamer report?" class="vx-btn success">Approve Report</button>
                                @elseif(str_contains(strtolower($nextText), 'payment structure') || str_contains(strtolower($nextText), 'compensation') || str_contains(strtolower($nextText), 'pay rate'))
                                    <a class="vx-btn" href="{{ \App\Filament\Resources\StreamerResource::getUrl('edit',['record'=>$show->streamers->first()]) }}">Fix Pay Setup</a>
                                @else
                                    <a class="vx-btn {{ $resolution['tone']==='primary'?'primary':($resolution['tone']==='success'?'success':'') }}" href="{{ $resolution['url'] }}">{{ $resolution['label'] }}</a>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty<div class="p-8 text-center text-sm text-gray-500">No shows in this pay period.</div>@endforelse
            </div>

            @if($totalFilteredShows > 0)
                <div class="vx-pager">
                    <div class="text-[11px] text-gray-500">Showing {{ (($page-1)*$perPage)+1 }}–{{ min($page*$perPage,$totalFilteredShows) }} of {{ $totalFilteredShows }}</div>
                    @if($lastPage > 1)
                        <div class="vx-pager-links">
                            @if($page>1)<a class="vx-page" href="{{ request()->fullUrlWithQuery(['page'=>$page-1]) }}">‹</a>@endif
                            @for($p=max(1,$page-1);$p<=min($lastPage,$page+1);$p++)<a class="vx-page {{ $p===$page?'active':'' }}" href="{{ request()->fullUrlWithQuery(['page'=>$p]) }}">{{ $p }}</a>@endfor
                            @if($page<$lastPage)<a class="vx-page" href="{{ request()->fullUrlWithQuery(['page'=>$page+1]) }}">›</a>@endif
                        </div>
                    @endif
                </div>
            @endif
        </section>
    </div>


</div>
</x-filament-panels::page>