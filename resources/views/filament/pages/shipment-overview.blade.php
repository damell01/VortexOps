<x-filament-panels::page>
    @php ['rows' => $rows, 'totals' => $totals] = $this->dashboardData(); @endphp
    @php $recentShows = $this->recentShows(); @endphp
    @php $selectedShipment = $this->selectedShipment(); @endphp
    <style>
        .vxs{--accent:#6d28d9;color:#1e293b}.vxs *{box-sizing:border-box}
        .vxs-panel{background:#fff;border:1px solid #e7e5ef;border-radius:16px;box-shadow:0 2px 8px rgba(31,16,55,.035)}
        .vxs-top{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:20px 22px}
        .vxs-eyebrow{font-size:11px;font-weight:800;letter-spacing:.1em;color:#7c3aed}
        .vxs-title{font-size:24px;font-weight:850;letter-spacing:-.03em;color:#201139;margin:3px 0}
        .vxs-sub{font-size:13px;color:#64748b}
        .vxs-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:10px 14px;min-height:40px;border-radius:10px;font-size:12px;font-weight:800;border:1px solid #ddd6fe;color:#5b21b6;background:#fff;text-decoration:none;cursor:pointer}
        .vxs-btn-primary{background:#6d28d9;border-color:#6d28d9;color:#fff}
        .vxs-btn:disabled{opacity:.5;cursor:wait}
        .vxs-layout{display:grid;grid-template-columns:minmax(230px,280px) minmax(0,1fr);gap:16px;align-items:start}
        .vxs-sidebar{padding:14px;position:sticky;top:16px}
        .vxs-sidebar-title{display:flex;justify-content:space-between;align-items:center;font-size:13px;font-weight:850;margin:3px 3px 12px}
        .vxs-show-list{display:grid;gap:7px;max-height:600px;overflow:auto}
        .vxs-show{display:block;width:100%;padding:11px;border:1px solid #e9e5f1;border-radius:11px;text-align:left;background:#fff;cursor:pointer}
        .vxs-show:hover{border-color:#c4b5fd;background:#faf8ff}
        .vxs-show[aria-pressed=true]{background:#f5f0ff;border-color:#8b5cf6;box-shadow:inset 3px 0 #7c3aed}
        .vxs-show strong{display:block;font-size:12px;line-height:1.35;color:#25143d;overflow-wrap:anywhere}
        .vxs-show small{display:block;font-size:11px;color:#64748b;margin-top:5px}
        .vxs-main{min-width:0;display:grid;gap:14px}
        .vxs-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:15px 18px}
        .vxs-toolbar h2{font-size:17px;font-weight:850;color:#25143d}
        .vxs-tools{display:grid;grid-template-columns:minmax(180px,1fr) 150px 120px;gap:9px;padding:0 18px 16px}
        .vxs-field{width:100%;height:40px;border:1px solid #dcd7e7;border-radius:9px;background:#fff;padding:0 11px;font-size:12px;color:#1e293b}
        .vxs-stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px}
        .vxs-stat{padding:11px 12px;border:1px solid #e7e5ef;background:#fff;border-radius:12px;text-align:left;cursor:pointer}
        .vxs-stat[aria-pressed=true]{border-color:#8b5cf6;background:#f5f0ff}
        .vxs-stat span{display:block;font-size:10px;font-weight:750;color:#64748b}
        .vxs-stat strong{display:block;font-size:21px;font-weight:850;color:#24123e;margin-top:4px;font-variant-numeric:tabular-nums}
        .vxs-list-head{padding:15px 18px;border-bottom:1px solid #eeeaf3;display:flex;justify-content:space-between;align-items:center}
        .vxs-list-head h3{font-size:14px;font-weight:850;color:#24123e}
        .vxs-table-wrap{overflow-x:auto}
        .vxs-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:12px}
        .vxs-table th{background:#faf9fc;color:#64748b;text-transform:uppercase;font-size:10px;letter-spacing:.035em;text-align:left;padding:11px 12px}
        .vxs-table td{border-top:1px solid #f1edf5;padding:12px;vertical-align:middle;overflow-wrap:anywhere}
        .vxs-table tr:hover td{background:#fcfaff}
        .vxs-buyer{font-weight:800;color:#27123f}
        .vxs-muted{color:#64748b;font-size:11px}
        .vxs-pill{display:inline-block;border-radius:99px;padding:4px 8px;background:#f3e8ff;color:#6b21a8;font-size:10px;font-weight:800}
        .vxs-detail{padding:18px;display:grid;gap:12px;position:fixed;right:16px;top:96px;bottom:16px;z-index:100;overflow:auto;width:min(510px,calc(100vw - 32px));box-shadow:0 14px 60px rgba(20,10,38,.25);align-content:start}
        .vxs-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        .vxs-detail-grid dt{font-size:11px;color:#64748b}.vxs-detail-grid dd{font-size:13px;font-weight:750;overflow-wrap:anywhere}
        .vxs-detail-actions{display:flex;gap:8px;flex-wrap:wrap}
        @media(max-width:1050px){.vxs-layout{grid-template-columns:1fr}.vxs-sidebar{position:static}.vxs-show-list{display:flex;overflow-x:auto;max-height:none}.vxs-show{min-width:200px;max-width:240px}.vxs-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(max-width:650px){.vxs-tools{grid-template-columns:1fr 1fr}.vxs-tools input{grid-column:1/-1}.vxs-stats{grid-template-columns:repeat(3,minmax(0,1fr))}.vxs-table th:nth-child(1),.vxs-table td:nth-child(1),.vxs-table th:nth-child(4),.vxs-table td:nth-child(4){display:none}.vxs-table th,.vxs-table td{padding:10px 7px}.vxs-top{padding:16px}}
        .dark .vxs-panel,.dark .vxs-show,.dark .vxs-stat{background:#1f1b2b;border-color:#42384e}.dark .vxs-title,.dark .vxs-toolbar h2,.dark .vxs-list-head h3,.dark .vxs-show strong,.dark .vxs-stat strong,.dark .vxs-buyer{color:#f8fafc}
    </style>
    <div class="vxs" wire:poll.visible.30s>
        <header class="vxs-panel vxs-top" style="margin-bottom:16px">
            <div><p class="vxs-eyebrow">FULFILLMENT CENTER / SHIPPING</p><h1 class="vxs-title">Shipment Center</h1><p class="vxs-sub">Select a show, find a buyer, and review tracking or available Whatnot labels.</p></div>
            <span class="vxs-muted">Stored shipment data refreshes every 30 seconds</span>
        </header>
        <div class="vxs-layout">
            <aside class="vxs-panel vxs-sidebar" aria-label="Recent shows">
                <div class="vxs-sidebar-title"><span>Recent shows</span><span class="vxs-muted">{{ $recentShows->count() }}</span></div>
                <div class="vxs-show-list">
                    <button type="button" class="vxs-show" wire:click="$set('showId', 'all')" aria-pressed="{{ $this->showId === 'all' ? 'true' : 'false' }}"><strong>All shipments</strong><small>Search across all shows</small></button>
                    @foreach($recentShows as $recent)
                        <button type="button" wire:key="recent-show-{{ $recent->id }}" class="vxs-show" wire:click="$set('showId', '{{ $recent->id }}')" aria-pressed="{{ $this->showId === (string) $recent->id ? 'true' : 'false' }}">
                            <strong>{{ $recent->title }}</strong><small>{{ $recent->show_date?->format('M j, Y') }} · {{ number_format($recent->shipments_count) }} shipments</small>
                        </button>
                    @endforeach
                </div>
            </aside>
            <main class="vxs-main">
                <section class="vxs-panel">
                    <div class="vxs-toolbar">
                        <div><h2>{{ $this->showId === 'all' ? 'All shipments' : ($recentShows->firstWhere('id', (int) $this->showId)?->title ?? 'Selected show') }}</h2><p class="vxs-sub">Shipment status, buyers and shipping documents</p></div>
                        @if($this->showId !== 'all')
                            <div class="vxs-detail-actions">
                                <button type="button" class="vxs-btn" wire:click="refreshSelectedShow" wire:loading.attr="disabled" wire:target="refreshSelectedShow">Refresh Whatnot</button>
                                <a class="vxs-btn vxs-btn-primary" href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('shipping', ['record' => (int) $this->showId]) }}">Shipping &amp; Labels</a>
                            </div>
                        @endif
                    </div>
                    <div class="vxs-tools">
                        <input class="vxs-field" type="search" wire:model.live.debounce.400ms="search" placeholder="Search buyer, tracking number or show…" aria-label="Search shipments">
                        <select class="vxs-field" wire:model.live="status" aria-label="Shipment status"><option value="all">All statuses</option>@foreach(\App\Filament\Pages\ShipmentOverview::BUCKETS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                        <select class="vxs-field" wire:model.live="days" aria-label="Date range"><option value="7">Last 7 days</option><option value="30">Last 30 days</option><option value="90">Last 90 days</option><option value="365">Last year</option></select>
                    </div>
                </section>
                <section class="vxs-stats" aria-label="Shipment statuses">
                    @foreach(array_diff_key(\App\Filament\Pages\ShipmentOverview::BUCKETS, ['cancelled' => true]) as $key => $label)
                        <button type="button" class="vxs-stat" wire:click="$set('status', '{{ $this->status === $key ? 'all' : $key }}')" aria-pressed="{{ $this->status === $key ? 'true' : 'false' }}"><span>{{ $label }}</span><strong>{{ number_format($totals[$key] ?? 0) }}</strong></button>
                    @endforeach
                </section>
                <section class="vxs-panel">
                    <div class="vxs-list-head"><h3>Shipment list</h3><span class="vxs-muted">{{ $rows->count() }} shown · select a shipment for details</span></div>
                    <div class="vxs-table-wrap"><table class="vxs-table">
                        <thead><tr><th style="width:27%">Show</th><th style="width:18%">Buyer</th><th style="width:25%">Tracking</th><th style="width:16%">Status</th><th style="width:14%">Details</th></tr></thead>
                        <tbody>
                        @forelse($rows as $shipment)
                            <tr wire:key="shipment-{{ $shipment->id }}">
                                <td><strong>{{ \Illuminate\Support\Str::limit($shipment->show?->title ?? 'Unknown show', 36) }}</strong><div class="vxs-muted">{{ $shipment->show?->show_date?->format('M j, Y') }}</div></td>
                                <td><span class="vxs-buyer">{{ $shipment->buyer_username ?: 'Unknown' }}</span><div class="vxs-muted">{{ $shipment->carrier ?: 'Carrier pending' }}</div></td>
                                <td><span style="font-family:monospace;font-size:11px;overflow-wrap:anywhere">{{ $shipment->tracking_number ?: 'Awaiting tracking' }}</span></td>
                                <td><span class="vxs-pill">{{ \App\Filament\Pages\ShipmentOverview::BUCKETS[\App\Filament\Pages\ShipmentOverview::bucket($shipment->status)] }}</span></td>
                                <td><button type="button" class="vxs-btn" wire:click="selectShipment({{ $shipment->id }})">View</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="padding:28px;text-align:center;color:#64748b">No shipments found. Try another show, status, or date range.</td></tr>
                        @endforelse
                        </tbody>
                    </table></div>
                    @if($rows->count() === 150)<p class="vxs-muted" style="padding:12px 16px">Showing 150 recent matches. Narrow your search to find older shipments.</p>@endif
                </section>
                @if($selectedShipment)
                    <section class="vxs-panel vxs-detail" role="dialog" aria-modal="false" aria-label="Selected shipment details">
                        <div class="vxs-toolbar" style="padding:0"><div><h2>Shipment details</h2><p class="vxs-sub">{{ $selectedShipment->buyer_username ?: 'Unknown buyer' }} · {{ $selectedShipment->show?->title }}</p></div><button type="button" class="vxs-btn" wire:click="$set('selectedShipmentId', null)">Close</button></div>
                        <dl class="vxs-detail-grid">
                            <div><dt>Tracking number</dt><dd>{{ $selectedShipment->tracking_number ?: 'Not available' }}</dd></div>
                            <div><dt>Carrier</dt><dd>{{ $selectedShipment->carrier ?: 'Not available' }}</dd></div>
                            <div><dt>Shipment status</dt><dd>{{ \App\Filament\Pages\ShipmentOverview::BUCKETS[\App\Filament\Pages\ShipmentOverview::bucket($selectedShipment->status)] }}</dd></div>
                            <div><dt>Items</dt><dd>{{ $selectedShipment->item_count ?? 'Unknown' }}</dd></div>
                        </dl>
                        @php
                            $labelPayload = is_array($selectedShipment->raw_payload) ? $selectedShipment->raw_payload : [];
                            $labelDocs = is_array($labelPayload['documents'] ?? null) ? $labelPayload['documents'] : [];
                            $labelAvailable = (bool) \App\Filament\Resources\FulfillmentResource\Pages\ShippingDesk::safeDocumentUrl($labelDocs['label_url'] ?? null);
                            $labelPreviewUrl = $labelAvailable ? route('fulfillment.shipment-label', ['shipment' => $selectedShipment->id]) : null;
                        @endphp
                        <div class="vxs-panel" style="padding:14px;background:#faf8ff" x-data="{ verified: false, open: false, url: @js($labelPreviewUrl) }" wire:key="shipment-label-{{ $selectedShipment->id }}">
                            <h3 style="font-weight:850;color:#27123f">Verify buyer &amp; label</h3>
                            <p class="vxs-muted" style="margin:6px 0 12px">Confirm the buyer and tracking number match the package before printing.</p>
                            <label style="display:flex;gap:9px;align-items:center;font-size:13px;font-weight:700"><input type="checkbox" x-model="verified" style="width:17px;height:17px"> Buyer {{ $selectedShipment->buyer_username ?: 'Unknown' }} and tracking verified</label>
                            <div class="vxs-detail-actions" style="margin-top:12px">
                                @if($labelAvailable)
                                    <button type="button" class="vxs-btn vxs-btn-primary" :disabled="!verified" @click="open=true">Preview &amp; print label</button>
                                @else
                                    <span class="vxs-muted">No printable Whatnot label is available for this shipment. Refresh from Whatnot or check Shipping &amp; Labels.</span>
                                @endif
                                <a class="vxs-btn" href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('shipping', ['record' => $selectedShipment->show_id]) }}">Show shipping workspace</a>
                            </div>
                            <template x-if="open && url">
                                <div role="dialog" aria-modal="true" aria-label="Shipping label preview" @keydown.escape.window="open=false" style="position:fixed;inset:0;z-index:99999;background:rgba(17,12,35,.75);display:flex;align-items:center;justify-content:center;padding:16px" @click.self="open=false">
                                    <div style="background:#fff;border-radius:15px;width:min(900px,100%);height:min(92vh,900px);display:flex;flex-direction:column;overflow:hidden">
                                        <div style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center;gap:12px">
                                            <strong>Label · {{ $selectedShipment->buyer_username ?: 'Unknown buyer' }}</strong>
                                            <div class="vxs-detail-actions"><a class="vxs-btn" :href="url" target="_blank" rel="noopener noreferrer">Open / print in browser</a><button type="button" class="vxs-btn" @click="open=false">Close</button></div>
                                        </div>
                                        <iframe :src="url" title="Whatnot shipping label PDF or image" style="width:100%;flex:1;border:0;background:#f8fafc"></iframe>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <p class="vxs-muted">Labels are previewed and printed using the existing authorized Shipping &amp; Labels and packing station workflows. If Whatnot has not supplied a label, no preview is available.</p>
                    </section>
                @endif
            </main>
        </div>
    </div>
</x-filament-panels::page>
