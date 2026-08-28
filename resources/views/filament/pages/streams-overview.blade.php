<x-filament-panels::page>
@php $s=$this->streamSnapshot; @endphp
<style>
.vx-streams{max-width:1440px;margin:0 auto}.vx-card{border:1px solid rgb(229 231 235);background:white;border-radius:18px;box-shadow:0 1px 2px rgba(15,23,42,.03)}.dark .vx-card{border-color:#263248;background:#101827}.vx-stream-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.vx-stream-kpi{padding:18px}.vx-stream-grid{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(300px,.75fr);gap:16px}.vx-row{display:flex;align-items:center;gap:12px;padding:14px 0;border-top:1px solid rgb(243 244 246)}.dark .vx-row{border-color:#1f2937}.vx-step{position:relative;flex:1;border:1px solid rgb(229 231 235);border-radius:14px;padding:14px}.dark .vx-step{border-color:#263248}.vx-step:not(:last-child):after{content:'›';position:absolute;right:-12px;top:50%;z-index:2;transform:translateY(-50%);height:24px;width:24px;border-radius:999px;background:#7c3aed;color:white;text-align:center;line-height:22px}.vx-action{display:flex;align-items:center;gap:12px;border:1px solid rgb(229 231 235);border-radius:14px;padding:13px;transition:.15s}.dark .vx-action{border-color:#263248}.vx-action:hover{border-color:#8b5cf6;background:rgba(124,58,237,.04)}
@media(max-width:1050px){.vx-stream-kpis{grid-template-columns:repeat(3,1fr)}.vx-stream-grid{grid-template-columns:1fr}}
@media(max-width:640px){.vx-stream-kpis{grid-template-columns:1fr 1fr;gap:8px}.vx-stream-kpi{padding:12px}.vx-flow{display:grid!important;grid-template-columns:1fr 1fr;gap:8px}.vx-step:not(:last-child):after{display:none}.vx-top-actions{display:grid!important;grid-template-columns:1fr 1fr;width:100%}.vx-show-row{align-items:flex-start}.vx-show-stats{display:grid!important;grid-template-columns:1fr 1fr}.vx-card{border-radius:14px}}
</style>
<div class="vx-streams space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><h2 class="text-2xl font-bold text-gray-950 dark:text-white">Streams workspace</h2><p class="mt-1 text-sm text-gray-500">Follow each show from import through review, inventory, shipments, and payroll.</p></div>
        <div class="vx-top-actions flex flex-wrap gap-2"><a href="{{ $this->showsUrl() }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-center text-sm font-semibold dark:border-gray-700 dark:bg-gray-900">All Shows</a><a href="{{ $this->importerUrl() }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-center text-sm font-semibold dark:border-gray-700 dark:bg-gray-900">Run Import</a><a href="{{ $this->syncUrl() }}" class="rounded-xl bg-primary-600 px-4 py-2.5 text-center text-sm font-semibold text-white">Sync Dashboard</a></div>
    </div>

    <section class="vx-card p-4"><div class="mb-3 text-xs font-bold uppercase tracking-[.16em] text-primary-600">Show workflow</div><div class="vx-flow flex gap-12">
        @foreach([
            ['1','Shows','Imported or manually created'],['2','Import','Orders and show data'],['3','Review','Submission and mapping'],['4','Inventory','Sold items deducted'],['5','Payroll','Weekly pay-run contribution']
        ] as [$n,$title,$sub])<div class="vx-step"><div class="text-xs font-bold text-primary-600">STEP {{ $n }}</div><div class="mt-1 text-sm font-bold">{{ $title }}</div><div class="mt-1 text-xs text-gray-500">{{ $sub }}</div></div>@endforeach
    </div></section>

    <div class="vx-stream-kpis">
        @foreach([
            ['Shows · 30 days',$s['total30'],'Recent show volume','heroicon-o-video-camera','primary'],
            ['Upcoming',$s['upcoming'],'Scheduled ahead','heroicon-o-calendar-days','blue'],
            ['Needs Submission',$s['needsSubmission'],'Requires follow-up','heroicon-o-exclamation-triangle','amber'],
            ['Open Shipments',$s['openShipments'],'Not delivered','heroicon-o-truck','rose'],
            ['Revenue · 30 days','$'.number_format($s['revenue30'],0),'Gross show revenue','heroicon-o-banknotes','green'],
        ] as [$label,$value,$sub,$icon,$tone])
            <div class="vx-card vx-stream-kpi"><div class="flex items-center justify-between"><div class="text-xs font-medium text-gray-500">{{ $label }}</div><x-filament::icon :icon="$icon" class="h-5 w-5 text-primary-500" /></div><div class="mt-2 text-2xl font-bold">{{ $value }}</div><div class="mt-1 text-xs text-gray-400">{{ $sub }}</div></div>
        @endforeach
    </div>

    <div class="vx-stream-grid">
        <div class="space-y-4">
            <section class="vx-card p-5">
                <div class="flex items-center justify-between"><div><h3 class="font-bold">Recent Shows</h3><p class="mt-1 text-xs text-gray-500">Newest shows with the operational numbers you need.</p></div><a href="{{ $this->showsUrl() }}" class="text-xs font-semibold text-primary-600">View all</a></div>
                @forelse($this->recentShows as $show)
                    @php $log=$show->streamerLogEntry; @endphp
                    <a href="{{ $this->showUrl($show->id) }}" class="vx-row vx-show-row">
                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-950/40"><x-filament::icon icon="heroicon-o-video-camera" class="h-5 w-5" /></div>
                        <div class="min-w-0 flex-1"><div class="truncate text-sm font-bold">{{ $show->title }}</div><div class="mt-1 truncate text-xs text-gray-500">{{ $show->show_date?->format('M j, Y') ?? 'Date not set' }} · {{ $show->streamers->pluck('name')->join(', ') ?: 'Unassigned' }}</div></div>
                        <div class="vx-show-stats flex shrink-0 gap-5 text-right text-xs"><div><div class="font-bold text-gray-900 dark:text-white">${{ number_format((float)$show->gross_revenue,0) }}</div><div class="text-gray-400">Revenue</div></div><div><div class="font-bold">{{ $show->orders_count }}</div><div class="text-gray-400">Orders</div></div><div><div class="font-bold {{ $show->open_shipments_count ? 'text-amber-600' : '' }}">{{ $show->shipments_count }}</div><div class="text-gray-400">Shipments</div></div><div><div class="font-bold {{ !$log && $show->show_date?->lte(today()) ? 'text-amber-600' : 'text-emerald-600' }}">{{ !$log ? 'Pending' : ($log->status === 'admin_approved' ? 'Approved' : 'Submitted') }}</div><div class="text-gray-400">Form</div></div></div><span class="text-gray-400">›</span>
                    </a>
                @empty <div class="py-10 text-center text-sm text-gray-500">No shows available.</div> @endforelse
            </section>
        </div>

        <aside class="space-y-4">
            <section class="vx-card p-5"><div class="flex items-center justify-between"><div><h3 class="font-bold">Needs Attention</h3><p class="mt-1 text-xs text-gray-500">Past shows still waiting on a streamer submission.</p></div><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">{{ $s['needsSubmission'] }}</span></div>
                @forelse($this->attentionShows as $show)<a href="{{ $this->showUrl($show->id) }}" class="vx-row"><div class="min-w-0 flex-1"><div class="truncate text-sm font-semibold">{{ $show->title }}</div><div class="mt-1 text-xs text-gray-500">{{ $show->show_date?->format('M j') }} · {{ $show->streamers->pluck('name')->join(', ') ?: 'Unassigned' }}</div></div><span class="text-xs font-semibold text-amber-600">Submission ›</span></a>@empty<div class="py-8 text-center text-sm text-gray-500">Nothing waiting on submission.</div>@endforelse
            </section>
            <section class="vx-card p-5"><h3 class="font-bold">Stream Tools</h3><div class="mt-4 space-y-2">
                @foreach([
                    [$this->showsUrl(),'heroicon-o-video-camera','Shows','Search, filter, and manage shows'],
                    [$this->shipmentsUrl(),'heroicon-o-truck','Show Shipments','Open shipment workflow'],
                    [$this->importerUrl(),'heroicon-o-arrow-down-tray','Importer','Run or inspect the Whatnot importer'],
                    [$this->syncUrl(),'heroicon-o-arrow-path','Sync Dashboard','Sync show data from Whatnot'],
                    [$this->statusUrl(),'heroicon-o-clipboard-document-check','Show Status','Review workflow status'],
                ] as [$url,$icon,$title,$sub])<a href="{{ $url }}" class="vx-action"><div class="grid h-9 w-9 place-items-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-950/40"><x-filament::icon :icon="$icon" class="h-5 w-5" /></div><div class="min-w-0 flex-1"><div class="text-sm font-semibold">{{ $title }}</div><div class="truncate text-xs text-gray-500">{{ $sub }}</div></div><span class="text-gray-400">›</span></a>@endforeach
            </div></section>
        </aside>
    </div>
</div>
</x-filament-panels::page>
