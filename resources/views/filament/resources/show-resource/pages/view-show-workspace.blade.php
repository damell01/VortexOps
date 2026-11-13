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

    // Read-only diagnostics. Never update show financials, payout rows or ledger attributions.
    $canViewReconciliation = (bool) (auth()->user()?->isAdmin() || auth()->user()?->isOwner());
    $ledgerReview = null;
    $reconciliationError = null;
    if ($canViewReconciliation) {
        try {
            $ledgerReview = app(\App\Services\ShowLedgerSummary::class)->for($show);
        } catch (\Throwable $exception) {
            $reconciliationError = 'Ledger comparison is temporarily unavailable.';
            \Illuminate\Support\Facades\Log::warning('Show read-only reconciliation failed', [
                'show_id' => $show->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
    $analyticsState = (string) ($show->analytics_sync_status ?? 'unknown');
    $recordedPay = round((float) $payouts->sum('calculated_payout'), 2);
    $pendingLedgerLines = $ledgerReview ? $ledgerReview['lines']->whereIn('status', ['waiting', 'pending'])->count() : 0;

@endphp
<div class="vx-show-detail space-y-5" style="font-size:14px">
<section class="vx-card overflow-hidden" style="border-radius:18px;box-shadow:0 1px 2px rgba(15,23,42,.04)">
<div class="p-5 sm:p-6"><div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
<div class="flex min-w-0 items-start gap-4">@if($show->cover_image_url)<img src="{{ $show->cover_image_url }}" alt="" loading="lazy" class="h-20 w-20 shrink-0 rounded-xl object-cover sm:h-24 sm:w-24">@endif
<div class="min-w-0"><div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-gray-500">@if($show->channel)<span>{{ $show->channel->name }}</span><span>•</span>@endif<span class="text-primary-600">Show #{{ $show->id }}</span></div>
<h1 class="mt-1 text-2xl font-black tracking-tight text-gray-950 dark:text-white sm:text-2xl">{{ $show->title ?: 'Show #'.$show->id }}</h1>
<div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500"><span>{{ $show->show_date?->format('M j, Y') ?? 'Date not set' }}</span>@if($show->start_time)<span>• {{ $show->start_time->format('g:i A') }}</span>@endif<span>• {{ ucfirst(str_replace('_',' ',$show->status ?? 'unknown')) }}</span></div>
<div class="mt-3 flex flex-wrap gap-2"><span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700">{{ $workflow['label'] ?? ucfirst(str_replace('_',' ',$workflowKey)) }}</span>@foreach($show->streamers as $streamer)<span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $streamer->name }}</span>@endforeach @if((float) ($show->tips ?? 0) > 0)<span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Tips: ${{ number_format((float) $show->tips, 2) }}</span>@endif @if($analyticsMissing)<span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">Whatnot analytics missing</span>@endif</div></div>
</div><div class="flex shrink-0 flex-wrap gap-2"><a class="inline-flex min-h-10 items-center justify-center rounded-xl border border-[var(--vx-border)] px-4 text-xs font-semibold" href="{{ \App\Filament\Resources\ShowResource::getUrl('edit',['record'=>$show]) }}">Edit Show</a><a class="inline-flex min-h-10 items-center justify-center rounded-xl border border-[var(--vx-border)] px-4 text-xs font-semibold" href="{{ \App\Filament\Resources\ShipmentResource::getUrl('index',['show'=>$show->id]) }}">Shipments</a><a class="inline-flex min-h-10 items-center justify-center rounded-xl border border-[var(--vx-border)] px-4 text-xs font-semibold" href="{{ \App\Filament\Resources\ShowResource::getUrl('inventory',['record'=>$show]) }}">Inventory / COGS</a></div>
</div></div>
<div class="grid grid-cols-2 gap-3 border-t border-[var(--vx-divider)] p-4 md:grid-cols-3 xl:grid-cols-4">
<div class="rounded-xl border border-[var(--vx-divider)] p-4"><label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500">Gross Sales</label><strong class="mt-1 block text-xl font-black tabular-nums">${{ number_format((float)$pnl['gross'],2) }}</strong></div>
<div class="rounded-xl border border-[var(--vx-divider)] p-4"><label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500">Orders</label><strong class="mt-1 block text-xl font-black tabular-nums">{{ number_format((int)($show->units_sold ?? 0)) }}</strong></div>
<div class="rounded-xl border border-[var(--vx-divider)] p-4"><label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500">Shipments</label><strong class="mt-1 block text-xl font-black tabular-nums">{{ $shipments->count() }}</strong></div>
<div class="rounded-xl border border-[var(--vx-divider)] p-4"><label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500">Whatnot Net</label><strong class="mt-1 block text-xl font-black tabular-nums">${{ number_format((float)$pnl['net'],2) }}</strong></div>
<div class="rounded-xl border border-[var(--vx-divider)] p-4"><label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500">Tips Received</label><strong class="mt-1 block text-xl font-black tabular-nums {{ (float) ($show->tips ?? 0) > 0 ? 'text-emerald-600' : '' }}">${{ number_format((float) ($show->tips ?? 0), 2) }}</strong></div>
<div class="rounded-xl border border-[var(--vx-divider)] p-4"><label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500">Payroll</label><strong class="mt-1 block text-xl font-black tabular-nums">${{ number_format((float)$pnl['payouts'],2) }}</strong></div>
<div class="rounded-xl border border-[var(--vx-divider)] p-4"><label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500">Show Net</label><strong class="mt-1 block text-xl font-black tabular-nums {{ $pnl['margin']<0?'vx-bad':'vx-good' }}">${{ number_format((float)$pnl['margin'],2) }}</strong></div>
</div></section>

    @if($canViewReconciliation)
    <section class="vx-card p-4 sm:p-5" aria-label="Read-only financial reconciliation">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-bold">Financial reconciliation</h2>
                <p class="mt-1 text-xs text-gray-500">Read-only comparison of recorded show figures, ledger attribution, and saved payroll. No amounts are changed here.</p>
            </div>
            <span class="rounded-full border border-gray-200 px-3 py-1 text-xs font-semibold dark:border-gray-700">Analytics: {{ ucfirst(str_replace('_', ' ', $analyticsState)) }}</span>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700"><p class="text-xs text-gray-500">Show net (Whatnot)</p><p class="mt-1 text-lg font-bold tabular-nums">${{ number_format((float) ($show->whatnot_net ?? 0), 2) }}</p></div>
            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700"><p class="text-xs text-gray-500">Attributed ledger net</p><p class="mt-1 text-lg font-bold tabular-nums">{{ $ledgerReview ? ' lg:items-center lg:overflow-x-auto" aria-label="Show workflow">@foreach($workflowSteps as $key=>$label)@php $idx=array_search($key,$stepKeys,true);$cls=$idx<$currentStep?'done':($idx===$currentStep?'current':'');@endphp<div class="flex min-h-10 items-center gap-2 text-xs font-semibold text-[var(--vx-muted)] {{ $cls }}"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[var(--vx-gray-bg)] vx-mono">{{ $idx<$currentStep?'✓':$idx+1 }}</span><span>{{ $label }}</span></div>@if(!$loop->last)<span class="hidden text-[var(--vx-faint)] lg:inline">›</span>@endif @endforeach</div>
        <div class="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5"><div class="vx-next {{ $primary['tone'] }}"><div><div class="text-[9px] font-bold uppercase tracking-[.12em] opacity-70">Next action</div><div class="mt-1 text-sm font-bold">{{ $primary['label'] }}</div><div class="mt-1 text-[11px] opacity-90">{{ $blocker }}</div></div>@if($primary['url'])<a href="{{ $primary['url'] }}" class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold {{ $primary['tone'] }}">{{ $primary['label'] }}</a>@endif</div></div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,.65fr)]">
        <div class="space-y-3">
            <section class="vx-card p-5"><div><h3 class="text-base font-bold">Operations</h3><div class="mt-1 text-xs text-[var(--vx-muted)]">The four operational handoffs that move this show forward.</div></div>
                <div class="flex items-center justify-between gap-4 border-t border-[var(--vx-divider)] py-3"><div><div class="text-sm font-semibold">Streamer Report</div><div class="mt-1 text-xs text-[var(--vx-muted)]">{{ $reportLabel }}{{ $report?->streamer?->name?' · '.$report->streamer->name:'' }}</div></div><a class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold" href="{{ $report?\App\Filament\Resources\StreamerLogResource::getUrl('edit',['record'=>$report]):\App\Filament\Pages\EndOfStreamForm::getUrl(['showId'=>$show->id]) }}">{{ $report?'Review':'Start' }}</a></div>
                <div class="flex items-center justify-between gap-4 border-t border-[var(--vx-divider)] py-3"><div><div class="text-sm font-semibold">Fulfillment</div><div class="mt-1 text-xs text-[var(--vx-muted)]">{{ $show->fulfillmentUsers->pluck('name')->filter()->join(', ') ?: 'Unassigned' }} · {{ $shipments->count() }} shipment(s)</div></div><a class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold" href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('view',['record'=>$show]) }}">Open</a></div>
                <div class="flex items-center justify-between gap-4 border-t border-[var(--vx-divider)] py-3"><div><div class="text-sm font-semibold">Inventory / COGS</div><div class="mt-1 text-xs text-[var(--vx-muted)]">${{ number_format((float)$pnl['cogs'],2) }} approved cost · {{ $show->latestDeductionRequest?'deduction request present':'no deduction request yet' }}</div></div><a class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold" href="{{ \App\Filament\Resources\ShowResource::getUrl('inventory',['record'=>$show]) }}">Breakdown</a></div>
                <div class="flex items-center justify-between gap-4 border-t border-[var(--vx-divider)] py-3"><div><div class="text-sm font-semibold">Payroll</div><div class="mt-1 text-xs text-[var(--vx-muted)]">{{ $payouts->count() }} payout line(s) · ${{ number_format((float)$payouts->sum('calculated_payout'),2) }}</div></div>@if($payouts->first()?->batch)<a class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view',['record'=>$payouts->first()->batch]) }}">Open Run</a>@else<a class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold" href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}">Payroll</a>@endif</div>
            </section>

            @php $ledger = app(\App\Services\ShowLedgerSummary::class)->for($show); @endphp
            <section class="vx-card p-5" aria-labelledby="show-ledger-h">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h3 id="show-ledger-h" class="text-base font-bold">Ledger</h3><div class="mt-1 text-xs text-[var(--vx-muted)]">Money Whatnot actually recorded for this show: sales, refunds, shipping and carrier adjustments, plus tips.</div>
                        @if(\App\Filament\Resources\WhatnotLedgerResource::canAccess())<a class="mt-2 inline-flex text-xs font-semibold text-violet-700 hover:underline dark:text-violet-300" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('index', ['show' => $show->id, 'period' => 'all']) }}">View every entry in the ledger ›</a>@endif</div>
                    <div class="text-right"><div class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Ledger net</div><div class="text-lg font-black tabular-nums {{ $ledger['ledger_net'] < 0 ? 'vx-bad' : '' }}">${{ number_format($ledger['ledger_net'], 2) }}</div><div class="text-xs text-[var(--vx-muted)]">Tips ${{ number_format($ledger['tips'], 2) }}</div></div>
                </div>
                @if($ledger['lines']->isEmpty())
                    <p class="mt-3 border-t border-[var(--vx-divider)] pt-3 text-xs text-[var(--vx-muted)]">No ledger entries tied to this show yet.</p>
                @else
                    <div class="mt-3 flex flex-wrap gap-2 border-t border-[var(--vx-divider)] pt-3 text-xs">
                        @foreach($ledger['by_category'] as $category => $total)
                            <span class="rounded-full bg-gray-100 px-3 py-1 font-semibold dark:bg-gray-800">{{ ucfirst($category) }} <span class="tabular-nums {{ $total < 0 ? 'vx-bad' : '' }}">${{ number_format($total, 2) }}</span></span>
                        @endforeach
                    </div>
                    <details class="mt-4 rounded-lg border border-[var(--vx-divider)] p-3" x-data="{ page: 1, size: 10 }">
                        <summary class="cursor-pointer text-sm font-semibold">After the show · {{ $ledger['after_show']->count() }} entries <span class="text-xs font-normal text-[var(--vx-muted)]">(expand)</span></summary>
                        @if($ledger['after_show']->isEmpty())
                            <p class="mt-2 text-xs text-[var(--vx-muted)]">Nothing has landed since the show ended.</p>
                        @else
                            <p class="mt-2 text-xs {{ $ledger['after_show_net'] < 0 ? 'vx-bad' : 'text-[var(--vx-muted)]' }}">{{ $ledger['after_show_net'] < 0 ? 'This show lost' : 'This show gained' }} ${{ number_format(abs($ledger['after_show_net']), 2) }} after it ended{{ $ledger['after_show_tips'] > 0 ? ', plus $'.number_format($ledger['after_show_tips'], 2).' in late tips' : '' }}.</p>
                            <div class="mt-2 divide-y divide-[var(--vx-divider)]">
                                @foreach($ledger['after_show'] as $line)
                                    <div x-show="Math.floor({{ $loop->index }} / size) + 1 === page" class="flex items-center justify-between gap-3 py-2 text-xs">
                                        <div class="min-w-0"><span class="font-semibold">{{ $line['at']?->format('M j') }}</span> · {{ ucfirst($line['category']) }}@if($line['order']) · order {{ $line['order'] }}@endif @if($line['status'] === 'waiting')<span class="text-amber-600">· not matched yet</span>@endif<div class="truncate text-[var(--vx-muted)]">{{ $line['message'] }}</div></div>
                                        <span class="shrink-0 font-semibold tabular-nums {{ $line['amount'] < 0 ? 'vx-bad' : 'vx-good' }}">{{ $line['amount'] < 0 ? '−' : '+' }}${{ number_format(abs($line['amount']), 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                            @if($ledger['after_show']->count() > 10)
                                <nav class="mt-3 flex items-center justify-between gap-3 text-xs" aria-label="After-show ledger pages">
                                    <button type="button" class="rounded border border-[var(--vx-divider)] px-3 py-2" @click="page = Math.max(1, page - 1)" :disabled="page === 1">Previous</button>
                                    <span x-text="'Page ' + page + ' of ' + Math.ceil({{ $ledger['after_show']->count() }} / size)"></span>
                                    <button type="button" class="rounded border border-[var(--vx-divider)] px-3 py-2" @click="page = Math.min(Math.ceil({{ $ledger['after_show']->count() }} / size), page + 1)" :disabled="page >= Math.ceil({{ $ledger['after_show']->count() }} / size)">Next</button>
                                </nav>
                            @endif
                        @endif
                    </details>
                    <details class="mt-4 rounded-lg border border-[var(--vx-divider)] p-3" x-data="{ ledgerCategory: 'all', page: 1, size: 10, categories: @js($ledger['lines']->pluck('category')->values()->all()), filteredIndexes() { return this.categories.map((category, index) => category === this.ledgerCategory || this.ledgerCategory === 'all' ? index : -1).filter(index => index !== -1); }, visible(index) { const indexes = this.filteredIndexes(); const position = indexes.indexOf(index); return position >= 0 && Math.floor(position / this.size) + 1 === this.page; } }">
                        <summary class="cursor-pointer text-sm font-semibold">All {{ $ledger['lines']->count() }} ledger entries <span class="text-xs font-normal text-[var(--vx-muted)]">(expand)</span></summary>
                        @if(\App\Filament\Resources\WhatnotLedgerResource::canAccess())
                            <a class="mt-2 inline-block text-xs font-semibold text-violet-700 hover:underline dark:text-violet-300" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('index', ['show' => $show->id, 'period' => 'all']) }}">Open full ledger</a>
                        @endif
                        <div class="mt-3 flex flex-wrap gap-1.5" role="group" aria-label="Filter show ledger entries">
                            @foreach(array_merge(['all'], $ledger['lines']->pluck('category')->filter()->unique()->sort()->values()->all()) as $category)
                                <button type="button" class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                                    :class="ledgerCategory === @js($category) ? 'border-violet-500 bg-violet-600 text-white' : 'border-[var(--vx-divider)] hover:border-violet-400'"
                                    :aria-pressed="ledgerCategory === @js($category)"
                                    @click="ledgerCategory = @js($category); page = 1">{{ $category === 'all' ? 'All entries' : ucfirst($category) }}</button>
                            @endforeach
                        </div>
                        <div class="mt-2 divide-y divide-[var(--vx-divider)]">
                            @foreach($ledger['lines'] as $line)
                                <div x-show="visible({{ $loop->index }})" class="flex items-start justify-between gap-3 py-2 text-xs">
                                    <div class="min-w-0">
                                        <div class="font-semibold">{{ $line['at']?->format('M j, g:ia') }} · {{ ucfirst($line['category']) }} · {{ $line['type'] }}</div>
                                        @if($line['order'])<div class="text-[var(--vx-muted)]">Order {{ $line['order'] }}</div>@endif
                                        @if($line['message'])<div class="break-words text-[var(--vx-muted)]">{{ $line['message'] }}</div>@endif
                                        @if($line['status'] === 'waiting')<span class="text-amber-600">Awaiting attribution</span>@endif
                                    </div>
                                    <span class="shrink-0 font-semibold tabular-nums {{ $line['amount'] < 0 ? 'vx-bad' : 'vx-good' }}">{{ $line['amount'] < 0 ? '−' : '+' }}${{ number_format(abs($line['amount']), 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <nav class="mt-3 flex items-center justify-between gap-3 text-xs" aria-label="Show ledger pages" x-show="filteredIndexes().length > size">
                            <button type="button" class="rounded border border-[var(--vx-divider)] px-3 py-2" @click="page = Math.max(1, page - 1)" :disabled="page === 1">Previous</button>
                            <span x-text="'Page ' + page + ' of ' + Math.max(1, Math.ceil(filteredIndexes().length / size))"></span>
                            <button type="button" class="rounded border border-[var(--vx-divider)] px-3 py-2" @click="page = Math.min(Math.ceil(filteredIndexes().length / size), page + 1)" :disabled="page >= Math.ceil(filteredIndexes().length / size)">Next</button>
                        </nav>
                    </details>
                @endif
            </section>
        </div>

        <aside class="space-y-3">
            <section class="vx-card p-5"><h3 class="text-base font-bold">Show Financials</h3><div class="mt-1 text-xs text-[var(--vx-muted)]">A compact operating P&L for the show.</div><div class="mt-3">@foreach([['Gross Sales',$pnl['gross']],['Whatnot Net',$pnl['net']],['Tips',$pnl['tips']],['COGS',-$pnl['cogs']],['Payroll',-$pnl['payouts']]] as [$label,$value])<div class="flex items-center justify-between gap-3 border-t border-[var(--vx-divider)] py-2 text-sm"><span class="text-gray-500">{{ $label }}</span><strong>${{ number_format((float)$value,2) }}</strong></div>@endforeach<div class="flex items-center justify-between gap-3 border-t border-[var(--vx-divider)] py-2 text-sm"><span class="font-bold">Show Net</span><strong class="{{ $pnl['margin']<0?'vx-bad':'vx-good' }}">${{ number_format((float)$pnl['margin'],2) }} · {{ number_format((float)$pnl['margin_pct'],1) }}%</strong></div></div>@if($analyticsMissing)<div class="mt-3 rounded-xl bg-amber-50 p-3 text-[11px] text-amber-700 dark:bg-amber-950/30 dark:text-amber-300">Whatnot analytics have not been captured for this completed show yet.</div>@endif</section>
            <section class="vx-card p-5"><details><summary class="cursor-pointer list-none"><div class="flex items-center justify-between"><div><h3>Show Details</h3><div class="mt-1 text-xs text-[var(--vx-muted)]">Secondary show and sync metadata.</div></div><span class="text-xs font-bold text-primary-600">View</span></div></summary><div class="mt-3">@foreach([['Orders',$show->units_sold],['Buyers',$show->buyers_count],['Giveaways',$show->giveaways_count],['Peak viewers',$show->max_concurrent_viewers],['Rating',$show->avg_order_rating],['Last sync',$show->last_synced_at?->copy()->timezone(\App\Support\BusinessTime::zone())->format('M j, g:i A')]] as [$label,$value])<div class="flex items-center justify-between gap-3 border-t border-[var(--vx-divider)] py-2 text-sm"><span class="text-gray-500">{{ $label }}</span><strong>{{ $value ?? '—' }}</strong></div>@endforeach</div></details></section>
        </aside>
    </div>
</div>
</x-filament-panels::page> . number_format((float) $ledgerReview['ledger_net'], 2) : 'Unavailable' }}</p></div>
            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700"><p class="text-xs text-gray-500">Attributed ledger tips</p><p class="mt-1 text-lg font-bold tabular-nums">{{ $ledgerReview ? ' lg:items-center lg:overflow-x-auto" aria-label="Show workflow">@foreach($workflowSteps as $key=>$label)@php $idx=array_search($key,$stepKeys,true);$cls=$idx<$currentStep?'done':($idx===$currentStep?'current':'');@endphp<div class="flex min-h-10 items-center gap-2 text-xs font-semibold text-[var(--vx-muted)] {{ $cls }}"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[var(--vx-gray-bg)] vx-mono">{{ $idx<$currentStep?'✓':$idx+1 }}</span><span>{{ $label }}</span></div>@if(!$loop->last)<span class="hidden text-[var(--vx-faint)] lg:inline">›</span>@endif @endforeach</div>
        <div class="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5"><div class="vx-next {{ $primary['tone'] }}"><div><div class="text-[9px] font-bold uppercase tracking-[.12em] opacity-70">Next action</div><div class="mt-1 text-sm font-bold">{{ $primary['label'] }}</div><div class="mt-1 text-[11px] opacity-90">{{ $blocker }}</div></div>@if($primary['url'])<a href="{{ $primary['url'] }}" class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold {{ $primary['tone'] }}">{{ $primary['label'] }}</a>@endif</div></div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,.65fr)]">
        <div class="space-y-3">
            <section class="vx-card p-5"><div><h3 class="text-base font-bold">Operations</h3><div class="mt-1 text-xs text-[var(--vx-muted)]">The four operational handoffs that move this show forward.</div></div>
                <div class="flex items-center justify-between gap-4 border-t border-[var(--vx-divider)] py-3"><div><div class="text-sm font-semibold">Streamer Report</div><div class="mt-1 text-xs text-[var(--vx-muted)]">{{ $reportLabel }}{{ $report?->streamer?->name?' · '.$report->streamer->name:'' }}</div></div><a class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold" href="{{ $report?\App\Filament\Resources\StreamerLogResource::getUrl('edit',['record'=>$report]):\App\Filament\Pages\EndOfStreamForm::getUrl(['showId'=>$show->id]) }}">{{ $report?'Review':'Start' }}</a></div>
                <div class="flex items-center justify-between gap-4 border-t border-[var(--vx-divider)] py-3"><div><div class="text-sm font-semibold">Fulfillment</div><div class="mt-1 text-xs text-[var(--vx-muted)]">{{ $show->fulfillmentUsers->pluck('name')->filter()->join(', ') ?: 'Unassigned' }} · {{ $shipments->count() }} shipment(s)</div></div><a class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold" href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('view',['record'=>$show]) }}">Open</a></div>
                <div class="flex items-center justify-between gap-4 border-t border-[var(--vx-divider)] py-3"><div><div class="text-sm font-semibold">Inventory / COGS</div><div class="mt-1 text-xs text-[var(--vx-muted)]">${{ number_format((float)$pnl['cogs'],2) }} approved cost · {{ $show->latestDeductionRequest?'deduction request present':'no deduction request yet' }}</div></div><a class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold" href="{{ \App\Filament\Resources\ShowResource::getUrl('inventory',['record'=>$show]) }}">Breakdown</a></div>
                <div class="flex items-center justify-between gap-4 border-t border-[var(--vx-divider)] py-3"><div><div class="text-sm font-semibold">Payroll</div><div class="mt-1 text-xs text-[var(--vx-muted)]">{{ $payouts->count() }} payout line(s) · ${{ number_format((float)$payouts->sum('calculated_payout'),2) }}</div></div>@if($payouts->first()?->batch)<a class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view',['record'=>$payouts->first()->batch]) }}">Open Run</a>@else<a class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold" href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}">Payroll</a>@endif</div>
            </section>

            @php $ledger = app(\App\Services\ShowLedgerSummary::class)->for($show); @endphp
            <section class="vx-card p-5" aria-labelledby="show-ledger-h">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h3 id="show-ledger-h" class="text-base font-bold">Ledger</h3><div class="mt-1 text-xs text-[var(--vx-muted)]">Money Whatnot actually recorded for this show: sales, refunds, shipping and carrier adjustments, plus tips.</div>
                        @if(\App\Filament\Resources\WhatnotLedgerResource::canAccess())<a class="mt-2 inline-flex text-xs font-semibold text-violet-700 hover:underline dark:text-violet-300" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('index', ['show' => $show->id, 'period' => 'all']) }}">View every entry in the ledger ›</a>@endif</div>
                    <div class="text-right"><div class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Ledger net</div><div class="text-lg font-black tabular-nums {{ $ledger['ledger_net'] < 0 ? 'vx-bad' : '' }}">${{ number_format($ledger['ledger_net'], 2) }}</div><div class="text-xs text-[var(--vx-muted)]">Tips ${{ number_format($ledger['tips'], 2) }}</div></div>
                </div>
                @if($ledger['lines']->isEmpty())
                    <p class="mt-3 border-t border-[var(--vx-divider)] pt-3 text-xs text-[var(--vx-muted)]">No ledger entries tied to this show yet.</p>
                @else
                    <div class="mt-3 flex flex-wrap gap-2 border-t border-[var(--vx-divider)] pt-3 text-xs">
                        @foreach($ledger['by_category'] as $category => $total)
                            <span class="rounded-full bg-gray-100 px-3 py-1 font-semibold dark:bg-gray-800">{{ ucfirst($category) }} <span class="tabular-nums {{ $total < 0 ? 'vx-bad' : '' }}">${{ number_format($total, 2) }}</span></span>
                        @endforeach
                    </div>
                    <details class="mt-4 rounded-lg border border-[var(--vx-divider)] p-3" x-data="{ page: 1, size: 10 }">
                        <summary class="cursor-pointer text-sm font-semibold">After the show · {{ $ledger['after_show']->count() }} entries <span class="text-xs font-normal text-[var(--vx-muted)]">(expand)</span></summary>
                        @if($ledger['after_show']->isEmpty())
                            <p class="mt-2 text-xs text-[var(--vx-muted)]">Nothing has landed since the show ended.</p>
                        @else
                            <p class="mt-2 text-xs {{ $ledger['after_show_net'] < 0 ? 'vx-bad' : 'text-[var(--vx-muted)]' }}">{{ $ledger['after_show_net'] < 0 ? 'This show lost' : 'This show gained' }} ${{ number_format(abs($ledger['after_show_net']), 2) }} after it ended{{ $ledger['after_show_tips'] > 0 ? ', plus $'.number_format($ledger['after_show_tips'], 2).' in late tips' : '' }}.</p>
                            <div class="mt-2 divide-y divide-[var(--vx-divider)]">
                                @foreach($ledger['after_show'] as $line)
                                    <div x-show="Math.floor({{ $loop->index }} / size) + 1 === page" class="flex items-center justify-between gap-3 py-2 text-xs">
                                        <div class="min-w-0"><span class="font-semibold">{{ $line['at']?->format('M j') }}</span> · {{ ucfirst($line['category']) }}@if($line['order']) · order {{ $line['order'] }}@endif @if($line['status'] === 'waiting')<span class="text-amber-600">· not matched yet</span>@endif<div class="truncate text-[var(--vx-muted)]">{{ $line['message'] }}</div></div>
                                        <span class="shrink-0 font-semibold tabular-nums {{ $line['amount'] < 0 ? 'vx-bad' : 'vx-good' }}">{{ $line['amount'] < 0 ? '−' : '+' }}${{ number_format(abs($line['amount']), 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                            @if($ledger['after_show']->count() > 10)
                                <nav class="mt-3 flex items-center justify-between gap-3 text-xs" aria-label="After-show ledger pages">
                                    <button type="button" class="rounded border border-[var(--vx-divider)] px-3 py-2" @click="page = Math.max(1, page - 1)" :disabled="page === 1">Previous</button>
                                    <span x-text="'Page ' + page + ' of ' + Math.ceil({{ $ledger['after_show']->count() }} / size)"></span>
                                    <button type="button" class="rounded border border-[var(--vx-divider)] px-3 py-2" @click="page = Math.min(Math.ceil({{ $ledger['after_show']->count() }} / size), page + 1)" :disabled="page >= Math.ceil({{ $ledger['after_show']->count() }} / size)">Next</button>
                                </nav>
                            @endif
                        @endif
                    </details>
                    <details class="mt-4 rounded-lg border border-[var(--vx-divider)] p-3" x-data="{ ledgerCategory: 'all', page: 1, size: 10, categories: @js($ledger['lines']->pluck('category')->values()->all()), filteredIndexes() { return this.categories.map((category, index) => category === this.ledgerCategory || this.ledgerCategory === 'all' ? index : -1).filter(index => index !== -1); }, visible(index) { const indexes = this.filteredIndexes(); const position = indexes.indexOf(index); return position >= 0 && Math.floor(position / this.size) + 1 === this.page; } }">
                        <summary class="cursor-pointer text-sm font-semibold">All {{ $ledger['lines']->count() }} ledger entries <span class="text-xs font-normal text-[var(--vx-muted)]">(expand)</span></summary>
                        @if(\App\Filament\Resources\WhatnotLedgerResource::canAccess())
                            <a class="mt-2 inline-block text-xs font-semibold text-violet-700 hover:underline dark:text-violet-300" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('index', ['show' => $show->id, 'period' => 'all']) }}">Open full ledger</a>
                        @endif
                        <div class="mt-3 flex flex-wrap gap-1.5" role="group" aria-label="Filter show ledger entries">
                            @foreach(array_merge(['all'], $ledger['lines']->pluck('category')->filter()->unique()->sort()->values()->all()) as $category)
                                <button type="button" class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                                    :class="ledgerCategory === @js($category) ? 'border-violet-500 bg-violet-600 text-white' : 'border-[var(--vx-divider)] hover:border-violet-400'"
                                    :aria-pressed="ledgerCategory === @js($category)"
                                    @click="ledgerCategory = @js($category); page = 1">{{ $category === 'all' ? 'All entries' : ucfirst($category) }}</button>
                            @endforeach
                        </div>
                        <div class="mt-2 divide-y divide-[var(--vx-divider)]">
                            @foreach($ledger['lines'] as $line)
                                <div x-show="visible({{ $loop->index }})" class="flex items-start justify-between gap-3 py-2 text-xs">
                                    <div class="min-w-0">
                                        <div class="font-semibold">{{ $line['at']?->format('M j, g:ia') }} · {{ ucfirst($line['category']) }} · {{ $line['type'] }}</div>
                                        @if($line['order'])<div class="text-[var(--vx-muted)]">Order {{ $line['order'] }}</div>@endif
                                        @if($line['message'])<div class="break-words text-[var(--vx-muted)]">{{ $line['message'] }}</div>@endif
                                        @if($line['status'] === 'waiting')<span class="text-amber-600">Awaiting attribution</span>@endif
                                    </div>
                                    <span class="shrink-0 font-semibold tabular-nums {{ $line['amount'] < 0 ? 'vx-bad' : 'vx-good' }}">{{ $line['amount'] < 0 ? '−' : '+' }}${{ number_format(abs($line['amount']), 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <nav class="mt-3 flex items-center justify-between gap-3 text-xs" aria-label="Show ledger pages" x-show="filteredIndexes().length > size">
                            <button type="button" class="rounded border border-[var(--vx-divider)] px-3 py-2" @click="page = Math.max(1, page - 1)" :disabled="page === 1">Previous</button>
                            <span x-text="'Page ' + page + ' of ' + Math.max(1, Math.ceil(filteredIndexes().length / size))"></span>
                            <button type="button" class="rounded border border-[var(--vx-divider)] px-3 py-2" @click="page = Math.min(Math.ceil(filteredIndexes().length / size), page + 1)" :disabled="page >= Math.ceil(filteredIndexes().length / size)">Next</button>
                        </nav>
                    </details>
                @endif
            </section>
        </div>

        <aside class="space-y-3">
            <section class="vx-card p-5"><h3 class="text-base font-bold">Show Financials</h3><div class="mt-1 text-xs text-[var(--vx-muted)]">A compact operating P&L for the show.</div><div class="mt-3">@foreach([['Gross Sales',$pnl['gross']],['Whatnot Net',$pnl['net']],['Tips',$pnl['tips']],['COGS',-$pnl['cogs']],['Payroll',-$pnl['payouts']]] as [$label,$value])<div class="flex items-center justify-between gap-3 border-t border-[var(--vx-divider)] py-2 text-sm"><span class="text-gray-500">{{ $label }}</span><strong>${{ number_format((float)$value,2) }}</strong></div>@endforeach<div class="flex items-center justify-between gap-3 border-t border-[var(--vx-divider)] py-2 text-sm"><span class="font-bold">Show Net</span><strong class="{{ $pnl['margin']<0?'vx-bad':'vx-good' }}">${{ number_format((float)$pnl['margin'],2) }} · {{ number_format((float)$pnl['margin_pct'],1) }}%</strong></div></div>@if($analyticsMissing)<div class="mt-3 rounded-xl bg-amber-50 p-3 text-[11px] text-amber-700 dark:bg-amber-950/30 dark:text-amber-300">Whatnot analytics have not been captured for this completed show yet.</div>@endif</section>
            <section class="vx-card p-5"><details><summary class="cursor-pointer list-none"><div class="flex items-center justify-between"><div><h3>Show Details</h3><div class="mt-1 text-xs text-[var(--vx-muted)]">Secondary show and sync metadata.</div></div><span class="text-xs font-bold text-primary-600">View</span></div></summary><div class="mt-3">@foreach([['Orders',$show->units_sold],['Buyers',$show->buyers_count],['Giveaways',$show->giveaways_count],['Peak viewers',$show->max_concurrent_viewers],['Rating',$show->avg_order_rating],['Last sync',$show->last_synced_at?->copy()->timezone(\App\Support\BusinessTime::zone())->format('M j, g:i A')]] as [$label,$value])<div class="flex items-center justify-between gap-3 border-t border-[var(--vx-divider)] py-2 text-sm"><span class="text-gray-500">{{ $label }}</span><strong>{{ $value ?? '—' }}</strong></div>@endforeach</div></details></section>
        </aside>
    </div>
</div>
</x-filament-panels::page> . number_format((float) $ledgerReview['tips'], 2) : 'Unavailable' }}</p></div>
            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700"><p class="text-xs text-gray-500">Saved payout lines</p><p class="mt-1 text-lg font-bold tabular-nums">${{ number_format($recordedPay, 2) }}</p></div>
        </div>
        @if($reconciliationError)
            <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">{{ $reconciliationError }}</p>
        @elseif($ledgerReview)
            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                <span class="rounded-lg bg-gray-100 px-3 py-2 dark:bg-gray-800">Ledger lines: {{ $ledgerReview['lines']->count() }}</span>
                <span class="rounded-lg {{ $pendingLedgerLines ? 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200' : 'bg-gray-100 dark:bg-gray-800' }} px-3 py-2">Pending/unmatched: {{ $pendingLedgerLines }}</span>
                <span class="rounded-lg bg-gray-100 px-3 py-2 dark:bg-gray-800">Post-show ledger adjustments: ${{ number_format((float) $ledgerReview['after_show_net'], 2) }}</span>
            </div>
            @if($pendingLedgerLines > 0)
                <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">Some ledger entries still need attribution or approval. Review them before treating ledger totals as reconciled.</p>
            @endif
        @endif
        @if(in_array($analyticsState, ['pending', 'unavailable', 'failed'], true))
            <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">Whatnot analytics are {{ $analyticsState }}. This comparison does not assume the figures are final.</p>
        @endif
        <p class="mt-3 text-xs text-gray-500">Ledger net and Whatnot net may cover different fee and adjustment categories; a difference alone is not a payroll error. Saved payouts are shown for reference, not recalculated.</p>
    </section>
    @endif

    <section class="vx-card">
        <div class="flex flex-col gap-3 p-5 lg:flex-row lg:items-center lg:overflow-x-auto" aria-label="Show workflow">@foreach($workflowSteps as $key=>$label)@php $idx=array_search($key,$stepKeys,true);$cls=$idx<$currentStep?'done':($idx===$currentStep?'current':'');@endphp<div class="flex min-h-10 items-center gap-2 text-xs font-semibold text-[var(--vx-muted)] {{ $cls }}"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[var(--vx-gray-bg)] vx-mono">{{ $idx<$currentStep?'✓':$idx+1 }}</span><span>{{ $label }}</span></div>@if(!$loop->last)<span class="hidden text-[var(--vx-faint)] lg:inline">›</span>@endif @endforeach</div>
        <div class="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5"><div class="vx-next {{ $primary['tone'] }}"><div><div class="text-[9px] font-bold uppercase tracking-[.12em] opacity-70">Next action</div><div class="mt-1 text-sm font-bold">{{ $primary['label'] }}</div><div class="mt-1 text-[11px] opacity-90">{{ $blocker }}</div></div>@if($primary['url'])<a href="{{ $primary['url'] }}" class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold {{ $primary['tone'] }}">{{ $primary['label'] }}</a>@endif</div></div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,.65fr)]">
        <div class="space-y-3">
            <section class="vx-card p-5"><div><h3 class="text-base font-bold">Operations</h3><div class="mt-1 text-xs text-[var(--vx-muted)]">The four operational handoffs that move this show forward.</div></div>
                <div class="flex items-center justify-between gap-4 border-t border-[var(--vx-divider)] py-3"><div><div class="text-sm font-semibold">Streamer Report</div><div class="mt-1 text-xs text-[var(--vx-muted)]">{{ $reportLabel }}{{ $report?->streamer?->name?' · '.$report->streamer->name:'' }}</div></div><a class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold" href="{{ $report?\App\Filament\Resources\StreamerLogResource::getUrl('edit',['record'=>$report]):\App\Filament\Pages\EndOfStreamForm::getUrl(['showId'=>$show->id]) }}">{{ $report?'Review':'Start' }}</a></div>
                <div class="flex items-center justify-between gap-4 border-t border-[var(--vx-divider)] py-3"><div><div class="text-sm font-semibold">Fulfillment</div><div class="mt-1 text-xs text-[var(--vx-muted)]">{{ $show->fulfillmentUsers->pluck('name')->filter()->join(', ') ?: 'Unassigned' }} · {{ $shipments->count() }} shipment(s)</div></div><a class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold" href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('view',['record'=>$show]) }}">Open</a></div>
                <div class="flex items-center justify-between gap-4 border-t border-[var(--vx-divider)] py-3"><div><div class="text-sm font-semibold">Inventory / COGS</div><div class="mt-1 text-xs text-[var(--vx-muted)]">${{ number_format((float)$pnl['cogs'],2) }} approved cost · {{ $show->latestDeductionRequest?'deduction request present':'no deduction request yet' }}</div></div><a class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold" href="{{ \App\Filament\Resources\ShowResource::getUrl('inventory',['record'=>$show]) }}">Breakdown</a></div>
                <div class="flex items-center justify-between gap-4 border-t border-[var(--vx-divider)] py-3"><div><div class="text-sm font-semibold">Payroll</div><div class="mt-1 text-xs text-[var(--vx-muted)]">{{ $payouts->count() }} payout line(s) · ${{ number_format((float)$payouts->sum('calculated_payout'),2) }}</div></div>@if($payouts->first()?->batch)<a class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view',['record'=>$payouts->first()->batch]) }}">Open Run</a>@else<a class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold" href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}">Payroll</a>@endif</div>
            </section>

            @php $ledger = app(\App\Services\ShowLedgerSummary::class)->for($show); @endphp
            <section class="vx-card p-5" aria-labelledby="show-ledger-h">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h3 id="show-ledger-h" class="text-base font-bold">Ledger</h3><div class="mt-1 text-xs text-[var(--vx-muted)]">Money Whatnot actually recorded for this show: sales, refunds, shipping and carrier adjustments, plus tips.</div>
                        @if(\App\Filament\Resources\WhatnotLedgerResource::canAccess())<a class="mt-2 inline-flex text-xs font-semibold text-violet-700 hover:underline dark:text-violet-300" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('index', ['show' => $show->id, 'period' => 'all']) }}">View every entry in the ledger ›</a>@endif</div>
                    <div class="text-right"><div class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Ledger net</div><div class="text-lg font-black tabular-nums {{ $ledger['ledger_net'] < 0 ? 'vx-bad' : '' }}">${{ number_format($ledger['ledger_net'], 2) }}</div><div class="text-xs text-[var(--vx-muted)]">Tips ${{ number_format($ledger['tips'], 2) }}</div></div>
                </div>
                @if($ledger['lines']->isEmpty())
                    <p class="mt-3 border-t border-[var(--vx-divider)] pt-3 text-xs text-[var(--vx-muted)]">No ledger entries tied to this show yet.</p>
                @else
                    <div class="mt-3 flex flex-wrap gap-2 border-t border-[var(--vx-divider)] pt-3 text-xs">
                        @foreach($ledger['by_category'] as $category => $total)
                            <span class="rounded-full bg-gray-100 px-3 py-1 font-semibold dark:bg-gray-800">{{ ucfirst($category) }} <span class="tabular-nums {{ $total < 0 ? 'vx-bad' : '' }}">${{ number_format($total, 2) }}</span></span>
                        @endforeach
                    </div>
                    <details class="mt-4 rounded-lg border border-[var(--vx-divider)] p-3" x-data="{ page: 1, size: 10 }">
                        <summary class="cursor-pointer text-sm font-semibold">After the show · {{ $ledger['after_show']->count() }} entries <span class="text-xs font-normal text-[var(--vx-muted)]">(expand)</span></summary>
                        @if($ledger['after_show']->isEmpty())
                            <p class="mt-2 text-xs text-[var(--vx-muted)]">Nothing has landed since the show ended.</p>
                        @else
                            <p class="mt-2 text-xs {{ $ledger['after_show_net'] < 0 ? 'vx-bad' : 'text-[var(--vx-muted)]' }}">{{ $ledger['after_show_net'] < 0 ? 'This show lost' : 'This show gained' }} ${{ number_format(abs($ledger['after_show_net']), 2) }} after it ended{{ $ledger['after_show_tips'] > 0 ? ', plus $'.number_format($ledger['after_show_tips'], 2).' in late tips' : '' }}.</p>
                            <div class="mt-2 divide-y divide-[var(--vx-divider)]">
                                @foreach($ledger['after_show'] as $line)
                                    <div x-show="Math.floor({{ $loop->index }} / size) + 1 === page" class="flex items-center justify-between gap-3 py-2 text-xs">
                                        <div class="min-w-0"><span class="font-semibold">{{ $line['at']?->format('M j') }}</span> · {{ ucfirst($line['category']) }}@if($line['order']) · order {{ $line['order'] }}@endif @if($line['status'] === 'waiting')<span class="text-amber-600">· not matched yet</span>@endif<div class="truncate text-[var(--vx-muted)]">{{ $line['message'] }}</div></div>
                                        <span class="shrink-0 font-semibold tabular-nums {{ $line['amount'] < 0 ? 'vx-bad' : 'vx-good' }}">{{ $line['amount'] < 0 ? '−' : '+' }}${{ number_format(abs($line['amount']), 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                            @if($ledger['after_show']->count() > 10)
                                <nav class="mt-3 flex items-center justify-between gap-3 text-xs" aria-label="After-show ledger pages">
                                    <button type="button" class="rounded border border-[var(--vx-divider)] px-3 py-2" @click="page = Math.max(1, page - 1)" :disabled="page === 1">Previous</button>
                                    <span x-text="'Page ' + page + ' of ' + Math.ceil({{ $ledger['after_show']->count() }} / size)"></span>
                                    <button type="button" class="rounded border border-[var(--vx-divider)] px-3 py-2" @click="page = Math.min(Math.ceil({{ $ledger['after_show']->count() }} / size), page + 1)" :disabled="page >= Math.ceil({{ $ledger['after_show']->count() }} / size)">Next</button>
                                </nav>
                            @endif
                        @endif
                    </details>
                    <details class="mt-4 rounded-lg border border-[var(--vx-divider)] p-3" x-data="{ ledgerCategory: 'all', page: 1, size: 10, categories: @js($ledger['lines']->pluck('category')->values()->all()), filteredIndexes() { return this.categories.map((category, index) => category === this.ledgerCategory || this.ledgerCategory === 'all' ? index : -1).filter(index => index !== -1); }, visible(index) { const indexes = this.filteredIndexes(); const position = indexes.indexOf(index); return position >= 0 && Math.floor(position / this.size) + 1 === this.page; } }">
                        <summary class="cursor-pointer text-sm font-semibold">All {{ $ledger['lines']->count() }} ledger entries <span class="text-xs font-normal text-[var(--vx-muted)]">(expand)</span></summary>
                        @if(\App\Filament\Resources\WhatnotLedgerResource::canAccess())
                            <a class="mt-2 inline-block text-xs font-semibold text-violet-700 hover:underline dark:text-violet-300" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('index', ['show' => $show->id, 'period' => 'all']) }}">Open full ledger</a>
                        @endif
                        <div class="mt-3 flex flex-wrap gap-1.5" role="group" aria-label="Filter show ledger entries">
                            @foreach(array_merge(['all'], $ledger['lines']->pluck('category')->filter()->unique()->sort()->values()->all()) as $category)
                                <button type="button" class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                                    :class="ledgerCategory === @js($category) ? 'border-violet-500 bg-violet-600 text-white' : 'border-[var(--vx-divider)] hover:border-violet-400'"
                                    :aria-pressed="ledgerCategory === @js($category)"
                                    @click="ledgerCategory = @js($category); page = 1">{{ $category === 'all' ? 'All entries' : ucfirst($category) }}</button>
                            @endforeach
                        </div>
                        <div class="mt-2 divide-y divide-[var(--vx-divider)]">
                            @foreach($ledger['lines'] as $line)
                                <div x-show="visible({{ $loop->index }})" class="flex items-start justify-between gap-3 py-2 text-xs">
                                    <div class="min-w-0">
                                        <div class="font-semibold">{{ $line['at']?->format('M j, g:ia') }} · {{ ucfirst($line['category']) }} · {{ $line['type'] }}</div>
                                        @if($line['order'])<div class="text-[var(--vx-muted)]">Order {{ $line['order'] }}</div>@endif
                                        @if($line['message'])<div class="break-words text-[var(--vx-muted)]">{{ $line['message'] }}</div>@endif
                                        @if($line['status'] === 'waiting')<span class="text-amber-600">Awaiting attribution</span>@endif
                                    </div>
                                    <span class="shrink-0 font-semibold tabular-nums {{ $line['amount'] < 0 ? 'vx-bad' : 'vx-good' }}">{{ $line['amount'] < 0 ? '−' : '+' }}${{ number_format(abs($line['amount']), 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <nav class="mt-3 flex items-center justify-between gap-3 text-xs" aria-label="Show ledger pages" x-show="filteredIndexes().length > size">
                            <button type="button" class="rounded border border-[var(--vx-divider)] px-3 py-2" @click="page = Math.max(1, page - 1)" :disabled="page === 1">Previous</button>
                            <span x-text="'Page ' + page + ' of ' + Math.max(1, Math.ceil(filteredIndexes().length / size))"></span>
                            <button type="button" class="rounded border border-[var(--vx-divider)] px-3 py-2" @click="page = Math.min(Math.ceil(filteredIndexes().length / size), page + 1)" :disabled="page >= Math.ceil(filteredIndexes().length / size)">Next</button>
                        </nav>
                    </details>
                @endif
            </section>
        </div>

        <aside class="space-y-3">
            <section class="vx-card p-5"><h3 class="text-base font-bold">Show Financials</h3><div class="mt-1 text-xs text-[var(--vx-muted)]">A compact operating P&L for the show.</div><div class="mt-3">@foreach([['Gross Sales',$pnl['gross']],['Whatnot Net',$pnl['net']],['Tips',$pnl['tips']],['COGS',-$pnl['cogs']],['Payroll',-$pnl['payouts']]] as [$label,$value])<div class="flex items-center justify-between gap-3 border-t border-[var(--vx-divider)] py-2 text-sm"><span class="text-gray-500">{{ $label }}</span><strong>${{ number_format((float)$value,2) }}</strong></div>@endforeach<div class="flex items-center justify-between gap-3 border-t border-[var(--vx-divider)] py-2 text-sm"><span class="font-bold">Show Net</span><strong class="{{ $pnl['margin']<0?'vx-bad':'vx-good' }}">${{ number_format((float)$pnl['margin'],2) }} · {{ number_format((float)$pnl['margin_pct'],1) }}%</strong></div></div>@if($analyticsMissing)<div class="mt-3 rounded-xl bg-amber-50 p-3 text-[11px] text-amber-700 dark:bg-amber-950/30 dark:text-amber-300">Whatnot analytics have not been captured for this completed show yet.</div>@endif</section>
            <section class="vx-card p-5"><details><summary class="cursor-pointer list-none"><div class="flex items-center justify-between"><div><h3>Show Details</h3><div class="mt-1 text-xs text-[var(--vx-muted)]">Secondary show and sync metadata.</div></div><span class="text-xs font-bold text-primary-600">View</span></div></summary><div class="mt-3">@foreach([['Orders',$show->units_sold],['Buyers',$show->buyers_count],['Giveaways',$show->giveaways_count],['Peak viewers',$show->max_concurrent_viewers],['Rating',$show->avg_order_rating],['Last sync',$show->last_synced_at?->copy()->timezone(\App\Support\BusinessTime::zone())->format('M j, g:i A')]] as [$label,$value])<div class="flex items-center justify-between gap-3 border-t border-[var(--vx-divider)] py-2 text-sm"><span class="text-gray-500">{{ $label }}</span><strong>{{ $value ?? '—' }}</strong></div>@endforeach</div></details></section>
        </aside>
    </div>
</div>
</x-filament-panels::page>