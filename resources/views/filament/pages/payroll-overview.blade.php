<x-filament-panels::page>
    @php
        $current = $this->currentPayRun();
        $attention = $this->needsAttention();
        $breakdown = $this->currentBreakdown();
        $recent = $this->recentPayRuns();
        $shows = $this->currentWeekShows();
        $readiness = $this->readinessSummary();
    @endphp

    <style>
        .vx-payroll { display:grid; gap:1rem; }
        .vx-payroll-hero { border:1px solid rgb(229 231 235); border-radius:1rem; padding:1.1rem; background:white; box-shadow:0 1px 2px rgba(15,23,42,.04); }
        .dark .vx-payroll-hero,.dark .vx-pay-card { background:rgb(17 24 39); border-color:rgb(55 65 81); }
        .vx-pay-eyebrow { font-size:.72rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:rgb(37 99 235); }
        .vx-pay-title { margin-top:.2rem; font-size:1.35rem; font-weight:850; }
        .vx-pay-muted { color:rgb(107 114 128); font-size:.8rem; }
        .vx-pay-kpis { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:.65rem; margin-top:1rem; }
        .vx-pay-card { border:1px solid rgb(229 231 235); border-radius:.9rem; padding:.9rem; background:white; }
        .vx-pay-card-label { color:rgb(107 114 128); font-size:.7rem; font-weight:750; text-transform:uppercase; letter-spacing:.04em; }
        .vx-pay-card-value { margin-top:.2rem; font-size:1.2rem; font-weight:850; }
        .vx-pay-actions { display:flex; flex-wrap:wrap; gap:.6rem; margin-top:1rem; }
        .vx-pay-action { min-height:44px; display:inline-flex; align-items:center; justify-content:center; gap:.4rem; border-radius:.75rem; padding:.65rem .9rem; font-size:.8rem; font-weight:800; border:1px solid transparent; }
        .vx-pay-primary { background:rgb(37 99 235); color:white; }
        .vx-pay-secondary { border-color:rgb(209 213 219); background:rgb(249 250 251); color:rgb(55 65 81); }
        .dark .vx-pay-secondary { border-color:rgb(75 85 99); background:rgb(31 41 55); color:rgb(229 231 235); }
        .vx-pay-grid { display:grid; grid-template-columns:minmax(0,1.3fr) minmax(0,.7fr); gap:1rem; }
        .vx-pay-section-title { font-size:1rem; font-weight:850; }
        .vx-pay-attention { display:grid; gap:.45rem; margin-top:.75rem; max-height:270px; overflow:auto; }
        .vx-pay-warning { display:flex; align-items:flex-start; gap:.6rem; border-radius:.7rem; background:rgb(255 247 237); color:rgb(154 52 18); padding:.65rem .75rem; font-size:.78rem; }
        .dark .vx-pay-warning { background:rgba(154,52,18,.16); color:rgb(253 186 116); }
        .vx-pay-ok { border-radius:.7rem; background:rgb(236 253 245); color:rgb(4 120 87); padding:.75rem; font-size:.8rem; font-weight:700; }
        .vx-pay-split { display:grid; grid-template-columns:1fr 1fr; gap:.65rem; margin-top:.75rem; }
        .vx-pay-split > div { border-radius:.8rem; background:rgb(249 250 251); padding:.8rem; }
        .dark .vx-pay-split > div { background:rgb(31 41 55); }
        .vx-pay-list { display:grid; gap:.55rem; margin-top:.75rem; }
        .vx-pay-row { display:flex; align-items:center; justify-content:space-between; gap:.75rem; border:1px solid rgb(229 231 235); border-radius:.8rem; padding:.75rem; }
        .dark .vx-pay-row { border-color:rgb(55 65 81); }
        .vx-pay-status { display:inline-flex; border-radius:999px; padding:.25rem .55rem; font-size:.68rem; font-weight:800; background:rgb(243 244 246); }
        .dark .vx-pay-status { background:rgb(31 41 55); }
        .vx-pay-links { display:grid; grid-template-columns:repeat(3,1fr); gap:.65rem; }
        .vx-pay-link { min-height:70px; border:1px solid rgb(229 231 235); border-radius:.85rem; padding:.8rem; display:flex; flex-direction:column; justify-content:center; gap:.15rem; }
        .dark .vx-pay-link { border-color:rgb(55 65 81); }
        .vx-pay-link strong { font-size:.82rem; }
        .vx-pay-link span { font-size:.7rem; color:rgb(107 114 128); }
        .vx-show-table{overflow-x:auto;margin-top:.75rem;border:1px solid rgb(229 231 235);border-radius:.8rem}.dark .vx-show-table{border-color:rgb(55 65 81)}
        .vx-show-head,.vx-show-line{display:grid;grid-template-columns:minmax(180px,1.5fr) 90px repeat(5,minmax(90px,.65fr)) 120px 22px;gap:.55rem;align-items:center;min-width:900px;padding:.65rem .75rem}
        .vx-show-head{background:rgb(249 250 251);font-size:.66rem;text-transform:uppercase;letter-spacing:.04em;color:rgb(107 114 128);font-weight:800}.dark .vx-show-head{background:rgb(31 41 55)}
        .vx-show-line{border-top:1px solid rgb(243 244 246);font-size:.75rem}.dark .vx-show-line{border-color:rgb(55 65 81)}.vx-show-line:hover{background:rgb(249 250 251)}.dark .vx-show-line:hover{background:rgb(31 41 55)}
        .vx-show-name{font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.vx-show-sub{font-size:.67rem;color:rgb(107 114 128);margin-top:.1rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .vx-show-num{text-align:right;font-variant-numeric:tabular-nums}.vx-ready{background:rgb(236 253 245);color:rgb(4 120 87)}.vx-review{background:rgb(255 247 237);color:rgb(194 65 12)}.vx-inrun{background:rgb(239 246 255);color:rgb(29 78 216)}
        @media(max-width:900px){.vx-pay-kpis{grid-template-columns:repeat(3,1fr)}.vx-pay-grid{grid-template-columns:1fr}}
        @media(max-width:600px){
            .vx-payroll { gap:.75rem; }
            .vx-payroll-hero,.vx-pay-card { border-radius:.85rem; padding:.85rem; }
            .vx-pay-kpis { grid-template-columns:1fr 1fr; gap:.5rem; }
            .vx-pay-actions { display:grid; grid-template-columns:1fr 1fr; }
            .vx-pay-action { min-height:48px; width:100%; padding:.6rem; }
            .vx-pay-split { grid-template-columns:1fr 1fr; }
            .vx-pay-links { grid-template-columns:1fr; }
        }
    </style>

    <div class="vx-payroll">
        <section class="vx-payroll-hero">
            <div class="vx-pay-eyebrow">Weekly payroll workspace</div>
            @if($current)
                <div class="vx-pay-title">{{ $current->week_start->format('M j') }} – {{ $current->week_end->format('M j, Y') }}</div>
                <div class="vx-pay-muted">{{ \App\Models\WeeklyPayoutBatch::statusLabels()[$current->status] ?? ucfirst($current->status) }} · shows below roll into this week's calculations.</div>
                <div class="vx-pay-kpis">
                    <div><div class="vx-pay-card-label">Payroll Total</div><div class="vx-pay-card-value">${{ number_format((float)$current->total_payout, 2) }}</div></div>
                    <div><div class="vx-pay-card-label">People</div><div class="vx-pay-card-value">{{ $breakdown['people'] }}</div></div>
                    <div><div class="vx-pay-card-label">Streamer Pay</div><div class="vx-pay-card-value">${{ number_format($breakdown['streamer_total'],0) }}</div></div>
                    <div><div class="vx-pay-card-label">Fulfillment Pay</div><div class="vx-pay-card-value">${{ number_format($breakdown['fulfillment_total'],0) }}</div></div>
                    <div><div class="vx-pay-card-label">Shows</div><div class="vx-pay-card-value">{{ $readiness['shows'] }}</div></div>
                    <div><div class="vx-pay-card-label">Need Review</div><div class="vx-pay-card-value">{{ $readiness['review'] }}</div></div>
                </div>
                <div class="vx-pay-actions">
                    <a class="vx-pay-action vx-pay-primary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view', ['record' => $current]) }}">Review / Finalize Run</a>
                    <a class="vx-pay-action vx-pay-secondary" href="{{ \App\Filament\Resources\StreamerResource::getUrl('index') }}">People & Rates</a>
                    <a class="vx-pay-action vx-pay-secondary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index') }}">Pay Run History</a>
                    <a class="vx-pay-action vx-pay-secondary" href="{{ \App\Filament\Pages\InventoryCatalog::getUrl() }}">Inventory Catalog</a>
                </div>
            @else
                <div class="vx-pay-title">No current pay run yet</div>
                <div class="vx-pay-muted">The weekly show calculations are still visible below. Create a pay run when you're ready to review payroll.</div>
                <div class="vx-pay-actions"><a class="vx-pay-action vx-pay-primary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('create') }}">Create Pay Run</a></div>
            @endif
        </section>

        <div class="vx-pay-grid">
            <section class="vx-pay-card">
                <div class="vx-pay-section-title">Needs Attention</div>
                <div class="vx-pay-muted">Show blockers and payroll issues that should be cleared before finalizing.</div>
                <div class="vx-pay-attention">
                    @forelse($attention as $warning)
                        <div class="vx-pay-warning"><strong>!</strong><span>{{ $warning }}</span></div>
                    @empty
                        <div class="vx-pay-ok">✓ No payroll readiness issues found for the current week.</div>
                    @endforelse
                </div>
            </section>

            <section class="vx-pay-card">
                <div class="vx-pay-section-title">Readiness</div>
                <div class="vx-pay-muted">A quick check before the run is finalized.</div>
                <div class="vx-pay-split">
                    <div><div class="vx-pay-card-label">Ready / In Run</div><div class="vx-pay-card-value">{{ $readiness['ready'] }}</div><div class="vx-pay-muted">of {{ $readiness['shows'] }} shows</div></div>
                    <div><div class="vx-pay-card-label">Show Payroll</div><div class="vx-pay-card-value">${{ number_format($readiness['show_payroll'],2) }}</div><div class="vx-pay-muted">calculated on shows</div></div>
                </div>
            </section>
        </div>

        <section class="vx-pay-card">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="vx-pay-section-title">Shows in This Pay Period</div>
                    <div class="vx-pay-muted">Sales → COGS → payroll → final show net, with the current operational status.</div>
                </div>
                <a href="{{ \App\Filament\Resources\ShowResource::getUrl('index') }}" class="text-xs font-semibold text-primary-600">All Shows →</a>
            </div>
            <div class="vx-show-table">
                <div class="vx-show-head"><div>Show</div><div>Status</div><div class="vx-show-num">Sales</div><div class="vx-show-num">Net</div><div class="vx-show-num">COGS</div><div class="vx-show-num">Payroll</div><div class="vx-show-num">Show Net</div><div class="vx-show-num">Margin</div><div></div></div>
                @forelse($shows as $show)
                    @php
                        $state = $show->getAttribute('workflow_state');
                        $pnl = $show->getAttribute('pnl_summary');
                        $stateClass = in_array($state['key'], ['payroll_ready','paid'], true) ? 'vx-ready' : ($state['key'] === 'payroll' ? 'vx-inrun' : 'vx-review');
                    @endphp
                    <a class="vx-show-line" href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}">
                        <div class="min-w-0"><div class="vx-show-name">{{ $show->title }}</div><div class="vx-show-sub">{{ $show->show_date?->format('M j') }} · {{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer' }}@if($state['blockers']) · {{ $state['blockers'][0] }}@endif</div></div>
                        <div><span class="vx-pay-status {{ $stateClass }}">{{ $state['label'] }}</span></div>
                        <div class="vx-show-num">${{ number_format((float)($pnl['gross'] ?? 0),0) }}</div>
                        <div class="vx-show-num">${{ number_format((float)($pnl['net'] ?? 0),0) }}</div>
                        <div class="vx-show-num">${{ number_format((float)($pnl['cogs'] ?? 0),0) }}</div>
                        <div class="vx-show-num">${{ number_format((float)($pnl['payouts'] ?? 0),0) }}</div>
                        <div class="vx-show-num" style="font-weight:800">${{ number_format((float)($pnl['margin'] ?? 0),0) }}</div>
                        <div class="vx-show-num">{{ number_format((float)($pnl['margin_pct'] ?? 0),1) }}%</div>
                        <x-heroicon-m-chevron-right class="h-4 w-4 text-gray-300" />
                    </a>
                @empty
                    <div class="p-6 text-center text-sm text-gray-500">No shows fall inside this pay period yet.</div>
                @endforelse
            </div>
        </section>

        <section class="vx-pay-card">
            <div class="vx-pay-section-title">Recent Pay Runs</div>
            <div class="vx-pay-muted">Open a week to review its employee calculations, status, exports and payment actions.</div>
            <div class="vx-pay-list">
                @forelse($recent as $run)
                    <a class="vx-pay-row" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view', ['record' => $run]) }}">
                        <div><strong>{{ $run->week_start->format('M j') }} – {{ $run->week_end->format('M j, Y') }}</strong><div class="vx-pay-muted">{{ $run->payouts_count }} payout entries · ${{ number_format((float)$run->total_payout,2) }}</div></div>
                        <div style="text-align:right"><span class="vx-pay-status">{{ \App\Models\WeeklyPayoutBatch::statusLabels()[$run->status] ?? ucfirst($run->status) }}</span><div class="vx-pay-muted" style="margin-top:.3rem">View →</div></div>
                    </a>
                @empty
                    <div class="vx-pay-muted" style="padding:.75rem 0">No pay runs have been created yet.</div>
                @endforelse
            </div>
        </section>

        <section class="vx-pay-card">
            <div class="vx-pay-section-title" style="margin-bottom:.75rem">Payroll Tools</div>
            <div class="vx-pay-links">
                <a class="vx-pay-link" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index') }}"><strong>Pay Runs</strong><span>Weekly payroll history</span></a>
                <a class="vx-pay-link" href="{{ \App\Filament\Resources\StreamerResource::getUrl('index') }}"><strong>People & Rates</strong><span>Streamer and fulfillment compensation</span></a>
                <a class="vx-pay-link" href="{{ \App\Filament\Resources\PayoutResource::getUrl('index') }}"><strong>Payout Entries</strong><span>Detailed employee calculations</span></a>
            </div>
        </section>
    </div>
</x-filament-panels::page>
