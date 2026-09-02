<x-filament-widgets::widget>
    <style>
        .vx-ops{border:1px solid rgb(229 231 235);border-radius:1rem;background:white;overflow:hidden}.dark .vx-ops{background:rgb(17 24 39);border-color:rgb(55 65 81)}
        .vx-ops-head{padding:1rem 1.1rem;border-bottom:1px solid rgb(243 244 246);display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap}.dark .vx-ops-head{border-color:rgb(55 65 81)}
        .vx-ops-title{font-size:1rem;font-weight:800}.vx-ops-muted{font-size:.75rem;color:rgb(107 114 128);margin-top:.15rem}
        .vx-actions{display:flex;gap:.45rem;flex-wrap:wrap}.vx-action{display:inline-flex;min-height:38px;align-items:center;justify-content:center;border-radius:.65rem;padding:.5rem .7rem;font-size:.75rem;font-weight:750;border:1px solid rgb(209 213 219)}.dark .vx-action{border-color:rgb(75 85 99)}.vx-action-primary{background:rgb(37 99 235);border-color:rgb(37 99 235);color:white}
        .vx-stages{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:1px;background:rgb(229 231 235)}.dark .vx-stages{background:rgb(55 65 81)}.vx-stage{background:white;padding:.65rem .45rem;text-align:center;min-width:0}.dark .vx-stage{background:rgb(17 24 39)}.vx-stage-label{font-size:.63rem;color:rgb(107 114 128);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.vx-stage-num{font-size:1rem;font-weight:850;margin-top:.15rem}
        .vx-main{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(260px,.6fr);gap:0}.vx-list{border-right:1px solid rgb(243 244 246)}.dark .vx-list{border-color:rgb(55 65 81)}
        .vx-row{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(110px,.7fr) minmax(90px,.5fr) 20px;gap:.7rem;align-items:center;padding:.75rem 1rem;border-bottom:1px solid rgb(243 244 246)}.dark .vx-row{border-color:rgb(55 65 81)}.vx-row:hover{background:rgb(249 250 251)}.dark .vx-row:hover{background:rgb(31 41 55)}
        .vx-show-title{font-size:.8rem;font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.vx-show-meta{font-size:.68rem;color:rgb(107 114 128);margin-top:.15rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.vx-badge{display:inline-flex;max-width:100%;border-radius:999px;padding:.25rem .5rem;font-size:.65rem;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.vx-badge-success{background:rgb(236 253 245);color:rgb(4 120 87)}.vx-badge-warning{background:rgb(255 247 237);color:rgb(194 65 12)}.vx-badge-danger{background:rgb(254 242 242);color:rgb(185 28 28)}.vx-badge-info,.vx-badge-primary{background:rgb(239 246 255);color:rgb(29 78 216)}.vx-badge-purple{background:rgb(245 243 255);color:rgb(109 40 217)}.vx-badge-gray{background:rgb(243 244 246);color:rgb(75 85 99)}
        .dark .vx-badge-success{background:rgba(4,120,87,.18);color:rgb(110 231 183)}.dark .vx-badge-warning{background:rgba(194,65,12,.18);color:rgb(253 186 116)}.dark .vx-badge-danger{background:rgba(185,28,28,.18);color:rgb(252 165 165)}.dark .vx-badge-info,.dark .vx-badge-primary{background:rgba(29,78,216,.18);color:rgb(147 197 253)}.dark .vx-badge-purple{background:rgba(109,40,217,.18);color:rgb(196 181 253)}.dark .vx-badge-gray{background:rgb(31 41 55);color:rgb(209 213 219)}
        .vx-money{text-align:right;font-size:.75rem;font-weight:750}.vx-side{padding:1rem}.vx-paybox{border:1px solid rgb(229 231 235);border-radius:.8rem;padding:.85rem}.dark .vx-paybox{border-color:rgb(55 65 81)}.vx-paytotal{font-size:1.35rem;font-weight:850}.vx-side-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-top:.65rem}.vx-side-stat{background:rgb(249 250 251);border-radius:.65rem;padding:.6rem}.dark .vx-side-stat{background:rgb(31 41 55)}
        @media(max-width:900px){.vx-stages{grid-template-columns:repeat(4,1fr)}.vx-main{grid-template-columns:1fr}.vx-list{border-right:0;border-bottom:1px solid rgb(243 244 246)}.vx-row{grid-template-columns:minmax(0,1fr) auto 20px}.vx-money{display:none}}
        @media(max-width:520px){.vx-stages{grid-template-columns:repeat(2,1fr)}.vx-row{padding:.7rem}.vx-actions{display:grid;grid-template-columns:1fr 1fr;width:100%}.vx-action{width:100%}}
    </style>

    <div class="vx-ops">
        <div class="vx-ops-head">
            <div>
                <div class="vx-ops-title">Show → Fulfillment → Payroll</div>
                <div class="vx-ops-muted">One live view of where the latest shows are and what needs to happen next.</div>
            </div>
            <div class="vx-actions">
                <a class="vx-action vx-action-primary" href="{{ $showsUrl }}">All Shows</a>
                <a class="vx-action" href="{{ $logsUrl }}">Review Logs</a>
                <a class="vx-action" href="{{ $payrollUrl }}">Payroll</a>
                <a class="vx-action" href="{{ $catalogUrl }}">Inventory Catalog</a>
            </div>
        </div>

        <div class="vx-stages">
            @foreach($workflowSteps as $step)
                @php
                    $countKey = match($step['key']) {
                        'scheduled' => 'scheduled',
                        'streamer_log' => 'streamer_log',
                        'admin_review' => 'admin_review',
                        'fulfillment' => 'fulfillment',
                        'payroll_ready' => 'payroll_ready',
                        'payroll' => 'payroll',
                        'paid' => 'paid',
                    };
                @endphp
                <div class="vx-stage">
                    <div class="vx-stage-label">{{ $step['label'] }}</div>
                    <div class="vx-stage-num">{{ $counts[$countKey] ?? 0 }}</div>
                </div>
            @endforeach
        </div>

        <div class="vx-main">
            <div class="vx-list">
                @forelse($shows as $show)
                    @php $state = $show->getAttribute('workflow_state'); $pnl = $show->getAttribute('pnl_summary'); @endphp
                    <a class="vx-row" href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}">
                        <div class="min-w-0">
                            <div class="vx-show-title">{{ $show->title }}</div>
                            <div class="vx-show-meta">{{ $show->show_date?->format('M j') }} · {{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer assigned' }}@if($state['blockers']) · {{ $state['blockers'][0] }}@endif</div>
                        </div>
                        <div><span class="vx-badge vx-badge-{{ $state['tone'] }}">{{ $state['label'] }}</span></div>
                        <div class="vx-money">${{ number_format((float)($pnl['margin'] ?? 0),2) }}<div class="vx-show-meta">show net</div></div>
                        <x-heroicon-m-chevron-right class="h-4 w-4 text-gray-300" />
                    </a>
                @empty
                    <div class="p-6 text-center text-sm text-gray-500">No completed shows to display yet.</div>
                @endforelse
            </div>

            <div class="vx-side">
                <div class="vx-ops-title">Current Pay Run</div>
                @if($currentPayRun)
                    <div class="vx-paybox mt-2">
                        <div class="vx-show-meta">{{ $currentPayRun->week_start->format('M j') }} – {{ $currentPayRun->week_end->format('M j, Y') }}</div>
                        <div class="vx-paytotal mt-1">${{ number_format((float)$currentPayRun->total_payout,2) }}</div>
                        <div class="vx-show-meta">{{ $currentPayRun->payouts_count }} payout entries · {{ \App\Models\WeeklyPayoutBatch::statusLabels()[$currentPayRun->status] ?? ucfirst($currentPayRun->status) }}</div>
                        <div class="vx-side-grid">
                            <div class="vx-side-stat"><div class="vx-show-meta">Next action</div><div class="vx-show-title">{{ $currentPayRun->status === 'draft' ? 'Review & finalize' : ($currentPayRun->status === 'finalized' ? 'Submit to ADP' : ucfirst(str_replace('_',' ',$currentPayRun->status))) }}</div></div>
                            <div class="vx-side-stat"><div class="vx-show-meta">Period</div><div class="vx-show-title">Weekly</div></div>
                        </div>
                        <a href="{{ $payrollUrl }}" class="vx-action vx-action-primary mt-3" style="width:100%">Open Payroll Dashboard</a>
                    </div>
                @else
                    <div class="vx-paybox mt-2">
                        <div class="vx-show-meta">No pay run exists yet.</div>
                        <a href="{{ $payrollUrl }}" class="vx-action vx-action-primary mt-3" style="width:100%">Open Payroll Dashboard</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
