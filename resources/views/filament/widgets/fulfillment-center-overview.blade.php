<x-filament-widgets::widget>
    <style>
        .vx-fc{display:grid;gap:.9rem}.vx-fc-kpis{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:.55rem}.vx-fc-kpi{border:1px solid rgb(229 231 235);border-radius:.8rem;background:white;padding:.75rem}.dark .vx-fc-kpi,.dark .vx-fc-card{background:rgb(17 24 39);border-color:rgb(55 65 81)}.vx-fc-label{font-size:.67rem;color:rgb(107 114 128);font-weight:800;text-transform:uppercase;letter-spacing:.04em}.vx-fc-value{font-size:1.25rem;font-weight:850;margin-top:.15rem}.vx-fc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}.vx-fc-card{display:block;border:1px solid rgb(229 231 235);border-radius:.95rem;background:white;padding:.85rem;transition:.15s ease}.vx-fc-card:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(15,23,42,.07)}.vx-fc-title{font-size:.88rem;font-weight:800;color:rgb(17 24 39);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.dark .vx-fc-title{color:white}.vx-fc-meta{font-size:.7rem;color:rgb(107 114 128);margin-top:.18rem}.vx-fc-row{display:flex;align-items:center;justify-content:space-between;gap:.5rem}.vx-fc-badge{display:inline-flex;border-radius:999px;padding:.23rem .5rem;font-size:.65rem;font-weight:800}.vx-fc-badge-warning{background:rgb(255 247 237);color:rgb(194 65 12)}.vx-fc-badge-primary{background:rgb(239 246 255);color:rgb(29 78 216)}.vx-fc-badge-danger{background:rgb(254 242 242);color:rgb(185 28 28)}.vx-fc-badge-success{background:rgb(236 253 245);color:rgb(4 120 87)}.vx-fc-badge-gray{background:rgb(243 244 246);color:rgb(75 85 99)}.vx-fc-next{margin-top:.55rem;font-size:.72rem;font-weight:750;color:rgb(55 65 81)}.dark .vx-fc-next{color:rgb(209 213 219)}
        @media(max-width:1100px){.vx-fc-kpis{grid-template-columns:repeat(4,1fr)}}
        @media(max-width:700px){.vx-fc-kpis{grid-template-columns:repeat(2,1fr)}.vx-fc-grid{grid-template-columns:1fr}.vx-fc-card{padding:.8rem}}
    </style>

    <div class="vx-fc">
        <div class="vx-fc-kpis">
            @foreach([
                ['Shows in Queue',$stats['shows']],
                ['Needs Assignment',$stats['unassigned']],
                ['Review Items',$stats['review']],
                ['Item Issues',$stats['issues']],
                ['Verify Counts',$stats['verify']],
                ['Open Shipments',$stats['open_shipments']],
                ['Complete',$stats['complete']],
            ] as [$label,$value])
                <div class="vx-fc-kpi"><div class="vx-fc-label">{{ $label }}</div><div class="vx-fc-value">{{ number_format($value) }}</div></div>
            @endforeach
        </div>

        <div class="flex items-center justify-between gap-3">
            <div><div class="text-sm font-semibold text-gray-950 dark:text-white">Active Show Queue</div><div class="text-xs text-gray-500">Assignment → logged-item review → count verification when needed → payroll handoff.</div></div>
            <div class="text-xs text-gray-500">{{ number_format($stats['pending_lines']) }} logged item lines pending review</div>
        </div>

        <div class="vx-fc-grid">
            @forelse($queue as $show)
                @php $stage = $show->getAttribute('fulfillment_stage'); @endphp
                <a class="vx-fc-card" href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('view', ['record' => $show]) }}">
                    <div class="vx-fc-row">
                        <div class="min-w-0 flex-1"><div class="vx-fc-title">{{ $show->title }}</div><div class="vx-fc-meta">{{ $show->show_date?->format('M j') }} · {{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer' }}</div></div>
                        <span class="vx-fc-badge vx-fc-badge-{{ $stage['tone'] }}">{{ $stage['label'] }}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-4 gap-2 text-center">
                        <div><div class="vx-fc-label">Logged</div><div class="font-semibold">{{ $stage['logged_items'] }}</div></div>
                        <div><div class="vx-fc-label">To Review</div><div class="font-semibold">{{ $stage['pending_lines'] }}</div></div>
                        <div><div class="vx-fc-label">Issues</div><div class="font-semibold">{{ $stage['issues'] }}</div></div>
                        <div><div class="vx-fc-label">Open Shipments</div><div class="font-semibold">{{ $stage['open'] }}</div></div>
                    </div>
                    @if($stage['shipments'] > 0)
                        <div class="vx-fc-meta mt-3">Whatnot reference: {{ $stage['delivered'] }} of {{ $stage['shipments'] }} shipments delivered.</div>
                    @endif
                    <div class="vx-fc-next">Next: {{ $stage['next'] }} →</div>
                </a>
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">No fulfillment work is currently queued.</div>
            @endforelse
        </div>
    </div>
</x-filament-widgets::widget>
