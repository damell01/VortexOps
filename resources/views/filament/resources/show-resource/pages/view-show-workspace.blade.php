<x-filament-panels::page>
@php
    $show = $this->record;
    $show->loadMissing(['streamers','channel','fulfillmentUsers','streamerLogEntry','orders','shipments','payouts.batch','latestDeductionRequest.lines.inventoryItem']);
    $pnl = $show->profitAndLoss();
    $workflow = app(\App\Services\ShowWorkflowService::class)->stateFor($show);
    $workflowKey = $workflow['key'] ?? 'show';
    $report = $show->streamerLogEntry;
    $orders = $show->orders;
    $shipments = $show->shipments;
    $payouts = $show->payouts;
    $reportLabel = match(true) {
        $report?->status === 'changes_requested' => 'Changes requested',
        (bool) $report?->submitted_at => 'Submitted',
        (bool) $report => 'Draft',
        default => 'Not started',
    };
    $workflowSteps = [
        'show' => 'Show', 'streamer_log' => 'Streamer Report', 'admin_review' => 'Admin Review',
        'fulfillment' => 'Fulfillment', 'payroll_review' => 'Payroll Review', 'payroll_ready' => 'Payroll Ready',
        'payroll' => 'Pay Run', 'paid' => 'Paid',
    ];
    $stepKeys = array_keys($workflowSteps);
    $currentStep = array_search($workflowKey, $stepKeys, true);
    $currentStep = $currentStep === false ? 0 : $currentStep;
@endphp
<style>
.vx-show{max-width:1440px;margin:0 auto;display:grid;gap:14px}.vx-card{border:1px solid #e5e7eb;background:#fff;border-radius:16px;box-shadow:0 1px 2px rgba(15,23,42,.04)}.dark .vx-card{border-color:#263248;background:#101827}.vx-hero{padding:18px 20px}.vx-chip{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:750;background:#f3f4f6;color:#4b5563}.dark .vx-chip{background:#1f2937;color:#d1d5db}.vx-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));border-top:1px solid #eef0f3;margin-top:16px}.dark .vx-kpis{border-color:#263248}.vx-kpi{padding:14px 16px;border-right:1px solid #eef0f3;min-width:0}.dark .vx-kpi{border-color:#263248}.vx-kpi:last-child{border-right:0}.vx-kpi label{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.06em;font-weight:800;color:#9ca3af}.vx-kpi strong{display:block;margin-top:4px;font-size:20px;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.dark .vx-kpi strong{color:#fff}.vx-flow{display:flex;align-items:center;gap:6px;padding:14px 16px;overflow-x:auto}.vx-step{display:flex;align-items:center;gap:6px;white-space:nowrap;font-size:11px;font-weight:750;color:#9ca3af}.vx-step .dot{width:22px;height:22px;border-radius:999px;display:grid;place-items:center;background:#f3f4f6;color:#9ca3af}.dark .vx-step .dot{background:#1f2937}.vx-step.done{color:#059669}.vx-step.done .dot{background:#ecfdf5;color:#059669}.vx-step.current{color:#2563eb}.vx-step.current .dot{background:#dbeafe;color:#2563eb}.vx-arrow{color:#d1d5db}.vx-main{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,.7fr);gap:14px}.vx-section{padding:18px}.vx-section h3{font-size:14px;font-weight:800;color:#111827}.dark .vx-section h3{color:#fff}.vx-sub{font-size:11px;color:#6b7280;margin-top:2px}.vx-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;padding:12px 0;border-top:1px solid #f1f3f5}.dark .vx-row{border-color:#1f2937}.vx-value{font-size:13px;font-weight:700;color:#111827}.dark .vx-value{color:#fff}.vx-meta{font-size:11px;color:#6b7280;margin-top:2px}.vx-fin{display:grid;grid-template-columns:1fr auto;gap:9px;font-size:12px;padding:7px 0}.vx-fin.total{border-top:1px solid #e5e7eb;margin-top:5px;padding-top:12px;font-weight:850}.dark .vx-fin.total{border-color:#334155}.vx-good{color:#059669}.vx-bad{color:#dc2626}.vx-empty{padding:24px 0;text-align:center;font-size:12px;color:#9ca3af}.vx-actions-inline{display:flex;gap:8px;flex-wrap:wrap}.vx-link{display:inline-flex;align-items:center;justify-content:center;min-height:38px;border-radius:10px;border:1px solid #d1d5db;padding:7px 10px;font-size:11px;font-weight:750;color:#374151}.dark .vx-link{border-color:#475569;color:#e5e7eb}.vx-link.primary{background:#2563eb;border-color:#2563eb;color:#fff}
@media(max-width:900px){.vx-main{grid-template-columns:1fr}.vx-kpis{grid-template-columns:repeat(2,1fr)}.vx-kpi:nth-child(2n){border-right:0}.vx-kpi{border-bottom:1px solid #eef0f3}.dark .vx-kpi{border-color:#263248}}
@media(max-width:640px){.vx-show{gap:10px}.vx-hero,.vx-section{padding:14px}.vx-kpis{margin-inline:-14px;margin-bottom:-14px}.vx-kpi strong{font-size:17px}.vx-flow{padding:12px}.vx-step{font-size:10px}.vx-hero h1{font-size:20px!important}.vx-actions-inline{display:grid;grid-template-columns:1fr 1fr}.vx-link{min-height:44px}}
</style>
<div class="vx-show">
    <section class="vx-card vx-hero">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                    <span>{{ $show->show_date?->format('M j, Y') ?? 'Date not set' }}</span>
                    @if($show->start_time)<span>· {{ $show->start_time->format('g:i A') }}</span>@endif
                    @if($show->channel)<span>· {{ $show->channel->name }}</span>@endif
                </div>
                <h1 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $show->title ?: 'Show #'.$show->id }}</h1>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span class="vx-chip">{{ ucfirst(str_replace('_',' ', $show->status ?? 'unknown')) }}</span>
                    <span class="vx-chip">{{ $workflow['label'] ?? ucfirst(str_replace('_',' ', $workflowKey)) }}</span>
                    @foreach($show->streamers as $streamer)<span class="vx-chip">{{ $streamer->name }}</span>@endforeach
                </div>
            </div>
            <div class="vx-actions-inline">
                <a class="vx-link primary" href="{{ \App\Filament\Pages\EndOfStreamForm::getUrl(['showId'=>$show->id]) }}">{{ $report ? 'Open Report' : 'Start Report' }}</a>
                <a class="vx-link" href="{{ \App\Filament\Resources\ShipmentResource::getUrl('index',['tableFilters[show_id][value]'=>$show->id]) }}">Shipments</a>
            </div>
        </div>
        <div class="vx-kpis">
            <div class="vx-kpi"><label>Gross Sales</label><strong>${{ number_format((float)$pnl['gross'],2) }}</strong></div>
            <div class="vx-kpi"><label>Orders</label><strong>{{ number_format($orders->sum('quantity') ?: $orders->count()) }}</strong></div>
            <div class="vx-kpi"><label>Whatnot Net</label><strong>${{ number_format((float)$pnl['net'],2) }}</strong></div>
            <div class="vx-kpi"><label>Payroll</label><strong>${{ number_format((float)$pnl['payouts'],2) }}</strong></div>
            <div class="vx-kpi"><label>Show Net</label><strong class="{{ $pnl['margin'] < 0 ? 'vx-bad' : 'vx-good' }}">${{ number_format((float)$pnl['margin'],2) }}</strong></div>
        </div>
    </section>

    <section class="vx-card vx-flow" aria-label="Show workflow">
        @foreach($workflowSteps as $key=>$label)
            @php $idx=array_search($key,$stepKeys,true); $cls=$idx < $currentStep ? 'done' : ($idx === $currentStep ? 'current' : ''); @endphp
            <div class="vx-step {{ $cls }}"><span class="dot">{{ $idx < $currentStep ? '✓' : $idx+1 }}</span><span>{{ $label }}</span></div>
            @if(!$loop->last)<span class="vx-arrow">›</span>@endif
        @endforeach
    </section>

    <div class="vx-main">
        <div class="space-y-3">
            <section class="vx-card vx-section">
                <h3>Operations</h3><div class="vx-sub">What needs to happen next, with the core show work in one place.</div>
                <div class="vx-row"><div><div class="vx-value">Streamer Report</div><div class="vx-meta">{{ $reportLabel }}{{ $report?->streamer?->name ? ' · '.$report->streamer->name : '' }}</div></div><a class="vx-link" href="{{ \App\Filament\Pages\EndOfStreamForm::getUrl(['showId'=>$show->id]) }}">Open</a></div>
                <div class="vx-row"><div><div class="vx-value">Fulfillment</div><div class="vx-meta">{{ $shipments->count() }} shipment(s) · {{ $show->fulfillmentUsers->pluck('name')->filter()->join(', ') ?: 'No fulfillment user assigned' }}</div></div><a class="vx-link" href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('view',['record'=>$show]) }}">Open</a></div>
                <div class="vx-row"><div><div class="vx-value">Inventory / COGS</div><div class="vx-meta">${{ number_format((float)$pnl['cogs'],2) }} approved cost · {{ $show->latestDeductionRequest ? 'deduction request present' : 'no deduction request yet' }}</div></div><a class="vx-link" href="{{ \App\Filament\Resources\ShowResource::getUrl('inventory',['record'=>$show]) }}">Breakdown</a></div>
                <div class="vx-row"><div><div class="vx-value">Pay Run</div><div class="vx-meta">{{ $payouts->count() }} payout line(s) · ${{ number_format((float)$payouts->sum('calculated_payout'),2) }}</div></div>@if($payouts->first()?->batch)<a class="vx-link" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view',['record'=>$payouts->first()->batch]) }}">Open Run</a>@else<span class="vx-chip">Not attached</span>@endif</div>
            </section>

            <section class="vx-card vx-section">
                <div class="flex items-start justify-between gap-3"><div><h3>Whatnot Orders</h3><div class="vx-sub">Latest imported buyer/order activity for this show.</div></div><span class="vx-chip">{{ $orders->count() }} rows</span></div>
                @forelse($orders->take(8) as $order)
                    <div class="vx-row"><div class="min-w-0"><div class="vx-value truncate">{{ $order->item_name ?? 'Order item' }}</div><div class="vx-meta">{{ $order->buyer_username ?? 'Buyer' }} @if($order->lot_number)· Lot {{ $order->lot_number }}@endif</div></div><div class="text-right"><div class="vx-value">{{ number_format((float)($order->quantity ?? 1)) }} ×</div><div class="vx-meta">${{ number_format((float)($order->total_price ?? $order->unit_price ?? 0),2) }}</div></div></div>
                @empty<div class="vx-empty">No Whatnot order rows imported yet.</div>@endforelse
            </section>
        </div>

        <aside class="space-y-3">
            <section class="vx-card vx-section">
                <h3>Show Financials</h3><div class="vx-sub">Quick P&amp;L for the show.</div>
                @foreach([['Gross sales',$pnl['gross']],['Whatnot net',$pnl['net']],['Tips',$pnl['tips']],['COGS',-$pnl['cogs']],['Payroll',-$pnl['payouts']]] as [$label,$value])<div class="vx-fin"><span class="text-gray-500">{{ $label }}</span><strong>${{ number_format((float)$value,2) }}</strong></div>@endforeach
                <div class="vx-fin total"><span>Show net</span><strong class="{{ $pnl['margin'] < 0 ? 'vx-bad' : 'vx-good' }}">${{ number_format((float)$pnl['margin'],2) }} <small>({{ number_format((float)$pnl['margin_pct'],1) }}%)</small></strong></div>
            </section>
            <section class="vx-card vx-section">
                <h3>Show Details</h3>
                @foreach([['Units sold',$show->units_sold],['Buyers',$show->buyers_count],['Giveaways',$show->giveaways_count],['Peak viewers',$show->max_concurrent_viewers],['Rating',$show->avg_order_rating],['Last sync',$show->last_synced_at?->format('M j, g:i A')]] as [$label,$value])<div class="vx-fin"><span class="text-gray-500">{{ $label }}</span><strong>{{ $value ?? '—' }}</strong></div>@endforeach
            </section>
        </aside>
    </div>
</div>
</x-filament-panels::page>
