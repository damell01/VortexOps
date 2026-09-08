<x-filament-widgets::widget>
    <style>
        .vx-fc{display:grid;gap:.85rem}.vx-fc-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.55rem}.vx-fc-kpi{border:1px solid rgb(229 231 235);border-radius:.85rem;background:white;padding:.75rem}.dark .vx-fc-kpi,.dark .vx-fc-card,.dark .vx-fc-focus{background:rgb(17 24 39);border-color:rgb(55 65 81)}.vx-fc-label{font-size:.62rem;color:rgb(107 114 128);font-weight:800;text-transform:uppercase;letter-spacing:.04em}.vx-fc-value{font-size:1.2rem;font-weight:850;margin-top:.1rem}.vx-fc-focus{border:1px solid rgb(229 231 235);border-radius:1rem;background:white;padding:.9rem}.vx-fc-focus-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.6rem;margin-top:.7rem}.vx-fc-focus-item{display:block;border:1px solid rgb(229 231 235);border-radius:.85rem;padding:.75rem;background:rgb(249 250 251)}.dark .vx-fc-focus-item{background:rgb(31 41 55);border-color:rgb(55 65 81)}.vx-fc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}.vx-fc-card{display:block;border:1px solid rgb(229 231 235);border-radius:.95rem;background:white;padding:.82rem;transition:.15s ease}.vx-fc-card:hover,.vx-fc-focus-item:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(15,23,42,.07)}.vx-fc-title{font-size:.86rem;font-weight:800;color:rgb(17 24 39);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.dark .vx-fc-title{color:white}.vx-fc-meta{font-size:.68rem;color:rgb(107 114 128);margin-top:.15rem}.vx-fc-row{display:flex;align-items:flex-start;justify-content:space-between;gap:.55rem}.vx-fc-badge{display:inline-flex;border-radius:999px;padding:.22rem .48rem;font-size:.61rem;font-weight:800;white-space:nowrap}.vx-fc-badge-warning{background:rgb(255 247 237);color:rgb(194 65 12)}.vx-fc-badge-primary{background:rgb(239 246 255);color:rgb(29 78 216)}.vx-fc-badge-danger{background:rgb(254 242 242);color:rgb(185 28 28)}.vx-fc-badge-success{background:rgb(236 253 245);color:rgb(4 120 87)}.vx-fc-badge-gray{background:rgb(243 244 246);color:rgb(75 85 99)}.vx-fc-next{margin-top:.55rem;font-size:.7rem;font-weight:800;color:rgb(55 65 81)}.dark .vx-fc-next{color:rgb(209 213 219)}.vx-fc-mini{display:grid;grid-template-columns:repeat(4,1fr);gap:.45rem;margin-top:.65rem}.vx-fc-mini div{border-radius:.65rem;background:rgb(249 250 251);padding:.5rem;text-align:center}.dark .vx-fc-mini div{background:rgb(31 41 55)}
        @media(max-width:1100px){.vx-fc-kpis{grid-template-columns:repeat(2,1fr)}.vx-fc-focus-grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:760px){.vx-fc-kpis{grid-template-columns:repeat(2,minmax(0,1fr));gap:.45rem}.vx-fc-kpi{padding:.65rem}.vx-fc-value{font-size:1.05rem}.vx-fc-grid,.vx-fc-focus-grid{grid-template-columns:1fr}.vx-fc-card,.vx-fc-focus-item{padding:.72rem}.vx-fc-mini{grid-template-columns:repeat(2,1fr)}.vx-fc-row{align-items:flex-start}.vx-fc-title{white-space:normal}.vx-fc-focus>.flex,.vx-fc>.flex{align-items:flex-start;flex-direction:column}.vx-fc-focus>.flex>div:last-child,.vx-fc>.flex>div:last-child{text-align:left}}
    </style>

    <div class="vx-fc">
        <div class="vx-fc-kpis">
            @foreach([
                ['Active Work',$stats['active']],
                ['Needs Assignment',$stats['unassigned']],
                ['Item Issues',$stats['issues']],
                ['Open Shipments',$stats['open_shipments']],
            ] as [$label,$value])
                <div class="vx-fc-kpi"><div class="vx-fc-label">{{ $label }}</div><div class="vx-fc-value">{{ number_format($value) }}</div></div>
            @endforeach
        </div>

        @if($focusQueue->isNotEmpty())
            <div class="vx-fc-focus">
                <div class="flex items-center justify-between gap-3"><div><div class="text-sm font-semibold text-gray-950 dark:text-white">Do These First</div><div class="text-xs text-gray-500">Issues, unassigned shows and pending reviews are prioritized automatically.</div></div><div class="text-xs text-gray-500">{{ number_format($stats['pending_lines']) }} item lines pending</div></div>
                <div class="vx-fc-focus-grid">
                    @foreach($focusQueue as $show)
                        @php $stage = $show->getAttribute('fulfillment_stage'); @endphp
                        <a class="vx-fc-focus-item" href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('view', ['record' => $show]) }}">
                            <div class="vx-fc-row"><div class="min-w-0 flex-1"><div class="vx-fc-title">{{ $show->title }}</div><div class="vx-fc-meta">{{ $show->show_date?->format('M j') }} · {{ $show->fulfillmentUsers->pluck('name')->join(', ') ?: 'Unassigned' }}</div></div><span class="vx-fc-badge vx-fc-badge-{{ $stage['tone'] }}">{{ $stage['label'] }}</span></div>
                            <div class="vx-fc-next">{{ $stage['next'] }} →</div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex items-center justify-between gap-3"><div><div class="text-sm font-semibold text-gray-950 dark:text-white">All Active Fulfillment</div><div class="text-xs text-gray-500">Each show is a card — no wide table or horizontal scrolling.</div></div><div class="text-xs text-gray-500">{{ number_format($stats['review']) }} review · {{ number_format($stats['verify']) }} verify</div></div>

        <div class="vx-fc-grid">
            @forelse($queue as $show)
                @php $stage = $show->getAttribute('fulfillment_stage'); @endphp
                <a class="vx-fc-card" href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('view', ['record' => $show]) }}">
                    <div class="vx-fc-row"><div class="min-w-0 flex-1"><div class="vx-fc-title">{{ $show->title }}</div><div class="vx-fc-meta">{{ $show->show_date?->format('M j') }} · {{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer' }}</div><div class="vx-fc-meta">Assigned: {{ $show->fulfillmentUsers->pluck('name')->join(', ') ?: 'Unassigned' }}</div></div><span class="vx-fc-badge vx-fc-badge-{{ $stage['tone'] }}">{{ $stage['label'] }}</span></div>
                    <div class="vx-fc-mini"><div><div class="vx-fc-label">Logged</div><div class="font-semibold">{{ $stage['logged_items'] }}</div></div><div><div class="vx-fc-label">Review</div><div class="font-semibold">{{ $stage['pending_lines'] }}</div></div><div><div class="vx-fc-label">Issues</div><div class="font-semibold">{{ $stage['issues'] }}</div></div><div><div class="vx-fc-label">Shipments</div><div class="font-semibold">{{ $stage['open'] }}</div></div></div>
                    <div class="vx-fc-next">Next: {{ $stage['next'] }} →</div>
                </a>
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">No fulfillment work is currently queued.</div>
            @endforelse
        </div>
    </div>
</x-filament-widgets::widget>