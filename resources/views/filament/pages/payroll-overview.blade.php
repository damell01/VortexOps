<x-filament-panels::page>
@php
    $current = $this->currentPayRun();
    $attention = $this->needsAttention();
    $breakdown = $this->currentBreakdown();
    $recent = $this->recentPayRuns();
    $shows = $this->currentWeekShows();
    $readiness = $this->readinessSummary();
    $workflow = $this->workflowBreakdown();
    $activeWorkflow = request()->string('workflow')->toString() ?: 'all';
    $baseUrl = \App\Filament\Pages\PayrollOverview::getUrl();
@endphp
<style>
.vx-pay{max-width:1440px;margin:0 auto;display:grid;gap:14px}.vx-card{border:1px solid #e5e7eb;background:#fff;border-radius:16px;box-shadow:0 1px 2px rgba(15,23,42,.04)}.dark .vx-card{border-color:#263248;background:#101827}.vx-hero{padding:18px 20px}.vx-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));border-top:1px solid #eef0f3;margin-top:16px}.dark .vx-kpis{border-color:#263248}.vx-kpi{padding:14px 15px;border-right:1px solid #eef0f3;min-width:0}.dark .vx-kpi{border-color:#263248}.vx-kpi:last-child{border-right:0}.vx-kpi label{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.05em;font-weight:800;color:#9ca3af}.vx-kpi strong{display:block;margin-top:4px;font-size:19px;color:#111827;white-space:nowrap}.dark .vx-kpi strong{color:#fff}.vx-actions{display:flex;flex-wrap:wrap;gap:8px}.vx-btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;border-radius:10px;padding:8px 12px;font-size:11px;font-weight:800;border:1px solid #d1d5db;color:#374151;background:#fff}.dark .vx-btn{border-color:#475569;color:#e5e7eb;background:#111827}.vx-btn.primary{background:#2563eb;border-color:#2563eb;color:#fff}.vx-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(300px,.55fr);gap:14px}.vx-section{padding:18px}.vx-section h3{font-size:14px;font-weight:800;color:#111827}.dark .vx-section h3{color:#fff}.vx-sub{font-size:11px;color:#6b7280;margin-top:2px}.vx-alert{display:flex;gap:8px;border-radius:11px;background:#fff7ed;color:#9a3412;padding:9px 11px;font-size:11px;margin-top:7px}.dark .vx-alert{background:rgba(154,52,18,.16);color:#fdba74}.vx-ok{border-radius:11px;background:#ecfdf5;color:#047857;padding:10px 11px;font-size:11px;font-weight:750;margin-top:9px}.vx-filters{display:flex;gap:6px;overflow-x:auto;margin-top:12px}.vx-filter{display:inline-flex;gap:5px;align-items:center;white-space:nowrap;border:1px solid #e5e7eb;border-radius:999px;padding:6px 9px;font-size:10px;font-weight:800;color:#6b7280}.dark .vx-filter{border-color:#374151;color:#d1d5db}.vx-filter.active{background:#111827;color:#fff;border-color:#111827}.dark .vx-filter.active{background:#fff;color:#111827;border-color:#fff}.vx-show{display:grid;grid-template-columns:minmax(230px,1.25fr) 125px repeat(5,minmax(78px,.5fr)) 115px;gap:8px;align-items:center;padding:11px 12px;border-top:1px solid #f1f3f5;font-size:11px}.dark .vx-show{border-color:#1f2937}.vx-show.head{background:#f8fafc;border-top:0;font-size:9px;text-transform:uppercase;letter-spacing:.05em;font-weight:800;color:#94a3b8}.dark .vx-show.head{background:#1f2937}.vx-name{font-size:12px;font-weight:800;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.dark .vx-name{color:#fff}.vx-meta{font-size:10px;color:#6b7280;margin-top:2px}.vx-num{text-align:right;font-variant-numeric:tabular-nums}.vx-chip{display:inline-flex;border-radius:999px;padding:4px 7px;font-size:9px;font-weight:800;background:#f3f4f6;color:#4b5563}.dark .vx-chip{background:#1f2937;color:#d1d5db}.vx-chip.ready{background:#ecfdf5;color:#047857}.vx-chip.blocked{background:#fff7ed;color:#c2410c}.vx-chip.run{background:#eff6ff;color:#1d4ed8}.vx-link{display:inline-flex;align-items:center;justify-content:center;min-height:34px;border-radius:9px;border:1px solid #d1d5db;padding:6px 8px;font-size:10px;font-weight:800;color:#374151}.dark .vx-link{border-color:#475569;color:#e5e7eb}.vx-runrow{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 0;border-top:1px solid #f1f3f5}.dark .vx-runrow{border-color:#1f2937}.vx-runrow strong{font-size:12px}.vx-runrow span{font-size:10px;color:#6b7280}.vx-totalbox{border-radius:14px;background:#eff6ff;padding:15px}.dark .vx-totalbox{background:#172554}.vx-totalbox strong{display:block;font-size:28px;color:#1d4ed8}.dark .vx-totalbox strong{color:#93c5fd}
@media(max-width:1000px){.vx-kpis{grid-template-columns:repeat(3,1fr)}.vx-grid{grid-template-columns:1fr}.vx-show{grid-template-columns:1fr 1fr}.vx-show.head{display:none}.vx-show>div:first-child{grid-column:1/-1}.vx-show>div:nth-child(n+3):nth-child(-n+7){background:#f8fafc;border-radius:8px;padding:7px;text-align:left}.dark .vx-show>div:nth-child(n+3):nth-child(-n+7){background:#1f2937}.vx-show>div:last-child{grid-column:1/-1}.vx-link{width:100%;min-height:40px}}
@media(max-width:640px){.vx-pay{gap:10px}.vx-hero,.vx-section{padding:14px}.vx-kpis{grid-template-columns:1fr 1fr;margin-inline:-14px;margin-bottom:-14px}.vx-kpi:nth-child(2n){border-right:0}.vx-kpi strong{font-size:17px}.vx-actions{display:grid;grid-template-columns:1fr 1fr;width:100%}.vx-btn{min-height:44px}.vx-show{margin-top:8px;border:1px solid #e5e7eb;border-radius:12px;padding:11px}.dark .vx-show{border-color:#374151}.vx-show>div:nth-child(2){grid-column:1/-1}.vx-title{font-size:20px!important}}
</style>
<div class="vx-pay">
    <section class="vx-card vx-hero">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><div class="text-xs font-bold uppercase tracking-wide text-primary-600">Current weekly payroll</div><h1 class="vx-title mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ ($current?->week_start ?? now()->startOfWeek())->format('M j') }} – {{ ($current?->week_end ?? now()->endOfWeek())->format('M j, Y') }}</h1><p class="mt-1 text-xs text-gray-500">Resolve show blockers, review calculated pay, then finalize one weekly run.</p></div>
            <div class="vx-actions">
                @if($current)<a class="vx-btn primary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view',['record'=>$current]) }}">Review Current Pay Run</a>@else<a class="vx-btn primary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('create') }}">Create Pay Run</a>@endif
                <a class="vx-btn" href="{{ \App\Filament\Pages\PaymentStructures::getUrl() }}">Payment Structures</a>
                <a class="vx-btn" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index') }}">History</a>
            </div>
        </div>
        <div class="vx-kpis">
            <div class="vx-kpi"><label>Payroll Total</label><strong>${{ number_format((float)($current?->total_payout ?? 0),2) }}</strong></div>
            <div class="vx-kpi"><label>People</label><strong>{{ $breakdown['people'] }}</strong></div>
            <div class="vx-kpi"><label>Streamer Pay</label><strong>${{ number_format($breakdown['streamer_total'],0) }}</strong></div>
            <div class="vx-kpi"><label>Fulfillment Pay</label><strong>${{ number_format($breakdown['fulfillment_total'],0) }}</strong></div>
            <div class="vx-kpi"><label>Ready / In Run</label><strong class="text-emerald-600">{{ $readiness['ready'] }}</strong></div>
            <div class="vx-kpi"><label>Blocked</label><strong class="{{ $readiness['review'] ? 'text-amber-600' : 'text-emerald-600' }}">{{ $readiness['review'] }}</strong></div>
        </div>
    </section>

    <div class="vx-grid">
        <section class="vx-card vx-section">
            <div class="flex items-start justify-between gap-3"><div><h3>Shows in This Pay Period</h3><div class="vx-sub">Fix the workflow blocker from here instead of hunting through separate pages.</div></div><a href="{{ \App\Filament\Resources\ShowResource::getUrl('index') }}" class="text-xs font-semibold text-primary-600">All Shows →</a></div>
            <div class="vx-filters">@foreach(['all'=>['All',$workflow['all']],'blocked'=>['Blocked',$workflow['blocked']],'ready'=>['Ready',$workflow['ready']],'in_run'=>['In Run',$workflow['in_run']],'paid'=>['Paid',$workflow['paid']]] as $key=>[$label,$count])<a class="vx-filter {{ $activeWorkflow===$key?'active':'' }}" href="{{ $key==='all'?$baseUrl:$baseUrl.'?workflow='.$key }}">{{ $label }} <span>{{ $count }}</span></a>@endforeach</div>
            <div class="mt-3 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="vx-show head"><div>Show</div><div>Status</div><div class="vx-num">Sales</div><div class="vx-num">Whatnot Net</div><div class="vx-num">COGS</div><div class="vx-num">Payroll</div><div class="vx-num">Show Net</div><div>Action</div></div>
                @forelse($shows as $show)
                    @php $state=$show->getAttribute('workflow_state'); $pnl=$show->getAttribute('pnl_summary'); $probs=$show->getAttribute('payrun_problems')??[]; $resolution=$this->showResolution($show); $key=$state['key']??''; $tone=$probs!==[]?'blocked':($key==='paid'?'ready':($key==='payroll'?'run':($key==='payroll_ready'?'ready':''))); @endphp
                    <div class="vx-show">
                        <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view',['record'=>$show]) }}" class="min-w-0"><div class="vx-name">{{ $show->title ?: 'Show #'.$show->id }}</div><div class="vx-meta">{{ $show->show_date?->format('M j') }} · {{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer assigned' }}@if($probs!==[]) · {{ count($probs) }} blocker(s)@endif</div></a>
                        <div><span class="vx-chip {{ $tone }}">{{ $state['label'] ?? ucfirst(str_replace('_',' ',$key)) }}</span></div>
                        <div class="vx-num">${{ number_format((float)($pnl['gross']??0),0) }}</div><div class="vx-num">${{ number_format((float)($pnl['net']??0),0) }}</div><div class="vx-num">${{ number_format((float)($pnl['cogs']??0),0) }}</div><div class="vx-num">${{ number_format((float)($pnl['payouts']??0),0) }}</div><div class="vx-num font-bold {{ ($pnl['margin']??0)<0?'text-red-600':'text-emerald-600' }}">${{ number_format((float)($pnl['margin']??0),0) }}</div>
                        <div><a class="vx-link" href="{{ $resolution['url'] }}">{{ $resolution['label'] }}</a></div>
                    </div>
                @empty<div class="p-8 text-center text-sm text-gray-500">No shows in this pay period.</div>@endforelse
            </div>
        </section>

        <aside class="space-y-3">
            <section class="vx-card vx-section">
                <h3>Run Readiness</h3><div class="vx-sub">A draft should only be finalized when there are no blockers.</div>
                @forelse($attention as $warning)<div class="vx-alert"><strong>!</strong><span>{{ $warning }}</span></div>@empty<div class="vx-ok">✓ Current week is clear of payroll readiness issues.</div>@endforelse
            </section>
            <section class="vx-card vx-section">
                <h3>Current Run</h3><div class="vx-sub">At-a-glance weekly total and next action.</div>
                <div class="vx-totalbox mt-3"><span class="text-[10px] font-bold uppercase tracking-wide text-blue-600 dark:text-blue-300">Total payroll</span><strong>${{ number_format((float)($current?->total_payout ?? 0),2) }}</strong><div class="mt-1 text-xs text-blue-700 dark:text-blue-200">{{ $current ? (\App\Models\WeeklyPayoutBatch::statusLabels()[$current->status] ?? ucfirst($current->status)) : 'No run created' }}</div></div>
                @if($current)<a class="vx-btn primary mt-3 w-full" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view',['record'=>$current]) }}">Open Current Pay Run</a>@endif
            </section>
            <section class="vx-card vx-section"><h3>Recent Pay Runs</h3>@forelse($recent as $run)<a href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view',['record'=>$run]) }}" class="vx-runrow"><div><strong>{{ $run->week_start?->format('M j') }} – {{ $run->week_end?->format('M j') }}</strong><br><span>{{ \App\Models\WeeklyPayoutBatch::statusLabels()[$run->status] ?? ucfirst($run->status) }} · {{ $run->payouts_count }} entries</span></div><strong>${{ number_format((float)$run->total_payout,2) }}</strong></a>@empty<div class="py-5 text-center text-xs text-gray-500">No prior pay runs.</div>@endforelse</section>
        </aside>
    </div>
</div>
</x-filament-panels::page>
