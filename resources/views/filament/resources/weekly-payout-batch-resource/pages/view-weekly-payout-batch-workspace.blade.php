<x-filament-panels::page>
@php
    $batch = $this->record;
    $batch->loadMissing(['payouts.streamer','payouts.show','createdBy','finalizedBy']);
    $payouts = $batch->payouts->sortByDesc('calculated_payout')->values();
    $problems = $batch->status === 'draft'
        ? app(\App\Services\PayRunReadinessService::class)->problems($batch)
        : [];
    $statusLabel = \App\Models\WeeklyPayoutBatch::statusLabels()[$batch->status] ?? ucfirst(str_replace('_',' ', $batch->status));
    $workflowSteps = [
        'draft' => 'Build & Review',
        'finalized' => 'Finalized',
        'submitted_to_adp' => 'Submitted to ADP',
        'paid' => 'Paid',
    ];
    $keys = array_keys($workflowSteps);
    $current = array_search($batch->status, $keys, true);
    $current = $current === false ? 0 : $current;
    $showCount = $payouts->pluck('show_id')->filter()->unique()->count();
    $memberCount = $payouts->pluck('streamer_id')->filter()->unique()->count();
    $avgPayout = $payouts->count() ? (float)$payouts->avg('calculated_payout') : 0;
@endphp
<style>
.vx-payrun{max-width:1420px;margin:0 auto;display:grid;gap:14px}.vx-card{border:1px solid #e5e7eb;background:#fff;border-radius:16px;box-shadow:0 1px 2px rgba(15,23,42,.04)}.dark .vx-card{border-color:#263248;background:#101827}.vx-hero{padding:18px 20px}.vx-chip{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:750;background:#f3f4f6;color:#4b5563}.dark .vx-chip{background:#1f2937;color:#d1d5db}.vx-chip.good{background:#ecfdf5;color:#047857}.dark .vx-chip.good{background:#052e2b;color:#6ee7b7}.vx-chip.warn{background:#fffbeb;color:#b45309}.dark .vx-chip.warn{background:#451a03;color:#fcd34d}.vx-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));border-top:1px solid #eef0f3;margin-top:16px}.dark .vx-kpis{border-color:#263248}.vx-kpi{padding:14px 16px;border-right:1px solid #eef0f3;min-width:0}.dark .vx-kpi{border-color:#263248}.vx-kpi:last-child{border-right:0}.vx-kpi label{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.06em;font-weight:800;color:#9ca3af}.vx-kpi strong{display:block;margin-top:4px;font-size:20px;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.dark .vx-kpi strong{color:#fff}.vx-flow{display:flex;align-items:center;gap:7px;padding:14px 16px;overflow-x:auto}.vx-step{display:flex;align-items:center;gap:7px;white-space:nowrap;font-size:11px;font-weight:750;color:#9ca3af}.vx-step .dot{width:24px;height:24px;border-radius:999px;display:grid;place-items:center;background:#f3f4f6}.dark .vx-step .dot{background:#1f2937}.vx-step.done{color:#059669}.vx-step.done .dot{background:#ecfdf5;color:#059669}.vx-step.current{color:#2563eb}.vx-step.current .dot{background:#dbeafe;color:#2563eb}.vx-arrow{color:#d1d5db}.vx-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,.7fr);gap:14px}.vx-section{padding:18px}.vx-section h3{font-size:14px;font-weight:800;color:#111827}.dark .vx-section h3{color:#fff}.vx-sub{font-size:11px;color:#6b7280;margin-top:2px}.vx-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;padding:12px 0;border-top:1px solid #f1f3f5}.dark .vx-row{border-color:#1f2937}.vx-value{font-size:13px;font-weight:750;color:#111827}.dark .vx-value{color:#fff}.vx-meta{font-size:11px;color:#6b7280;margin-top:2px}.vx-money{font-size:14px;font-weight:850;color:#111827}.dark .vx-money{color:#fff}.vx-fin{display:grid;grid-template-columns:1fr auto;gap:8px;padding:8px 0;font-size:12px;border-top:1px solid #f3f4f6}.dark .vx-fin{border-color:#1f2937}.vx-link{display:inline-flex;align-items:center;justify-content:center;min-height:38px;border-radius:10px;border:1px solid #d1d5db;padding:7px 10px;font-size:11px;font-weight:750;color:#374151}.dark .vx-link{border-color:#475569;color:#e5e7eb}.vx-link.primary{background:#2563eb;border-color:#2563eb;color:#fff}.vx-empty{padding:24px 0;text-align:center;font-size:12px;color:#9ca3af}.vx-problem{padding:10px 12px;border-radius:10px;background:#fffbeb;color:#92400e;font-size:12px;margin-top:8px}.dark .vx-problem{background:#451a03;color:#fde68a}.vx-note{white-space:pre-wrap;font-size:12px;color:#4b5563;line-height:1.55}.dark .vx-note{color:#cbd5e1}
@media(max-width:900px){.vx-grid{grid-template-columns:1fr}.vx-kpis{grid-template-columns:repeat(2,1fr)}.vx-kpi:nth-child(2n){border-right:0}}
@media(max-width:640px){.vx-payrun{gap:10px}.vx-hero,.vx-section{padding:14px}.vx-kpis{margin-inline:-14px;margin-bottom:-14px}.vx-kpi strong{font-size:17px}.vx-flow{padding:12px}.vx-step{font-size:10px}}
</style>
<div class="vx-payrun">
    <section class="vx-card vx-hero">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="text-xs text-gray-500">Payroll · Week of {{ $batch->week_start?->format('M j, Y') }}</div>
                <h1 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $batch->week_start?->format('M j') }}–{{ $batch->week_end?->format('M j, Y') }}</h1>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span class="vx-chip {{ $batch->status === 'paid' ? 'good' : '' }}">{{ $statusLabel }}</span>
                    @if($batch->status === 'draft')<span class="vx-chip {{ empty($problems) ? 'good' : 'warn' }}">{{ empty($problems) ? 'Ready to finalize' : count($problems).' blocker'.(count($problems)===1?'':'s') }}</span>@endif
                </div>
            </div>
            <a class="vx-link primary" href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}">Payroll Overview</a>
        </div>
        <div class="vx-kpis">
            <div class="vx-kpi"><label>Total Payroll</label><strong>${{ number_format((float)$batch->total_payout,2) }}</strong></div>
            <div class="vx-kpi"><label>Team Members</label><strong>{{ number_format($memberCount) }}</strong></div>
            <div class="vx-kpi"><label>Shows</label><strong>{{ number_format($showCount) }}</strong></div>
            <div class="vx-kpi"><label>Payout Lines</label><strong>{{ number_format($payouts->count()) }}</strong></div>
            <div class="vx-kpi"><label>Avg Payout</label><strong>${{ number_format($avgPayout,2) }}</strong></div>
        </div>
    </section>

    <section class="vx-card vx-flow" aria-label="Pay run workflow">
        @foreach($workflowSteps as $key=>$label)
            @php $idx=array_search($key,$keys,true); $cls=$idx < $current ? 'done' : ($idx === $current ? 'current' : ''); @endphp
            <div class="vx-step {{ $cls }}"><span class="dot">{{ $idx < $current ? '✓' : $idx+1 }}</span><span>{{ $label }}</span></div>
            @if(!$loop->last)<span class="vx-arrow">›</span>@endif
        @endforeach
    </section>

    <div class="vx-grid">
        <div class="space-y-3">
            <section class="vx-card vx-section">
                <div class="flex items-start justify-between gap-3"><div><h3>Payout Breakdown</h3><div class="vx-sub">Every team-member payout included in this weekly run.</div></div><span class="vx-chip">{{ $payouts->count() }} entries</span></div>
                @forelse($payouts as $payout)
                    <div class="vx-row">
                        <div class="min-w-0">
                            <div class="vx-value truncate">{{ $payout->streamer?->name ?? 'Team member' }}</div>
                            <div class="vx-meta">
                                {{ $payout->show?->title ?? 'General payout' }}
                                @if($payout->payout_type) · {{ \App\Models\Streamer::payoutTypeLabels()[$payout->payout_type] ?? ucfirst(str_replace('_',' ',$payout->payout_type)) }} @endif
                                @if($payout->hours_worked) · {{ number_format((float)$payout->hours_worked,2) }} hr @endif
                                @if($payout->shipments_count) · {{ number_format((int)$payout->shipments_count) }} shipments @endif
                            </div>
                        </div>
                        <div class="text-right"><div class="vx-money">${{ number_format((float)$payout->calculated_payout,2) }}</div><div class="vx-meta">{{ \App\Models\Payout::statusLabels()[$payout->status] ?? ucfirst($payout->status) }}</div></div>
                    </div>
                @empty<div class="vx-empty">No payout entries are attached to this run yet.</div>@endforelse
            </section>
        </div>

        <aside class="space-y-3">
            <section class="vx-card vx-section">
                <h3>Readiness</h3><div class="vx-sub">Items that must be clean before payroll can be locked.</div>
                @if($batch->status !== 'draft')
                    <div class="mt-3"><span class="vx-chip good">Locked at finalization</span></div>
                @elseif(empty($problems))
                    <div class="mt-3"><span class="vx-chip good">All checks passed</span></div>
                @else
                    @foreach($problems as $problem)<div class="vx-problem">{{ $problem }}</div>@endforeach
                @endif
            </section>

            <section class="vx-card vx-section">
                <h3>Run Details</h3>
                <div class="vx-fin"><span class="text-gray-500">Created By</span><strong>{{ $batch->createdBy?->name ?? '—' }}</strong></div>
                <div class="vx-fin"><span class="text-gray-500">Finalized By</span><strong>{{ $batch->finalizedBy?->name ?? '—' }}</strong></div>
                <div class="vx-fin"><span class="text-gray-500">Finalized At</span><strong>{{ $batch->finalized_at?->format('M j, Y g:i A') ?? '—' }}</strong></div>
                <div class="vx-fin"><span class="text-gray-500">Status</span><strong>{{ $statusLabel }}</strong></div>
            </section>

            @if($batch->notes)
            <section class="vx-card vx-section"><h3>Notes</h3><div class="vx-note mt-3">{{ $batch->notes }}</div></section>
            @endif
        </aside>
    </div>
</div>
</x-filament-panels::page>
