<x-filament-panels::page>
    @php
        $current = $this->currentPayRun();
        $attention = $this->needsAttention();
        $breakdown = $this->currentBreakdown();
        $recent = $this->recentPayRuns();
    @endphp

    <style>
        .vx-payroll { display:grid; gap:1rem; }
        .vx-payroll-hero { border:1px solid rgb(229 231 235); border-radius:1rem; padding:1.1rem; background:white; box-shadow:0 1px 2px rgba(15,23,42,.04); }
        .dark .vx-payroll-hero,.dark .vx-pay-card { background:rgb(17 24 39); border-color:rgb(55 65 81); }
        .vx-pay-eyebrow { font-size:.72rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:rgb(37 99 235); }
        .vx-pay-title { margin-top:.2rem; font-size:1.35rem; font-weight:850; }
        .vx-pay-muted { color:rgb(107 114 128); font-size:.85rem; }
        .vx-pay-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; margin-top:1rem; }
        .vx-pay-card { border:1px solid rgb(229 231 235); border-radius:.9rem; padding:.9rem; background:white; }
        .vx-pay-card-label { color:rgb(107 114 128); font-size:.74rem; font-weight:750; text-transform:uppercase; letter-spacing:.04em; }
        .vx-pay-card-value { margin-top:.2rem; font-size:1.25rem; font-weight:850; }
        .vx-pay-actions { display:flex; flex-wrap:wrap; gap:.6rem; margin-top:1rem; }
        .vx-pay-action { min-height:46px; display:inline-flex; align-items:center; justify-content:center; gap:.4rem; border-radius:.75rem; padding:.7rem .9rem; font-size:.84rem; font-weight:800; border:1px solid transparent; }
        .vx-pay-primary { background:rgb(37 99 235); color:white; }
        .vx-pay-success { background:rgb(5 150 105); color:white; }
        .vx-pay-secondary { border-color:rgb(209 213 219); background:rgb(249 250 251); color:rgb(55 65 81); }
        .dark .vx-pay-secondary { border-color:rgb(75 85 99); background:rgb(31 41 55); color:rgb(229 231 235); }
        .vx-pay-grid { display:grid; grid-template-columns:minmax(0,1.25fr) minmax(0,.75fr); gap:1rem; }
        .vx-pay-section-title { font-size:1rem; font-weight:850; }
        .vx-pay-attention { display:grid; gap:.5rem; margin-top:.75rem; }
        .vx-pay-warning { display:flex; align-items:flex-start; gap:.6rem; border-radius:.7rem; background:rgb(255 247 237); color:rgb(154 52 18); padding:.7rem .8rem; font-size:.82rem; }
        .dark .vx-pay-warning { background:rgba(154,52,18,.16); color:rgb(253 186 116); }
        .vx-pay-ok { border-radius:.7rem; background:rgb(236 253 245); color:rgb(4 120 87); padding:.75rem; font-size:.83rem; font-weight:700; }
        .vx-pay-split { display:grid; grid-template-columns:1fr 1fr; gap:.65rem; margin-top:.75rem; }
        .vx-pay-split > div { border-radius:.8rem; background:rgb(249 250 251); padding:.8rem; }
        .dark .vx-pay-split > div { background:rgb(31 41 55); }
        .vx-pay-list { display:grid; gap:.55rem; margin-top:.75rem; }
        .vx-pay-row { display:flex; align-items:center; justify-content:space-between; gap:.75rem; border:1px solid rgb(229 231 235); border-radius:.8rem; padding:.75rem; }
        .dark .vx-pay-row { border-color:rgb(55 65 81); }
        .vx-pay-status { display:inline-flex; border-radius:999px; padding:.25rem .55rem; font-size:.7rem; font-weight:800; background:rgb(243 244 246); }
        .dark .vx-pay-status { background:rgb(31 41 55); }
        .vx-pay-links { display:grid; grid-template-columns:repeat(3,1fr); gap:.65rem; }
        .vx-pay-link { min-height:74px; border:1px solid rgb(229 231 235); border-radius:.85rem; padding:.8rem; display:flex; flex-direction:column; justify-content:center; gap:.15rem; }
        .dark .vx-pay-link { border-color:rgb(55 65 81); }
        .vx-pay-link strong { font-size:.85rem; }
        .vx-pay-link span { font-size:.72rem; color:rgb(107 114 128); }
        @media(max-width:768px){
            .vx-payroll { gap:.75rem; }
            .vx-payroll-hero,.vx-pay-card { border-radius:.85rem; padding:.85rem; }
            .vx-pay-kpis { grid-template-columns:1fr 1fr; gap:.55rem; }
            .vx-pay-grid { grid-template-columns:1fr; gap:.75rem; }
            .vx-pay-actions { display:grid; grid-template-columns:1fr; }
            .vx-pay-action { min-height:50px; width:100%; }
            .vx-pay-split { grid-template-columns:1fr 1fr; }
            .vx-pay-links { grid-template-columns:1fr 1fr; }
            .vx-pay-link { min-height:68px; }
            .vx-pay-row { align-items:flex-start; }
        }
    </style>

    <div class="vx-payroll">
        <section class="vx-payroll-hero">
            <div class="vx-pay-eyebrow">Payroll workspace</div>
            @if($current)
                <div class="vx-pay-title">Current Pay Run</div>
                <div class="vx-pay-muted">{{ $current->week_start->format('M j') }} – {{ $current->week_end->format('M j, Y') }} · {{ \App\Models\WeeklyPayoutBatch::statusLabels()[$current->status] ?? ucfirst($current->status) }}</div>
                <div class="vx-pay-kpis">
                    <div><div class="vx-pay-card-label">Payroll Total</div><div class="vx-pay-card-value">${{ number_format((float)$current->total_payout, 2) }}</div></div>
                    <div><div class="vx-pay-card-label">People</div><div class="vx-pay-card-value">{{ $breakdown['people'] }}</div></div>
                    <div><div class="vx-pay-card-label">Streamers</div><div class="vx-pay-card-value">{{ $breakdown['streamers'] }}</div></div>
                    <div><div class="vx-pay-card-label">Fulfillment</div><div class="vx-pay-card-value">{{ $breakdown['fulfillment'] }}</div></div>
                </div>
                <div class="vx-pay-actions">
                    <a class="vx-pay-action vx-pay-primary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view', ['record' => $current]) }}">Review Current Pay Run →</a>
                    <a class="vx-pay-action vx-pay-secondary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index') }}">All Pay Runs</a>
                    <a class="vx-pay-action vx-pay-secondary" href="{{ \App\Filament\Resources\StreamerResource::getUrl('index') }}">People & Rates</a>
                </div>
            @else
                <div class="vx-pay-title">No current pay run yet</div>
                <div class="vx-pay-muted">Create the weekly pay run when you're ready to review payroll.</div>
                <div class="vx-pay-actions"><a class="vx-pay-action vx-pay-primary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('create') }}">Create Pay Run</a></div>
            @endif
        </section>

        <div class="vx-pay-grid">
            <section class="vx-pay-card">
                <div class="vx-pay-section-title">Needs Attention</div>
                <div class="vx-pay-muted">Anything that should be reviewed before payroll is finalized.</div>
                <div class="vx-pay-attention">
                    @forelse($attention as $warning)
                        <div class="vx-pay-warning"><strong>!</strong><span>{{ $warning }}</span></div>
                    @empty
                        <div class="vx-pay-ok">✓ No payroll readiness issues found for the current pay run.</div>
                    @endforelse
                </div>
            </section>

            <section class="vx-pay-card">
                <div class="vx-pay-section-title">This Week</div>
                <div class="vx-pay-muted">Quick breakdown by team function.</div>
                <div class="vx-pay-split">
                    <div><div class="vx-pay-card-label">Streamers</div><div class="vx-pay-card-value">${{ number_format($breakdown['streamer_total'],2) }}</div><div class="vx-pay-muted">{{ $breakdown['streamers'] }} people</div></div>
                    <div><div class="vx-pay-card-label">Fulfillment</div><div class="vx-pay-card-value">${{ number_format($breakdown['fulfillment_total'],2) }}</div><div class="vx-pay-muted">{{ $breakdown['fulfillment'] }} people</div></div>
                </div>
            </section>
        </div>

        <section class="vx-pay-card">
            <div class="vx-pay-section-title">Recent Pay Runs</div>
            <div class="vx-pay-muted">Open a week to review its calculations, status, exports and payment actions.</div>
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
                <a class="vx-pay-link" href="{{ \App\Filament\Resources\StreamerResource::getUrl('index') }}"><strong>People & Rates</strong><span>Streamer and fulfillment pay</span></a>
                <a class="vx-pay-link" href="{{ \App\Filament\Resources\PayoutResource::getUrl('index') }}"><strong>Payout Entries</strong><span>Detailed calculations</span></a>
            </div>
        </section>
    </div>
</x-filament-panels::page>
