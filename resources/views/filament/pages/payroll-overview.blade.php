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
        .vx-payroll{display:grid;gap:1rem}.vx-card{border:1px solid rgb(229 231 235);border-radius:1rem;background:#fff;padding:1rem}.dark .vx-card{background:rgb(17 24 39);border-color:rgb(55 65 81)}
        .vx-eyebrow{font-size:.68rem;font-weight:850;letter-spacing:.08em;text-transform:uppercase;color:rgb(37 99 235)}.vx-title{font-size:1.35rem;font-weight:850;margin-top:.15rem}.vx-muted{font-size:.76rem;color:rgb(107 114 128)}
        .vx-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.6rem;margin-top:1rem}.vx-kpi{min-width:0}.vx-label{font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:rgb(107 114 128)}.vx-value{font-size:1.2rem;font-weight:850;margin-top:.15rem;font-variant-numeric:tabular-nums}
        .vx-actions{display:flex;flex-wrap:wrap;gap:.55rem;margin-top:1rem}.vx-btn{display:inline-flex;min-height:44px;align-items:center;justify-content:center;border-radius:.72rem;padding:.6rem .85rem;font-size:.76rem;font-weight:800}.vx-primary{background:rgb(37 99 235);color:#fff}.vx-secondary{border:1px solid rgb(209 213 219);background:rgb(249 250 251);color:rgb(55 65 81)}.dark .vx-secondary{border-color:rgb(75 85 99);background:rgb(31 41 55);color:rgb(229 231 235)}
        .vx-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(280px,.75fr);gap:1rem}.vx-section{font-size:.95rem;font-weight:850}.vx-warnings{display:grid;gap:.42rem;margin-top:.7rem;max-height:290px;overflow:auto}.vx-warning{display:flex;gap:.55rem;border-radius:.7rem;background:rgb(255 247 237);color:rgb(154 52 18);padding:.65rem .72rem;font-size:.76rem}.dark .vx-warning{background:rgba(154,52,18,.16);color:rgb(253 186 116)}.vx-ok{border-radius:.7rem;background:rgb(236 253 245);color:rgb(4 120 87);padding:.75rem;font-size:.78rem;font-weight:750}
        .vx-ready-grid{display:grid;grid-template-columns:1fr 1fr;gap:.55rem;margin-top:.7rem}.vx-ready-grid>div{border-radius:.78rem;background:rgb(249 250 251);padding:.8rem}.dark .vx-ready-grid>div{background:rgb(31 41 55)}
        .vx-filters{display:flex;gap:.45rem;overflow-x:auto;margin-top:.8rem;padding-bottom:.1rem}.vx-filter{display:inline-flex;align-items:center;gap:.35rem;min-height:40px;white-space:nowrap;border:1px solid rgb(229 231 235);border-radius:999px;padding:.45rem .7rem;font-size:.72rem;font-weight:800;color:rgb(75 85 99)}.dark .vx-filter{border-color:rgb(55 65 81);color:rgb(209 213 219)}.vx-filter.active{background:rgb(17 24 39);border-color:rgb(17 24 39);color:#fff}.dark .vx-filter.active{background:#fff;border-color:#fff;color:rgb(17 24 39)}.vx-count{font-variant-numeric:tabular-nums;opacity:.75}
        .vx-table{overflow-x:auto;margin-top:.75rem;border:1px solid rgb(229 231 235);border-radius:.85rem}.dark .vx-table{border-color:rgb(55 65 81)}.vx-head,.vx-row{display:grid;grid-template-columns:minmax(210px,1.5fr) 125px repeat(5,minmax(78px,.6fr)) 125px;gap:.55rem;align-items:center;min-width:980px;padding:.7rem .75rem}.vx-head{background:rgb(249 250 251);font-size:.64rem;text-transform:uppercase;letter-spacing:.04em;color:rgb(107 114 128);font-weight:850}.dark .vx-head{background:rgb(31 41 55)}.vx-row{border-top:1px solid rgb(243 244 246);font-size:.74rem}.dark .vx-row{border-color:rgb(55 65 81)}.vx-row:hover{background:rgb(249 250 251)}.dark .vx-row:hover{background:rgb(31 41 55)}
        .vx-show-name{font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.vx-show-sub{font-size:.66rem;color:rgb(107 114 128);margin-top:.12rem;line-height:1.25}.vx-num{text-align:right;font-variant-numeric:tabular-nums}.vx-status{display:inline-flex;border-radius:999px;padding:.27rem .55rem;font-size:.66rem;font-weight:850}.vx-ready{background:rgb(236 253 245);color:rgb(4 120 87)}.vx-blocked{background:rgb(255 247 237);color:rgb(194 65 12)}.vx-run{background:rgb(239 246 255);color:rgb(29 78 216)}.vx-paid{background:rgb(236 253 245);color:rgb(4 120 87)}.vx-resolve{display:inline-flex;min-height:38px;align-items:center;justify-content:center;border-radius:.65rem;padding:.45rem .65rem;font-size:.68rem;font-weight:850;border:1px solid rgb(209 213 219);white-space:nowrap}.vx-resolve.primary{background:rgb(37 99 235);border-color:rgb(37 99 235);color:#fff}.vx-resolve.success{background:rgb(5 150 105);border-color:rgb(5 150 105);color:#fff}.vx-resolve.warning{background:rgb(255 247 237);border-color:rgb(253 186 116);color:rgb(154 52 18)}.dark .vx-resolve.warning{background:rgba(154,52,18,.16);border-color:rgb(154 52 18);color:rgb(253 186 116)}
        .vx-list{display:grid;gap:.5rem;margin-top:.7rem}.vx-list-row{display:flex;align-items:center;justify-content:space-between;gap:.8rem;border:1px solid rgb(229 231 235);border-radius:.78rem;padding:.72rem}.dark .vx-list-row{border-color:rgb(55 65 81)}
        .vx-tools{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;margin-top:.7rem}.vx-tool{border:1px solid rgb(229 231 235);border-radius:.8rem;padding:.8rem;min-height:72px;display:flex;flex-direction:column;justify-content:center}.dark .vx-tool{border-color:rgb(55 65 81)}.vx-tool strong{font-size:.8rem}.vx-tool span{font-size:.68rem;color:rgb(107 114 128);margin-top:.12rem}
        @media(max-width:950px){.vx-kpis{grid-template-columns:repeat(3,1fr)}.vx-grid{grid-template-columns:1fr}}
        @media(max-width:600px){
            .vx-card{padding:.85rem;border-radius:.85rem}.vx-kpis{grid-template-columns:1fr 1fr}.vx-actions{display:grid;grid-template-columns:1fr 1fr}.vx-btn{width:100%;min-height:48px}.vx-tools{grid-template-columns:1fr}.vx-ready-grid{grid-template-columns:1fr 1fr}
            .vx-table{overflow:visible;border:0;border-radius:0;background:transparent}.vx-head{display:none}.vx-row{min-width:0;grid-template-columns:1fr 1fr;gap:.65rem .75rem;margin-top:.7rem;padding:.85rem;border:1px solid rgb(229 231 235)!important;border-radius:.85rem;background:#fff;box-shadow:0 4px 14px rgba(15,23,42,.04)}.dark .vx-row{border-color:rgb(55 65 81)!important;background:rgb(17 24 39)}
            .vx-row>a:first-child{grid-column:1/-1;padding-bottom:.55rem;border-bottom:1px solid rgb(243 244 246)}.dark .vx-row>a:first-child{border-color:rgb(55 65 81)}.vx-row>div:nth-child(2){grid-column:1/-1}.vx-row>div:nth-child(n+3):nth-child(-n+7){text-align:left!important;border-radius:.65rem;background:rgb(249 250 251);padding:.6rem}.dark .vx-row>div:nth-child(n+3):nth-child(-n+7){background:rgb(31 41 55)}
            .vx-row>div:nth-child(n+3):nth-child(-n+7)::before{display:block;margin-bottom:.2rem;font-size:.58rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:rgb(107 114 128)}.vx-row>div:nth-child(3)::before{content:'Sales'}.vx-row>div:nth-child(4)::before{content:'Whatnot Net'}.vx-row>div:nth-child(5)::before{content:'COGS'}.vx-row>div:nth-child(6)::before{content:'Payroll'}.vx-row>div:nth-child(7)::before{content:'Show Net'}.vx-row>div:nth-child(8){grid-column:1/-1}.vx-resolve{width:100%;min-height:46px;font-size:.75rem}.vx-status{font-size:.72rem;padding:.35rem .6rem}.vx-list-row{align-items:flex-start}.vx-title{font-size:1.15rem}
        }
    </style>

    <div class="vx-payroll">
        <section class="vx-card">
            <div class="vx-eyebrow">Weekly payroll workspace</div>
            @if($current)
                <div class="vx-title">{{ $current->week_start->format('M j') }} – {{ $current->week_end->format('M j, Y') }}</div>
                <div class="vx-muted">{{ \App\Models\WeeklyPayoutBatch::statusLabels()[$current->status] ?? ucfirst($current->status) }} · resolve blocked shows here before finalizing the run.</div>
            @else
                <div class="vx-title">{{ now()->startOfWeek()->format('M j') }} – {{ now()->endOfWeek()->format('M j, Y') }}</div>
                <div class="vx-muted">No pay run exists for this week yet. The show readiness board below is still live.</div>
            @endif

            <div class="vx-kpis">
                <div class="vx-kpi"><div class="vx-label">Payroll Total</div><div class="vx-value">${{ number_format((float)($current?->total_payout ?? 0),2) }}</div></div>
                <div class="vx-kpi"><div class="vx-label">People</div><div class="vx-value">{{ $breakdown['people'] }}</div></div>
                <div class="vx-kpi"><div class="vx-label">Streamer Pay</div><div class="vx-value">${{ number_format($breakdown['streamer_total'],0) }}</div></div>
                <div class="vx-kpi"><div class="vx-label">Fulfillment Pay</div><div class="vx-value">${{ number_format($breakdown['fulfillment_total'],0) }}</div></div>
                <div class="vx-kpi"><div class="vx-label">Ready / In Run</div><div class="vx-value">{{ $readiness['ready'] }}</div></div>
                <div class="vx-kpi"><div class="vx-label">Blocked</div><div class="vx-value">{{ $readiness['review'] }}</div></div>
            </div>

            <div class="vx-actions">
                @if($current)
                    <a class="vx-btn vx-primary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view', ['record' => $current]) }}">Review / Finalize Run</a>
                @else
                    <a class="vx-btn vx-primary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('create') }}">Create Pay Run</a>
                @endif
                <a class="vx-btn vx-secondary" href="{{ \App\Filament\Resources\StreamerResource::getUrl('index') }}">People & Rates</a>
                <a class="vx-btn vx-secondary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index') }}">Pay Run History</a>
                <a class="vx-btn vx-secondary" href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('index') }}">All Inventory</a>
            </div>
        </section>

        <div class="vx-grid">
            <section class="vx-card">
                <div class="vx-section">Needs Attention</div>
                <div class="vx-muted">Anything here can affect whether the weekly run is safe to finalize.</div>
                <div class="vx-warnings">
                    @forelse($attention as $warning)
                        <div class="vx-warning"><strong>!</strong><span>{{ $warning }}</span></div>
                    @empty
                        <div class="vx-ok">✓ No payroll readiness issues found for the current week.</div>
                    @endforelse
                </div>
            </section>

            <section class="vx-card">
                <div class="vx-section">Run Readiness</div>
                <div class="vx-muted">The weekly run should only be finalized when Blocked reaches zero.</div>
                <div class="vx-ready-grid">
                    <div><div class="vx-label">Ready / In Run</div><div class="vx-value">{{ $readiness['ready'] }}</div><div class="vx-muted">of {{ $readiness['shows'] }} shows</div></div>
                    <div><div class="vx-label">Show Payroll</div><div class="vx-value">${{ number_format($readiness['show_payroll'],2) }}</div><div class="vx-muted">calculated show payouts</div></div>
                </div>
            </section>
        </div>

        <section class="vx-card">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="vx-section">Shows in This Pay Period</div>
                    <div class="vx-muted">Filter by workflow state, see the blocker, then jump directly to the screen that fixes it.</div>
                </div>
                <a href="{{ \App\Filament\Resources\ShowResource::getUrl('index') }}" class="text-xs font-semibold text-primary-600">All Shows →</a>
            </div>

            <div class="vx-filters">
                @foreach([
                    'all' => ['All',$workflow['all']],
                    'blocked' => ['Blocked',$workflow['blocked']],
                    'ready' => ['Ready',$workflow['ready']],
                    'in_run' => ['In Pay Run',$workflow['in_run']],
                    'paid' => ['Paid',$workflow['paid']],
                ] as $key => [$label,$count])
                    <a class="vx-filter {{ $activeWorkflow === $key ? 'active' : '' }}" href="{{ $key === 'all' ? $baseUrl : $baseUrl.'?workflow='.$key }}">{{ $label }} <span class="vx-count">{{ $count }}</span></a>
                @endforeach
            </div>

            <div class="vx-table">
                <div class="vx-head"><div>Show / Blocker</div><div>Status</div><div class="vx-num">Sales</div><div class="vx-num">Net</div><div class="vx-num">COGS</div><div class="vx-num">Payroll</div><div class="vx-num">Show Net</div><div>Resolve</div></div>
                @forelse($shows as $show)
                    @php
                        $state = $show->getAttribute('workflow_state');
                        $pnl = $show->getAttribute('pnl_summary');
                        $payRunProblems = $show->getAttribute('payrun_problems') ?? [];
                        $resolution = $this->showResolution($show);
                        $stateClass = $payRunProblems !== [] ? 'vx-blocked' : match($state['key']) {
                            'payroll_ready' => 'vx-ready',
                            'payroll' => 'vx-run',
                            'paid' => 'vx-paid',
                            default => 'vx-blocked',
                        };
                        $stateLabel = $payRunProblems !== [] ? 'Needs Recalculation' : $state['label'];
                    @endphp
                    <div class="vx-row">
                        <a class="min-w-0" href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}">
                            <div class="vx-show-name">{{ $show->title }}</div>
                            <div class="vx-show-sub">{{ $show->show_date?->format('M j') }} · {{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer' }}</div>
                            @if($payRunProblems !== [])
                                <div class="vx-show-sub" style="color:rgb(194 65 12);font-weight:700">{{ preg_replace('/^'.preg_quote($show->title, '/').' — /', '', $payRunProblems[0]) }}@if(count($payRunProblems) > 1) · +{{ count($payRunProblems) - 1 }} more@endif</div>
                            @elseif(!empty($state['blockers']))
                                <div class="vx-show-sub" style="color:rgb(194 65 12);font-weight:700">{{ $state['blockers'][0] }}@if(count($state['blockers']) > 1) · +{{ count($state['blockers']) - 1 }} more@endif</div>
                            @else
                                <div class="vx-show-sub">{{ $state['description'] }}</div>
                            @endif
                        </a>
                        <div><span class="vx-status {{ $stateClass }}">{{ $stateLabel }}</span></div>
                        <div class="vx-num">${{ number_format((float)($pnl['gross'] ?? 0),0) }}</div>
                        <div class="vx-num">${{ number_format((float)($pnl['net'] ?? 0),0) }}</div>
                        <div class="vx-num">${{ number_format((float)($pnl['cogs'] ?? 0),0) }}</div>
                        <div class="vx-num">${{ number_format((float)($pnl['payouts'] ?? 0),0) }}</div>
                        <div class="vx-num" style="font-weight:850">${{ number_format((float)($pnl['margin'] ?? 0),0) }}</div>
                        <div><a class="vx-resolve {{ $resolution['tone'] }}" href="{{ $resolution['url'] }}">{{ $resolution['label'] }} →</a></div>
                    </div>
                @empty
                    <div class="p-6 text-center text-sm text-gray-500">No shows match this workflow filter.</div>
                @endforelse
            </div>
        </section>

        <section class="vx-card">
            <div class="vx-section">Recent Pay Runs</div>
            <div class="vx-muted">Review prior weeks, exports and payment status without leaving the payroll workspace.</div>
            <div class="vx-list">
                @forelse($recent as $run)
                    <a class="vx-list-row" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view', ['record' => $run]) }}">
                        <div><strong>{{ $run->week_start->format('M j') }} – {{ $run->week_end->format('M j, Y') }}</strong><div class="vx-muted">{{ $run->payouts_count }} payout entries · ${{ number_format((float)$run->total_payout,2) }}</div></div>
                        <div class="text-right"><span class="vx-status">{{ \App\Models\WeeklyPayoutBatch::statusLabels()[$run->status] ?? ucfirst($run->status) }}</span><div class="vx-muted mt-1">View →</div></div>
                    </a>
                @empty
                    <div class="vx-muted">No pay run history yet.</div>
                @endforelse
            </div>
        </section>

        <section class="vx-card">
            <div class="vx-section">Payroll Tools</div>
            <div class="vx-tools">
                <a class="vx-tool" href="{{ \App\Filament\Resources\StreamerResource::getUrl('index') }}"><strong>People & Compensation</strong><span>Default structures, rates and individual overrides.</span></a>
                <a class="vx-tool" href="{{ \App\Filament\Resources\StreamerLogResource::getUrl('index') }}"><strong>Show Report Review</strong><span>Approve streamer reports and resolve report issues.</span></a>
                <a class="vx-tool" href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('index') }}"><strong>Fulfillment Center</strong><span>Resolve logged-item fulfillment and PWE / label verification.</span></a>
            </div>
        </section>
    </div>
</x-filament-panels::page>
