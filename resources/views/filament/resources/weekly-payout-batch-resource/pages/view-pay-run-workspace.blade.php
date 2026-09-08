<x-filament-panels::page>
@php
    $run = $this->record;
    $run->loadMissing(['payouts.streamer','payouts.show','createdBy','finalizedBy']);
    $payouts = $run->payouts;
    $problems = $run->status === 'draft' ? app(\App\Services\PayRunReadinessService::class)->problems($run) : [];
    $byPerson = $payouts->groupBy('streamer_id');
    $streamerTotal = (float)$payouts->filter(fn($p)=>!$p->streamer?->isFulfillment())->sum('calculated_payout');
    $fulfillmentTotal = (float)$payouts->filter(fn($p)=>$p->streamer?->isFulfillment())->sum('calculated_payout');
    $statusLabel = \App\Models\WeeklyPayoutBatch::statusLabels()[$run->status] ?? ucfirst($run->status);
    $statusSteps = ['draft'=>'Review','finalized'=>'Finalized','submitted_to_adp'=>'Submitted','paid'=>'Paid'];
    $statusKeys = array_keys($statusSteps);
    $statusIndex = array_search($run->status,$statusKeys,true);
    $statusIndex = $statusIndex === false ? 0 : $statusIndex;
    $showCount = $payouts->pluck('show_id')->filter()->unique()->count();
    $nextAction = match ($run->status) {
        'draft' => $problems !== [] ? ['label'=>'Resolve blockers','detail'=>count($problems).' issue'.(count($problems)===1?'':'s').' must be cleared before finalizing.','tone'=>'warning','url'=>\App\Filament\Pages\PayrollOverview::getUrl(['workflow'=>'blocked'])] : ['label'=>'Finalize pay run','detail'=>'All readiness checks are clear. Finalize to lock payout amounts.','tone'=>'success','url'=>null],
        'finalized' => ['label'=>'Submit to ADP','detail'=>'Payout amounts are locked. Export the CSV and submit this run to ADP.','tone'=>'primary','url'=>null],
        'submitted_to_adp' => ['label'=>'Mark paid','detail'=>'ADP submission is recorded. Mark the run paid once payment is confirmed.','tone'=>'success','url'=>null],
        'paid' => ['label'=>'Pay run complete','detail'=>'This run is paid and team balances have been updated.','tone'=>'success','url'=>null],
        default => ['label'=>$statusLabel,'detail'=>'Review this pay run.','tone'=>'gray','url'=>null],
    };
@endphp
<style>
.vx-run{max-width:1420px;margin:0 auto;display:grid;gap:14px}.vx-card{border:1px solid #e5e7eb;background:#fff;border-radius:18px;box-shadow:0 1px 2px rgba(15,23,42,.04);overflow:hidden}.dark .vx-card{border-color:#263248;background:#101827}.vx-pad{padding:18px 20px}.vx-chip{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:10px;font-weight:800;background:#f3f4f6;color:#4b5563}.dark .vx-chip{background:#1f2937;color:#d1d5db}.vx-chip.ready{background:#ecfdf5;color:#047857}.vx-chip.warn{background:#fff7ed;color:#c2410c}.vx-chip.run{background:#eff6ff;color:#1d4ed8}.vx-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:16px}.vx-kpi{padding:12px;border-radius:14px;background:#f8fafc}.dark .vx-kpi{background:#1f2937}.vx-kpi label{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.07em;font-weight:800;color:#9ca3af}.vx-kpi strong{display:block;margin-top:4px;font-size:20px;color:#111827}.dark .vx-kpi strong{color:#fff}.vx-flow{display:grid;grid-template-columns:repeat(4,1fr);gap:0;padding:14px 18px}.vx-step{position:relative;text-align:center;font-size:10px;font-weight:800;color:#9ca3af}.vx-step:before{content:'';position:absolute;top:11px;left:-50%;right:50%;height:2px;background:#e5e7eb}.vx-step:first-child:before{display:none}.vx-dot{position:relative;z-index:1;margin:0 auto 5px;width:24px;height:24px;border-radius:99px;background:#f3f4f6;display:grid;place-items:center}.vx-step.done,.vx-step.current{color:#2563eb}.vx-step.done .vx-dot,.vx-step.current .vx-dot{background:#dbeafe;color:#2563eb}.vx-step.done:before,.vx-step.current:before{background:#93c5fd}.vx-next{display:flex;align-items:center;justify-content:space-between;gap:14px;border-radius:14px;padding:13px 14px}.vx-next.warning{background:#fff7ed;color:#9a3412}.vx-next.success{background:#ecfdf5;color:#047857}.vx-next.primary{background:#eff6ff;color:#1d4ed8}.dark .vx-next.warning{background:rgba(154,52,18,.16);color:#fdba74}.dark .vx-next.success{background:rgba(4,120,87,.15);color:#6ee7b7}.dark .vx-next.primary{background:#172554;color:#93c5fd}.vx-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(300px,.55fr);gap:14px}.vx-section{padding:18px}.vx-section h3{font-size:14px;font-weight:800;color:#111827}.dark .vx-section h3{color:#fff}.vx-sub{font-size:11px;color:#6b7280;margin-top:2px}.vx-person{border-top:1px solid #f1f3f5;padding:14px 0}.dark .vx-person{border-color:#1f2937}.vx-person:first-of-type{border-top:0}.vx-person-head{display:grid;grid-template-columns:minmax(190px,1fr) repeat(4,minmax(82px,.45fr)) 24px;gap:10px;align-items:center}.vx-value{font-size:13px;font-weight:800;color:#111827}.dark .vx-value{color:#fff}.vx-meta{font-size:10px;color:#6b7280;margin-top:2px}.vx-num{text-align:right;font-variant-numeric:tabular-nums}.vx-breakdown{margin-top:10px;border:1px solid #eef0f3;border-radius:12px;overflow:hidden}.dark .vx-breakdown{border-color:#263248}.vx-payrow{display:grid;grid-template-columns:minmax(190px,1.3fr) repeat(5,minmax(74px,.55fr));gap:8px;padding:9px 11px;border-top:1px solid #f3f4f6;font-size:11px;align-items:center}.dark .vx-payrow{border-color:#1f2937}.vx-payrow:first-child{border-top:0}.vx-alert{border-radius:12px;background:#fff7ed;color:#9a3412;padding:10px 12px;font-size:11px;margin-top:8px}.dark .vx-alert{background:rgba(154,52,18,.16);color:#fdba74}.vx-ok{border-radius:12px;background:#ecfdf5;color:#047857;padding:11px 12px;font-size:12px;font-weight:750}.vx-fin{display:grid;grid-template-columns:1fr auto;gap:8px;padding:8px 0;font-size:11px;border-top:1px solid #f3f4f6}.dark .vx-fin{border-color:#1f2937}.vx-link{display:inline-flex;align-items:center;justify-content:center;min-height:40px;border-radius:11px;border:1px solid #d1d5db;padding:7px 11px;font-size:11px;font-weight:800;color:#374151}.dark .vx-link{border-color:#475569;color:#e5e7eb}.vx-link.primary{background:#2563eb;border-color:#2563eb;color:#fff}.vx-money-card{border-radius:16px;background:#eff6ff;padding:15px}.dark .vx-money-card{background:#172554}.vx-money-card strong{display:block;margin-top:3px;font-size:30px;color:#1d4ed8}.dark .vx-money-card strong{color:#93c5fd}
@media(max-width:950px){.vx-grid{grid-template-columns:1fr}.vx-kpis{grid-template-columns:repeat(2,1fr)}.vx-person-head{grid-template-columns:1fr 1fr}.vx-person-head>div:first-child{grid-column:1/-1}.vx-person-head>div:last-child{display:none}.vx-breakdown{overflow-x:auto}.vx-payrow{min-width:760px}}
@media(max-width:640px){.vx-run{gap:10px}.vx-pad,.vx-section{padding:14px}.vx-flow{padding:12px 8px}.vx-step{font-size:9px}.vx-kpis{grid-template-columns:1fr 1fr}.vx-person{border:1px solid #e5e7eb;border-radius:14px;padding:11px;margin-top:9px}.dark .vx-person{border-color:#374151}.vx-person-head>div:not(:first-child){border-radius:9px;background:#f8fafc;padding:8px;text-align:left}.dark .vx-person-head>div:not(:first-child){background:#1f2937}.vx-next{align-items:stretch;flex-direction:column}.vx-next .vx-link{width:100%}}
</style>

<div class="vx-run">
    <section class="vx-card vx-pad">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-primary-600">Pay Run Workspace</div>
                <h1 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $run->week_start?->format('M j') }} – {{ $run->week_end?->format('M j, Y') }}</h1>
                <div class="mt-2 flex flex-wrap gap-2"><span class="vx-chip {{ $run->status==='paid'?'ready':($run->status==='draft'&&count($problems)?'warn':'run') }}">{{ $statusLabel }}</span><span class="vx-chip">{{ $byPerson->count() }} people</span><span class="vx-chip">{{ $showCount }} shows</span><span class="vx-chip">{{ $payouts->count() }} payout entries</span></div>
            </div>
            <a href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}" class="vx-link">← Payroll Command Center</a>
        </div>
        <div class="vx-kpis">
            <div class="vx-kpi"><label>Total Payroll</label><strong>${{ number_format((float)$run->total_payout,2) }}</strong></div>
            <div class="vx-kpi"><label>Streamer Pay</label><strong>${{ number_format($streamerTotal,2) }}</strong></div>
            <div class="vx-kpi"><label>Fulfillment Pay</label><strong>${{ number_format($fulfillmentTotal,2) }}</strong></div>
            <div class="vx-kpi"><label>Blockers</label><strong class="{{ count($problems) ? 'text-amber-600' : 'text-emerald-600' }}">{{ count($problems) }}</strong></div>
        </div>
    </section>

    <section class="vx-card">
        <div class="vx-flow">
            @foreach($statusSteps as $key=>$label) @php $i=array_search($key,$statusKeys,true); @endphp
                <div class="vx-step {{ $i < $statusIndex ? 'done' : ($i === $statusIndex ? 'current' : '') }}"><div class="vx-dot">{{ $i < $statusIndex ? '✓' : $i+1 }}</div>{{ $label }}</div>
            @endforeach
        </div>
        <div class="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5">
            <div class="vx-next {{ $nextAction['tone'] }}">
                <div><div class="text-[9px] font-bold uppercase tracking-[.12em] opacity-70">Next action</div><div class="mt-1 text-sm font-bold">{{ $nextAction['label'] }}</div><div class="mt-1 text-[11px] opacity-90">{{ $nextAction['detail'] }}</div></div>
                @if($nextAction['url'])<a href="{{ $nextAction['url'] }}" class="vx-link primary">Resolve Now</a>@endif
            </div>
        </div>
    </section>

    <div class="vx-grid">
        <section class="vx-card vx-section">
            <div class="flex items-start justify-between gap-3"><div><h3>People in This Pay Run</h3><div class="vx-sub">Person total first. Expand only when you need the lines that built it.</div></div><span class="vx-chip">{{ $byPerson->count() }} people</span></div>
            @forelse($byPerson as $streamerId=>$rows)
                @php $person=$rows->first()->streamer; $personTotal=(float)$rows->sum('calculated_payout'); $hours=(float)$rows->sum('hours_worked'); $labels=(int)$rows->sum('label_count'); $pwe=(int)$rows->sum('pwe_count'); @endphp
                <details class="vx-person">
                    <summary class="vx-person-head cursor-pointer list-none">
                        <div><div class="flex flex-wrap items-center gap-2"><div class="vx-value">{{ $person?->name ?? 'Unassigned team member' }}</div><span class="vx-chip {{ $person?->isFulfillment() ? 'run' : 'ready' }}">{{ $person?->isFulfillment() ? 'Fulfillment' : 'Streamer' }}</span></div><div class="vx-meta">{{ $rows->count() }} payout line{{ $rows->count()===1?'':'s' }}</div></div>
                        <div class="vx-num"><div class="vx-meta">Hours</div><div class="vx-value">{{ number_format($hours,2) }}</div></div>
                        <div class="vx-num"><div class="vx-meta">Labels / PWE</div><div class="vx-value">{{ $labels }} / {{ $pwe }}</div></div>
                        <div class="vx-num"><div class="vx-meta">Shows</div><div class="vx-value">{{ $rows->pluck('show_id')->filter()->unique()->count() }}</div></div>
                        <div class="vx-num"><div class="vx-meta">Payout</div><div class="vx-value text-emerald-600">${{ number_format($personTotal,2) }}</div></div>
                        <div class="text-gray-400">⌄</div>
                    </summary>
                    <div class="vx-breakdown">
                        @foreach($rows as $payout)<div class="vx-payrow"><div><div class="vx-value truncate">{{ $payout->show?->title ?? 'General payroll line' }}</div><div class="vx-meta">{{ \App\Models\Streamer::payoutTypeLabels()[$payout->payout_type] ?? ucfirst(str_replace('_',' ',$payout->payout_type ?? '')) }}</div></div><div class="vx-num"><span class="vx-meta">Hours</span><br>{{ number_format((float)$payout->hours_worked,2) }}</div><div class="vx-num"><span class="vx-meta">Shipments</span><br>{{ number_format((int)$payout->shipments_count) }}</div><div class="vx-num"><span class="vx-meta">Labels</span><br>{{ number_format((int)$payout->label_count) }}</div><div class="vx-num"><span class="vx-meta">PWE</span><br>{{ number_format((int)$payout->pwe_count) }}</div><div class="vx-num"><strong>${{ number_format((float)$payout->calculated_payout,2) }}</strong></div></div>@endforeach
                    </div>
                </details>
            @empty<div class="py-10 text-center text-sm text-gray-500">No payout entries are attached to this run yet.</div>@endforelse
        </section>

        <aside class="space-y-3">
            <section class="vx-card vx-section"><div class="flex items-center justify-between gap-3"><div><h3>Readiness</h3><div class="vx-sub">What prevents this run from moving forward.</div></div><span class="vx-chip {{ count($problems)?'warn':'ready' }}">{{ count($problems) }} issue{{ count($problems)===1?'':'s' }}</span></div><div class="mt-3">@forelse($problems as $problem)<div class="vx-alert">{{ $problem }}</div>@empty<div class="vx-ok">✓ This pay run has no readiness blockers.</div>@endforelse</div></section>
            <section class="vx-card vx-section"><h3>Run Total</h3><div class="vx-money-card mt-3"><span class="text-[9px] font-bold uppercase tracking-wide text-blue-600 dark:text-blue-300">Total payroll</span><strong>${{ number_format((float)$run->total_payout,2) }}</strong><div class="mt-3 grid grid-cols-2 gap-2 text-xs"><div><span class="text-blue-600/70 dark:text-blue-300/70">Streamer</span><div class="font-bold text-blue-900 dark:text-blue-100">${{ number_format($streamerTotal,2) }}</div></div><div><span class="text-blue-600/70 dark:text-blue-300/70">Fulfillment</span><div class="font-bold text-blue-900 dark:text-blue-100">${{ number_format($fulfillmentTotal,2) }}</div></div></div></div></section>
            <section class="vx-card vx-section"><details><summary class="cursor-pointer list-none"><div class="flex items-center justify-between"><div><h3>Run Details</h3><div class="vx-sub">Audit and administrative details.</div></div><span class="text-xs font-bold text-primary-600">View</span></div></summary><div class="mt-3">@foreach([['Status',$statusLabel],['Created by',$run->createdBy?->name],['Finalized by',$run->finalizedBy?->name],['Finalized at',$run->finalized_at?->format('M j, Y g:i A')],['Notes',$run->notes]] as [$label,$value])<div class="vx-fin"><span class="text-gray-500">{{ $label }}</span><strong class="text-right">{{ $value ?: '—' }}</strong></div>@endforeach</div></details></section>
        </aside>
    </div>
</div>
</x-filament-panels::page>
