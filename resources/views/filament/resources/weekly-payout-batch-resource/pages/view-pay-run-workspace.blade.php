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
    $statusKeys = array_keys($statusSteps); $statusIndex = array_search($run->status,$statusKeys,true); $statusIndex = $statusIndex === false ? 0 : $statusIndex;
@endphp
<style>
.vx-run{max-width:1420px;margin:0 auto;display:grid;gap:14px}.vx-card{border:1px solid #e5e7eb;background:#fff;border-radius:16px;box-shadow:0 1px 2px rgba(15,23,42,.04)}.dark .vx-card{border-color:#263248;background:#101827}.vx-hero{padding:18px 20px}.vx-chip{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:750;background:#f3f4f6;color:#4b5563}.dark .vx-chip{background:#1f2937;color:#d1d5db}.vx-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));border-top:1px solid #eef0f3;margin-top:16px}.dark .vx-kpis{border-color:#263248}.vx-kpi{padding:14px 16px;border-right:1px solid #eef0f3}.dark .vx-kpi{border-color:#263248}.vx-kpi:last-child{border-right:0}.vx-kpi label{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.06em;font-weight:800;color:#9ca3af}.vx-kpi strong{display:block;margin-top:4px;font-size:20px;color:#111827}.dark .vx-kpi strong{color:#fff}.vx-flow{display:grid;grid-template-columns:repeat(4,1fr);gap:0;padding:14px 18px}.vx-step{position:relative;text-align:center;font-size:11px;font-weight:750;color:#9ca3af}.vx-step:before{content:'';position:absolute;top:11px;left:-50%;right:50%;height:2px;background:#e5e7eb}.vx-step:first-child:before{display:none}.vx-dot{position:relative;z-index:1;margin:0 auto 5px;width:24px;height:24px;border-radius:99px;background:#f3f4f6;display:grid;place-items:center}.vx-step.done,.vx-step.current{color:#2563eb}.vx-step.done .vx-dot,.vx-step.current .vx-dot{background:#dbeafe;color:#2563eb}.vx-step.done:before,.vx-step.current:before{background:#93c5fd}.vx-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(290px,.65fr);gap:14px}.vx-section{padding:18px}.vx-section h3{font-size:14px;font-weight:800;color:#111827}.dark .vx-section h3{color:#fff}.vx-sub{font-size:11px;color:#6b7280;margin-top:2px}.vx-person{border-top:1px solid #f1f3f5;padding:13px 0}.dark .vx-person{border-color:#1f2937}.vx-person-head{display:grid;grid-template-columns:minmax(180px,1fr) repeat(4,minmax(80px,.45fr)) 24px;gap:10px;align-items:center}.vx-value{font-size:13px;font-weight:750;color:#111827}.dark .vx-value{color:#fff}.vx-meta{font-size:11px;color:#6b7280;margin-top:2px}.vx-num{text-align:right;font-variant-numeric:tabular-nums}.vx-breakdown{margin-top:10px;border:1px solid #eef0f3;border-radius:12px;overflow:hidden}.dark .vx-breakdown{border-color:#263248}.vx-payrow{display:grid;grid-template-columns:minmax(190px,1.3fr) repeat(5,minmax(74px,.55fr));gap:8px;padding:9px 11px;border-top:1px solid #f3f4f6;font-size:11px;align-items:center}.dark .vx-payrow{border-color:#1f2937}.vx-payrow:first-child{border-top:0}.vx-alert{border-radius:12px;background:#fff7ed;color:#9a3412;padding:10px 12px;font-size:11px;margin-top:8px}.dark .vx-alert{background:rgba(154,52,18,.16);color:#fdba74}.vx-ok{border-radius:12px;background:#ecfdf5;color:#047857;padding:11px 12px;font-size:12px;font-weight:750}.vx-fin{display:grid;grid-template-columns:1fr auto;gap:8px;padding:8px 0;font-size:12px;border-top:1px solid #f3f4f6}.dark .vx-fin{border-color:#1f2937}.vx-link{display:inline-flex;align-items:center;justify-content:center;min-height:38px;border-radius:10px;border:1px solid #d1d5db;padding:7px 10px;font-size:11px;font-weight:750;color:#374151}.dark .vx-link{border-color:#475569;color:#e5e7eb}.vx-link.primary{background:#2563eb;border-color:#2563eb;color:#fff}
@media(max-width:950px){.vx-grid{grid-template-columns:1fr}.vx-kpis{grid-template-columns:repeat(2,1fr)}.vx-kpi:nth-child(2n){border-right:0}.vx-person-head{grid-template-columns:1fr 1fr}.vx-person-head>div:first-child{grid-column:1/-1}.vx-person-head>div:last-child{display:none}.vx-breakdown{overflow-x:auto}.vx-payrow{min-width:760px}}
@media(max-width:640px){.vx-run{gap:10px}.vx-hero,.vx-section{padding:14px}.vx-kpis{margin-inline:-14px;margin-bottom:-14px}.vx-kpi strong{font-size:17px}.vx-flow{padding:12px 8px}.vx-step{font-size:9px}.vx-person-head>div:not(:first-child){border-radius:9px;background:#f8fafc;padding:8px;text-align:left}.dark .vx-person-head>div:not(:first-child){background:#1f2937}}
</style>
<div class="vx-run">
    <section class="vx-card vx-hero">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><div class="text-xs font-semibold text-primary-600">Weekly Pay Run</div><h1 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $run->week_start?->format('M j') }} – {{ $run->week_end?->format('M j, Y') }}</h1><div class="mt-2 flex flex-wrap gap-2"><span class="vx-chip">{{ $statusLabel }}</span><span class="vx-chip">{{ $payouts->count() }} payout entries</span><span class="vx-chip">{{ $byPerson->count() }} people</span></div></div>
            <a href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}" class="vx-link">Payroll Dashboard</a>
        </div>
        <div class="vx-kpis">
            <div class="vx-kpi"><label>Total Payroll</label><strong>${{ number_format((float)$run->total_payout,2) }}</strong></div>
            <div class="vx-kpi"><label>People</label><strong>{{ $byPerson->count() }}</strong></div>
            <div class="vx-kpi"><label>Streamer Pay</label><strong>${{ number_format($streamerTotal,2) }}</strong></div>
            <div class="vx-kpi"><label>Fulfillment Pay</label><strong>${{ number_format($fulfillmentTotal,2) }}</strong></div>
            <div class="vx-kpi"><label>Blockers</label><strong class="{{ count($problems) ? 'text-amber-600' : 'text-emerald-600' }}">{{ count($problems) }}</strong></div>
        </div>
    </section>

    <section class="vx-card vx-flow">
        @foreach($statusSteps as $key=>$label) @php $i=array_search($key,$statusKeys,true); @endphp
            <div class="vx-step {{ $i < $statusIndex ? 'done' : ($i === $statusIndex ? 'current' : '') }}"><div class="vx-dot">{{ $i < $statusIndex ? '✓' : $i+1 }}</div>{{ $label }}</div>
        @endforeach
    </section>

    <div class="vx-grid">
        <section class="vx-card vx-section">
            <div class="flex items-start justify-between gap-3"><div><h3>People in This Pay Run</h3><div class="vx-sub">Each person’s total with the show or work lines that built it.</div></div></div>
            @forelse($byPerson as $streamerId=>$rows)
                @php $person=$rows->first()->streamer; $personTotal=(float)$rows->sum('calculated_payout'); $hours=(float)$rows->sum('hours_worked'); $labels=(int)$rows->sum('label_count'); $pwe=(int)$rows->sum('pwe_count'); @endphp
                <details class="vx-person">
                    <summary class="vx-person-head cursor-pointer list-none">
                        <div><div class="vx-value">{{ $person?->name ?? 'Unassigned team member' }}</div><div class="vx-meta">{{ $person?->isFulfillment() ? 'Fulfillment' : 'Streamer' }} · {{ $rows->count() }} line(s)</div></div>
                        <div class="vx-num"><div class="vx-meta">Hours</div><div class="vx-value">{{ number_format($hours,2) }}</div></div>
                        <div class="vx-num"><div class="vx-meta">Labels / PWE</div><div class="vx-value">{{ $labels }} / {{ $pwe }}</div></div>
                        <div class="vx-num"><div class="vx-meta">Shows</div><div class="vx-value">{{ $rows->pluck('show_id')->filter()->unique()->count() }}</div></div>
                        <div class="vx-num"><div class="vx-meta">Total</div><div class="vx-value">${{ number_format($personTotal,2) }}</div></div>
                        <div class="text-gray-400">⌄</div>
                    </summary>
                    <div class="vx-breakdown">
                        @foreach($rows as $payout)<div class="vx-payrow"><div><div class="vx-value truncate">{{ $payout->show?->title ?? 'General payroll line' }}</div><div class="vx-meta">{{ \App\Models\Streamer::payoutTypeLabels()[$payout->payout_type] ?? ucfirst(str_replace('_',' ',$payout->payout_type ?? '')) }}</div></div><div class="vx-num"><span class="vx-meta">Hours</span><br>{{ number_format((float)$payout->hours_worked,2) }}</div><div class="vx-num"><span class="vx-meta">Shipments</span><br>{{ number_format((int)$payout->shipments_count) }}</div><div class="vx-num"><span class="vx-meta">Labels</span><br>{{ number_format((int)$payout->label_count) }}</div><div class="vx-num"><span class="vx-meta">PWE</span><br>{{ number_format((int)$payout->pwe_count) }}</div><div class="vx-num"><strong>${{ number_format((float)$payout->calculated_payout,2) }}</strong></div></div>@endforeach
                    </div>
                </details>
            @empty<div class="py-10 text-center text-sm text-gray-500">No payout entries are attached to this run yet.</div>@endforelse
        </section>

        <aside class="space-y-3">
            <section class="vx-card vx-section"><h3>Readiness</h3><div class="vx-sub">Resolve every blocker before finalizing.</div><div class="mt-3">@forelse($problems as $problem)<div class="vx-alert">{{ $problem }}</div>@empty<div class="vx-ok">✓ This pay run has no readiness blockers.</div>@endforelse</div></section>
            <section class="vx-card vx-section"><h3>Run Details</h3>@foreach([['Status',$statusLabel],['Created by',$run->createdBy?->name],['Finalized by',$run->finalizedBy?->name],['Finalized at',$run->finalized_at?->format('M j, Y g:i A')],['Notes',$run->notes]] as [$label,$value])<div class="vx-fin"><span class="text-gray-500">{{ $label }}</span><strong class="text-right">{{ $value ?: '—' }}</strong></div>@endforeach</section>
        </aside>
    </div>
</div>
</x-filament-panels::page>
