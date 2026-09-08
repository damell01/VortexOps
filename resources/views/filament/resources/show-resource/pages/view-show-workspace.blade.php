<x-filament-panels::page>
@php
    $show = $this->record;
    $show->loadMissing(['streamers','channel','fulfillmentUsers','streamerLogEntry.streamer','shipments','payouts.batch','latestDeductionRequest.lines.inventoryItem']);
    $pnl = $show->profitAndLoss();
    $workflow = app(\App\Services\ShowWorkflowService::class)->stateFor($show);
    $workflowKey = $workflow['key'] ?? 'show';
    $report = $show->streamerLogEntry;
    $shipments = $show->shipments;
    $payouts = $show->payouts;
    $deliveredShipments = $shipments->filter(fn ($shipment) => strtolower((string) $shipment->status) === 'delivered')->count();
    $openShipments = max(0, $shipments->count() - $deliveredShipments);
    $analyticsMissing = $show->show_date?->lte(today()) && !in_array($show->status,['cancelled'],true) && (float)$show->gross_revenue===0.0 && (float)$show->whatnot_net===0.0;
    $reportLabel = match(true) {
        $report?->approval_status === 'approved' || $report?->status === 'admin_approved' => 'Approved',
        $report?->status === 'changes_requested' || $report?->approval_status === 'rejected' => 'Changes Requested',
        (bool)$report?->submitted_at => 'Submitted',
        (bool)$report => 'Draft',
        default => 'Not Started',
    };
    $workflowSteps = ['show'=>'Show','streamer_log'=>'Streamer Report','admin_review'=>'Admin Review','fulfillment'=>'Fulfillment','payroll_review'=>'Payroll Review','payroll_ready'=>'Payroll Ready','payroll'=>'Pay Run','paid'=>'Paid'];
    $stepKeys = array_keys($workflowSteps);
    $currentStep = array_search($workflowKey,$stepKeys,true);
    $currentStep = $currentStep===false?0:$currentStep;
    $primary = match($workflowKey) {
        'streamer_log' => ['label'=>$report?->status==='changes_requested'?'Fix Show Report':($report?'Open Show Report':'Start Show Report'),'url'=>\App\Filament\Pages\EndOfStreamForm::getUrl(['showId'=>$show->id]),'tone'=>'primary'],
        'admin_review' => ['label'=>'Review Streamer Report','url'=>$report?\App\Filament\Resources\StreamerLogResource::getUrl('edit',['record'=>$report]):\App\Filament\Resources\ShowResource::getUrl('view',['record'=>$show]),'tone'=>'warning'],
        'fulfillment' => ['label'=>match($workflow['label']??''){ 'Fulfillment Issues'=>'Resolve Fulfillment','Seal Boxes'=>'Seal Boxes','Build Boxes'=>'Build Boxes','Ready to Complete'=>'Complete Fulfillment',default=>'Pack Show'},'url'=>\App\Filament\Resources\FulfillmentResource::getUrl('view',['record'=>$show]),'tone'=>'primary'],
        'payroll_review','payroll_ready' => ['label'=>$workflowKey==='payroll_ready'?'Open Payroll':'Fix Payroll Inputs','url'=>\App\Filament\Pages\PayrollOverview::getUrl(),'tone'=>$workflowKey==='payroll_ready'?'success':'warning'],
        'payroll' => ['label'=>'Open Pay Run','url'=>$payouts->first()?->batch?\App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view',['record'=>$payouts->first()->batch]):\App\Filament\Pages\PayrollOverview::getUrl(),'tone'=>'primary'],
        'paid' => ['label'=>'Workflow Complete','url'=>null,'tone'=>'success'],
        default => ['label'=>'Open Show Report','url'=>\App\Filament\Pages\EndOfStreamForm::getUrl(['showId'=>$show->id]),'tone'=>'primary'],
    };
    $blocker = $workflow['blockers'][0] ?? ($workflow['description'] ?? 'No active blocker.');
@endphp
<style>
.vx-show{max-width:1440px;margin:0 auto;display:grid;gap:14px}.vx-card{border:1px solid #e5e7eb;background:#fff;border-radius:18px;box-shadow:0 1px 2px rgba(15,23,42,.04);overflow:hidden}.dark .vx-card{border-color:#263248;background:#101827}.vx-pad{padding:18px 20px}.vx-chip{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:10px;font-weight:800;background:#f3f4f6;color:#4b5563}.dark .vx-chip{background:#1f2937;color:#d1d5db}.vx-chip.warn{background:#fff7ed;color:#c2410c}.vx-chip.good{background:#ecfdf5;color:#047857}.vx-chip.run{background:#eff6ff;color:#1d4ed8}.vx-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:16px}.vx-kpi{padding:12px;border-radius:14px;background:#f8fafc}.dark .vx-kpi{background:#1f2937}.vx-kpi label{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.07em;font-weight:800;color:#9ca3af}.vx-kpi strong{display:block;margin-top:4px;font-size:20px;color:#111827}.dark .vx-kpi strong{color:#fff}.vx-next{display:flex;align-items:center;justify-content:space-between;gap:14px;border-radius:14px;padding:14px}.vx-next.warning{background:#fff7ed;color:#9a3412}.vx-next.primary{background:#eff6ff;color:#1d4ed8}.vx-next.success{background:#ecfdf5;color:#047857}.dark .vx-next.warning{background:rgba(154,52,18,.16);color:#fdba74}.dark .vx-next.primary{background:#172554;color:#93c5fd}.dark .vx-next.success{background:rgba(4,120,87,.15);color:#6ee7b7}.vx-flow{display:flex;align-items:center;gap:6px;padding:14px 16px;overflow-x:auto}.vx-step{display:flex;align-items:center;gap:6px;white-space:nowrap;font-size:10px;font-weight:800;color:#9ca3af}.vx-step .dot{width:22px;height:22px;border-radius:999px;display:grid;place-items:center;background:#f3f4f6}.dark .vx-step .dot{background:#1f2937}.vx-step.done{color:#059669}.vx-step.done .dot{background:#ecfdf5;color:#059669}.vx-step.current{color:#2563eb}.vx-step.current .dot{background:#dbeafe;color:#2563eb}.vx-arrow{color:#d1d5db}.vx-main{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(300px,.55fr);gap:14px}.vx-section{padding:18px}.vx-section h3{font-size:14px;font-weight:800;color:#111827}.dark .vx-section h3{color:#fff}.vx-sub{font-size:11px;color:#6b7280;margin-top:2px}.vx-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;padding:12px 0;border-top:1px solid #f1f3f5}.dark .vx-row{border-color:#1f2937}.vx-value{font-size:13px;font-weight:800;color:#111827}.dark .vx-value{color:#fff}.vx-meta{font-size:10px;color:#6b7280;margin-top:2px}.vx-fin{display:grid;grid-template-columns:1fr auto;gap:9px;font-size:11px;padding:7px 0;border-top:1px solid #f3f4f6}.dark .vx-fin{border-color:#1f2937}.vx-fin:first-child{border-top:0}.vx-good{color:#059669}.vx-bad{color:#dc2626}.vx-link{display:inline-flex;align-items:center;justify-content:center;min-height:40px;border-radius:11px;border:1px solid #d1d5db;padding:7px 11px;font-size:11px;font-weight:800;color:#374151}.dark .vx-link{border-color:#475569;color:#e5e7eb}.vx-link.primary{background:#2563eb;border-color:#2563eb;color:#fff}.vx-link.warning{background:#d97706;border-color:#d97706;color:#fff}.vx-link.success{background:#059669;border-color:#059669;color:#fff}.vx-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.vx-stat{border-radius:12px;background:#f8fafc;padding:10px;text-align:center}.dark .vx-stat{background:#1f2937}
@media(max-width:900px){.vx-main{grid-template-columns:1fr}.vx-kpis{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.vx-show{gap:10px}.vx-pad,.vx-section{padding:14px}.vx-kpis{grid-template-columns:1fr 1fr}.vx-next{align-items:stretch;flex-direction:column}.vx-next .vx-link{width:100%}.vx-flow{padding:12px}.vx-actions{display:grid;grid-template-columns:1fr 1fr}.vx-link{min-height:44px}.vx-stats{grid-template-columns:repeat(3,1fr)}}
</style>
<div class="vx-show">
    <section class="vx-card vx-pad">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-primary-600">Show Workspace</div>
                <h1 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $show->title ?: 'Show #'.$show->id }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-[10px] text-gray-500"><span>{{ $show->show_date?->format('M j, Y') ?? 'Date not set' }}</span>@if($show->start_time)<span>· {{ $show->start_time->format('g:i A') }}</span>@endif @if($show->channel)<span>· {{ $show->channel->name }}</span>@endif</div>
                <div class="mt-3 flex flex-wrap gap-2"><span class="vx-chip run">{{ $workflow['label'] ?? ucfirst(str_replace('_',' ',$workflowKey)) }}</span><span class="vx-chip">{{ ucfirst(str_replace('_',' ',$show->status ?? 'unknown')) }}</span>@foreach($show->streamers as $streamer)<span class="vx-chip">{{ $streamer->name }}</span>@endforeach @if($analyticsMissing)<span class="vx-chip warn">Whatnot analytics missing</span>@endif</div>
            </div>
            <div class="vx-actions flex flex-wrap gap-2"><a class="vx-link" href="{{ \App\Filament\Resources\ShipmentResource::getUrl('index',['show'=>$show->id]) }}">Shipments</a><a class="vx-link" href="{{ \App\Filament\Resources\ShowResource::getUrl('inventory',['record'=>$show]) }}">Inventory / COGS</a></div>
        </div>
        <div class="vx-kpis"><div class="vx-kpi"><label>Gross Sales</label><strong>${{ number_format((float)$pnl['gross'],2) }}</strong></div><div class="vx-kpi"><label>Shipments</label><strong>{{ number_format($shipments->count()) }}</strong></div><div class="vx-kpi"><label>Payroll</label><strong>${{ number_format((float)$pnl['payouts'],2) }}</strong></div><div class="vx-kpi"><label>Show Net</label><strong class="{{ $pnl['margin']<0?'vx-bad':'vx-good' }}">${{ number_format((float)$pnl['margin'],2) }}</strong></div></div>
    </section>

    <section class="vx-card">
        <div class="vx-flow" aria-label="Show workflow">@foreach($workflowSteps as $key=>$label)@php $idx=array_search($key,$stepKeys,true);$cls=$idx<$currentStep?'done':($idx===$currentStep?'current':'');@endphp<div class="vx-step {{ $cls }}"><span class="dot">{{ $idx<$currentStep?'✓':$idx+1 }}</span><span>{{ $label }}</span></div>@if(!$loop->last)<span class="vx-arrow">›</span>@endif @endforeach</div>
        <div class="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5"><div class="vx-next {{ $primary['tone'] }}"><div><div class="text-[9px] font-bold uppercase tracking-[.12em] opacity-70">Next action</div><div class="mt-1 text-sm font-bold">{{ $primary['label'] }}</div><div class="mt-1 text-[11px] opacity-90">{{ $blocker }}</div></div>@if($primary['url'])<a href="{{ $primary['url'] }}" class="vx-link {{ $primary['tone'] }}">{{ $primary['label'] }}</a>@endif</div></div>
    </section>

    <div class="vx-main">
        <div class="space-y-3">
            <section class="vx-card vx-section"><div><h3>Workflow Status</h3><div class="vx-sub">The four operational handoffs that move this show forward.</div></div>
                <div class="vx-row"><div><div class="vx-value">Streamer Report</div><div class="vx-meta">{{ $reportLabel }}{{ $report?->streamer?->name?' · '.$report->streamer->name:'' }}</div></div><a class="vx-link" href="{{ $report?\App\Filament\Resources\StreamerLogResource::getUrl('edit',['record'=>$report]):\App\Filament\Pages\EndOfStreamForm::getUrl(['showId'=>$show->id]) }}">{{ $report?'Review':'Start' }}</a></div>
                <div class="vx-row"><div><div class="vx-value">Fulfillment</div><div class="vx-meta">{{ $show->fulfillmentUsers->pluck('name')->filter()->join(', ') ?: 'Unassigned' }} · {{ $shipments->count() }} shipment(s)</div></div><a class="vx-link" href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('view',['record'=>$show]) }}">Open</a></div>
                <div class="vx-row"><div><div class="vx-value">Inventory / COGS</div><div class="vx-meta">${{ number_format((float)$pnl['cogs'],2) }} approved cost · {{ $show->latestDeductionRequest?'deduction request present':'no deduction request yet' }}</div></div><a class="vx-link" href="{{ \App\Filament\Resources\ShowResource::getUrl('inventory',['record'=>$show]) }}">Breakdown</a></div>
                <div class="vx-row"><div><div class="vx-value">Payroll</div><div class="vx-meta">{{ $payouts->count() }} payout line(s) · ${{ number_format((float)$payouts->sum('calculated_payout'),2) }}</div></div>@if($payouts->first()?->batch)<a class="vx-link" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view',['record'=>$payouts->first()->batch]) }}">Open Run</a>@else<a class="vx-link" href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}">Payroll</a>@endif</div>
            </section>

            <section class="vx-card vx-section"><div class="flex items-start justify-between gap-3"><div><h3>Shipment Progress</h3><div class="vx-sub">Keep shipment detail secondary unless fulfillment needs it.</div></div><a class="vx-link" href="{{ \App\Filament\Resources\ShipmentResource::getUrl('index',['show'=>$show->id]) }}">View All</a></div><div class="vx-stats mt-4"><div class="vx-stat"><div class="text-xl font-bold">{{ $shipments->count() }}</div><div class="text-[10px] text-gray-500">Shipments</div></div><div class="vx-stat"><div class="text-xl font-bold">{{ $deliveredShipments }}</div><div class="text-[10px] text-gray-500">Delivered</div></div><div class="vx-stat"><div class="text-xl font-bold">{{ $openShipments }}</div><div class="text-[10px] text-gray-500">Open</div></div></div></section>
        </div>

        <aside class="space-y-3">
            <section class="vx-card vx-section"><h3>Show Financials</h3><div class="vx-sub">A compact operating P&L for the show.</div><div class="mt-3">@foreach([['Gross Sales',$pnl['gross']],['Whatnot Net',$pnl['net']],['Tips',$pnl['tips']],['COGS',-$pnl['cogs']],['Payroll',-$pnl['payouts']]] as [$label,$value])<div class="vx-fin"><span class="text-gray-500">{{ $label }}</span><strong>${{ number_format((float)$value,2) }}</strong></div>@endforeach<div class="vx-fin"><span class="font-bold">Show Net</span><strong class="{{ $pnl['margin']<0?'vx-bad':'vx-good' }}">${{ number_format((float)$pnl['margin'],2) }} · {{ number_format((float)$pnl['margin_pct'],1) }}%</strong></div></div>@if($analyticsMissing)<div class="mt-3 rounded-xl bg-amber-50 p-3 text-[11px] text-amber-700 dark:bg-amber-950/30 dark:text-amber-300">Whatnot analytics have not been captured for this completed show yet.</div>@endif</section>
            <section class="vx-card vx-section"><details><summary class="cursor-pointer list-none"><div class="flex items-center justify-between"><div><h3>Show Details</h3><div class="vx-sub">Secondary show and sync metadata.</div></div><span class="text-xs font-bold text-primary-600">View</span></div></summary><div class="mt-3">@foreach([['Units sold',$show->units_sold],['Buyers',$show->buyers_count],['Giveaways',$show->giveaways_count],['Peak viewers',$show->max_concurrent_viewers],['Rating',$show->avg_order_rating],['Last sync',$show->last_synced_at?->format('M j, g:i A')]] as [$label,$value])<div class="vx-fin"><span class="text-gray-500">{{ $label }}</span><strong>{{ $value ?? '—' }}</strong></div>@endforeach</div></details></section>
        </aside>
    </div>
</div>
</x-filament-panels::page>