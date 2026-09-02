<x-filament-panels::page>
    @php
        $current = $this->currentPayRun();
        $attention = $this->needsAttention();
        $breakdown = $this->currentBreakdown();
        $peopleRows = $this->currentPeopleRows();
        $showRows = $this->currentShowRows();
        $recent = $this->recentPayRuns();
    @endphp

    <style>
        .vx-pay { display:grid; gap:.85rem; }
        .vx-card { border:1px solid rgb(229 231 235); border-radius:1rem; background:#fff; overflow:hidden; }
        .dark .vx-card { background:rgb(17 24 39); border-color:rgb(55 65 81); }
        .vx-pad { padding:1rem; }
        .vx-eyebrow { font-size:.7rem; font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:rgb(37 99 235); }
        .vx-title { margin-top:.2rem; font-size:1.3rem; font-weight:800; color:rgb(17 24 39); }
        .dark .vx-title { color:#fff; }
        .vx-muted { color:rgb(107 114 128); font-size:.78rem; }
        .vx-kpis { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); border-top:1px solid rgb(243 244 246); margin-top:1rem; }
        .dark .vx-kpis { border-color:rgb(31 41 55); }
        .vx-kpi { padding:.75rem .9rem; border-right:1px solid rgb(243 244 246); }
        .vx-kpi:last-child { border-right:0; }
        .dark .vx-kpi { border-color:rgb(31 41 55); }
        .vx-label { color:rgb(107 114 128); font-size:.68rem; font-weight:750; text-transform:uppercase; letter-spacing:.04em; }
        .vx-value { margin-top:.15rem; font-size:1.15rem; font-weight:800; color:rgb(17 24 39); }
        .dark .vx-value { color:#fff; }
        .vx-actions { display:flex; flex-wrap:wrap; gap:.5rem; margin-top:.85rem; }
        .vx-btn { min-height:42px; display:inline-flex; align-items:center; justify-content:center; border-radius:.7rem; padding:.6rem .8rem; font-size:.78rem; font-weight:800; }
        .vx-primary { background:rgb(37 99 235); color:white; }
        .vx-secondary { border:1px solid rgb(209 213 219); color:rgb(55 65 81); background:rgb(249 250 251); }
        .dark .vx-secondary { background:rgb(31 41 55); border-color:rgb(75 85 99); color:rgb(229 231 235); }
        .vx-grid { display:grid; grid-template-columns:minmax(0,1.3fr) minmax(0,.7fr); gap:.85rem; }
        .vx-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; padding:.9rem 1rem; border-bottom:1px solid rgb(243 244 246); }
        .dark .vx-head { border-color:rgb(31 41 55); }
        .vx-head h2 { font-size:.95rem; font-weight:800; color:rgb(17 24 39); }
        .dark .vx-head h2 { color:#fff; }
        .vx-warning { display:flex; gap:.55rem; padding:.7rem .8rem; margin:.55rem .8rem; border-radius:.7rem; background:rgb(255 247 237); color:rgb(154 52 18); font-size:.76rem; }
        .dark .vx-warning { background:rgba(154,52,18,.16); color:rgb(253 186 116); }
        .vx-ok { margin:.75rem; padding:.7rem .8rem; border-radius:.7rem; background:rgb(236 253 245); color:rgb(4 120 87); font-size:.76rem; font-weight:700; }
        .vx-table-wrap { overflow:auto; }
        .vx-table { width:100%; border-collapse:collapse; min-width:720px; }
        .vx-table th { padding:.65rem .75rem; text-align:left; color:rgb(107 114 128); font-size:.66rem; text-transform:uppercase; letter-spacing:.04em; font-weight:800; background:rgb(249 250 251); }
        .dark .vx-table th { background:rgb(31 41 55); }
        .vx-table td { padding:.72rem .75rem; border-top:1px solid rgb(243 244 246); font-size:.76rem; color:rgb(55 65 81); white-space:nowrap; }
        .dark .vx-table td { border-color:rgb(31 41 55); color:rgb(209 213 219); }
        .vx-table td strong { color:rgb(17 24 39); }
        .dark .vx-table td strong { color:#fff; }
        .vx-status { display:inline-flex; border-radius:999px; padding:.22rem .5rem; font-size:.65rem; font-weight:800; background:rgb(243 244 246); color:rgb(75 85 99); }
        .dark .vx-status { background:rgb(31 41 55); color:rgb(209 213 219); }
        .vx-recent { display:grid; gap:.45rem; padding:.75rem; }
        .vx-row { display:flex; align-items:center; justify-content:space-between; gap:.75rem; border:1px solid rgb(229 231 235); border-radius:.7rem; padding:.65rem .75rem; }
        .dark .vx-row { border-color:rgb(55 65 81); }
        @media(max-width:900px){ .vx-kpis{grid-template-columns:repeat(3,1fr)} .vx-grid{grid-template-columns:1fr} }
        @media(max-width:600px){ .vx-pad{padding:.85rem}.vx-kpis{grid-template-columns:repeat(2,1fr)}.vx-actions{display:grid;grid-template-columns:1fr 1fr}.vx-btn{width:100%}.vx-title{font-size:1.15rem} }
    </style>

    <div class="vx-pay">
        <section class="vx-card">
            <div class="vx-pad">
                <div class="vx-eyebrow">Weekly payroll dashboard</div>
                @if($current)
                    <div class="vx-title">{{ $current->week_start->format('M j') }} – {{ $current->week_end->format('M j, Y') }}</div>
                    <div class="vx-muted">{{ \App\Models\WeeklyPayoutBatch::statusLabels()[$current->status] ?? ucfirst($current->status) }} · every show and team calculation for this pay period in one place.</div>
                    <div class="vx-actions">
                        <a class="vx-btn vx-primary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view', ['record' => $current]) }}">Review / Finalize Run</a>
                        <a class="vx-btn vx-secondary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index') }}">Pay Run History</a>
                        <a class="vx-btn vx-secondary" href="{{ \App\Filament\Pages\PaymentStructures::getUrl() }}">Compensation Settings</a>
                        <a class="vx-btn vx-secondary" href="{{ \App\Filament\Pages\Shows::getUrl() }}">Open Shows</a>
                    </div>
                @else
                    <div class="vx-title">No current pay run</div>
                    <div class="vx-muted">Create the weekly run when the first eligible show is ready.</div>
                    <div class="vx-actions"><a class="vx-btn vx-primary" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('create') }}">Create Pay Run</a></div>
                @endif
            </div>

            <div class="vx-kpis">
                <div class="vx-kpi"><div class="vx-label">Total Payroll</div><div class="vx-value">${{ number_format((float)($current?->total_payout ?? 0), 2) }}</div></div>
                <div class="vx-kpi"><div class="vx-label">Shows</div><div class="vx-value">{{ $breakdown['shows'] }}</div></div>
                <div class="vx-kpi"><div class="vx-label">People</div><div class="vx-value">{{ $breakdown['people'] }}</div></div>
                <div class="vx-kpi"><div class="vx-label">Streamer Pay</div><div class="vx-value">${{ number_format($breakdown['streamer_total'], 2) }}</div></div>
                <div class="vx-kpi"><div class="vx-label">Fulfillment Pay</div><div class="vx-value">${{ number_format($breakdown['fulfillment_total'], 2) }}</div></div>
                <div class="vx-kpi"><div class="vx-label">Needs Review</div><div class="vx-value">{{ count($attention) }}</div></div>
            </div>
        </section>

        <div class="vx-grid">
            <section class="vx-card">
                <div class="vx-head"><div><h2>Needs Attention</h2><div class="vx-muted">Fix these before finalizing the week.</div></div></div>
                @forelse($attention as $warning)
                    <div class="vx-warning"><strong>!</strong><span>{{ $warning }}</span></div>
                @empty
                    <div class="vx-ok">✓ No payroll readiness issues found.</div>
                @endforelse
            </section>

            <section class="vx-card">
                <div class="vx-head"><div><h2>Pay Mix</h2><div class="vx-muted">Who the weekly total is going to.</div></div></div>
                <div class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800">
                    <div class="bg-white p-4 dark:bg-gray-900"><div class="vx-label">Streamers</div><div class="vx-value">${{ number_format($breakdown['streamer_total'],2) }}</div><div class="vx-muted">{{ $breakdown['streamers'] }} people</div></div>
                    <div class="bg-white p-4 dark:bg-gray-900"><div class="vx-label">Fulfillment</div><div class="vx-value">${{ number_format($breakdown['fulfillment_total'],2) }}</div><div class="vx-muted">{{ $breakdown['fulfillment'] }} people</div></div>
                </div>
            </section>
        </div>

        <section class="vx-card">
            <div class="vx-head">
                <div><h2>Team Calculations</h2><div class="vx-muted">Each person's total from every show/activity included in the weekly run.</div></div>
                <a href="{{ \App\Filament\Resources\PayoutResource::getUrl('index') }}" class="text-xs font-semibold text-primary-600">All payout entries →</a>
            </div>
            <div class="vx-table-wrap">
                <table class="vx-table">
                    <thead><tr><th>Person</th><th>Role</th><th>Shows</th><th>Entries</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($peopleRows as $row)
                        <tr>
                            <td><strong>{{ $row['member']?->name ?? $row['member']?->display_name ?? 'Unknown' }}</strong></td>
                            <td>{{ $row['role'] }}</td>
                            <td>{{ $row['shows'] }}</td>
                            <td>{{ $row['entries'] }}</td>
                            <td><strong>${{ number_format($row['total'], 2) }}</strong></td>
                            <td><span class="vx-status">{{ $row['status'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="vx-muted">No payout calculations are in this run yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="vx-card">
            <div class="vx-head">
                <div><h2>Shows Feeding This Pay Run</h2><div class="vx-muted">Sales → COGS → streamer/fulfillment pay → show net. These rows roll up into the weekly total above.</div></div>
            </div>
            <div class="vx-table-wrap">
                <table class="vx-table">
                    <thead><tr><th>Show</th><th>Sales</th><th>COGS</th><th>Gross Profit</th><th>Streamer</th><th>Fulfillment</th><th>Total Payroll</th><th>Show Net</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($showRows as $row)
                        <tr>
                            <td>
                                @if($row['show'])
                                    <a class="font-semibold text-primary-600" href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $row['show']]) }}">{{ $row['show']->title }}</a>
                                    <div class="vx-muted">{{ $row['show']->show_date?->format('M j, Y') }}</div>
                                @else
                                    <strong>Unknown show</strong>
                                @endif
                            </td>
                            <td>${{ number_format($row['sales'],2) }}</td>
                            <td>${{ number_format($row['cogs'],2) }}</td>
                            <td>${{ number_format($row['gross_profit'],2) }}</td>
                            <td>${{ number_format($row['streamer_pay'],2) }}</td>
                            <td>${{ number_format($row['fulfillment_pay'],2) }}</td>
                            <td><strong>${{ number_format($row['payroll'],2) }}</strong></td>
                            <td><strong>${{ number_format($row['net'],2) }}</strong></td>
                            <td><span class="vx-status">{{ $row['status'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="vx-muted">No show-linked payout calculations are in this run yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="vx-card">
            <div class="vx-head"><div><h2>Recent Pay Runs</h2><div class="vx-muted">Open any week for its detailed calculations and payment actions.</div></div></div>
            <div class="vx-recent">
                @forelse($recent as $run)
                    <a class="vx-row" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view', ['record' => $run]) }}">
                        <div><strong>{{ $run->week_start->format('M j') }} – {{ $run->week_end->format('M j, Y') }}</strong><div class="vx-muted">{{ $run->payouts_count }} entries · ${{ number_format((float)$run->total_payout,2) }}</div></div>
                        <div class="text-right"><span class="vx-status">{{ \App\Models\WeeklyPayoutBatch::statusLabels()[$run->status] ?? ucfirst($run->status) }}</span><div class="vx-muted mt-1">View →</div></div>
                    </a>
                @empty
                    <div class="vx-muted p-2">No pay runs yet.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
