<x-filament-panels::page>
    @php
        $record = $this->record;
        $phases = App\Models\Pallet::statusPhases();
        $currentPhase = $phases[$record->status]['number'] ?? 0;
        $lines = $record->lines;
        $casesExpected = $lines->sum(fn ($line) => (int) $line->case_count);
        $casesReceived = $lines->sum(fn ($line) => $line->cases->where('status', '!=', 'expected')->count());
        $progressPct = $casesExpected > 0 ? min(100, round(($casesReceived / $casesExpected) * 100)) : 0;
        $goods = $lines->sum(fn ($line) => (float) $line->unit_cost * (float) $line->quantity_per_case * (int) $line->case_count);
        $units = $lines->sum(fn ($line) => (float) $line->quantity_per_case * (int) $line->case_count);
        $extras = $record->landedCostExtras();
        $landed = $goods + $extras;
        $statusLabel = \App\Models\Pallet::statusLabels()[$record->status] ?? ucfirst($record->status);
        $shipTo = $lines->first()?->location?->name ?? '—';
        $receiveUrl = \App\Filament\Resources\PalletResource::getUrl('receive', ['record' => $record]);
        $itemsUrl = \App\Filament\Resources\PalletResource::getUrl('items', ['record' => $record]);
        $addLinesUrl = \App\Filament\Resources\PalletResource::getUrl('add-lines', ['record' => $record]);
        $editUrl = \App\Filament\Resources\PalletResource::getUrl('edit', ['record' => $record]);
    @endphp

    <style>
        body:has(.vx-pallet-redesign) .fi-page-header .fi-header-actions,
        body:has(.vx-pallet-redesign) .fi-page-header .fi-header-actions-ctn { display:none !important; }
        body:has(.vx-pallet-redesign) .fi-page-header-heading { display:none !important; }
        body:has(.vx-pallet-redesign) .fi-page-content { padding-top:.75rem !important; }
        .vx-pallet-redesign{max-width:1480px;margin:0 auto;padding:0 0 36px;color:#0f172a}.dark .vx-pallet-redesign{color:#f8fafc}
        .vx-pallet-redesign *{box-sizing:border-box}.vx-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 5px 18px rgba(15,23,42,.05)}.dark .vx-card{background:#111827;border-color:#263244;box-shadow:none}
        .vx-title-row{display:flex;align-items:center;justify-content:space-between;gap:20px;margin:4px 0 20px}.vx-title-wrap{min-width:0}.vx-title{margin:0;font-size:30px;line-height:1.12;font-weight:800;letter-spacing:-.035em;color:#0f172a}.dark .vx-title{color:#fff}.vx-meta-pills{display:flex;gap:8px;flex-wrap:wrap;margin-top:9px}.vx-pill{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:700}.vx-pill-blue{background:#e8f0ff;color:#2866dd}.vx-pill-gray{background:#f1f5f9;color:#475569}.dark .vx-pill-gray{background:#1f2937;color:#cbd5e1}
        .vx-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:wrap}.vx-action{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 16px;border-radius:10px;border:1px solid transparent;font-size:14px;font-weight:800;text-decoration:none;white-space:nowrap;cursor:pointer}.vx-action svg{width:18px;height:18px}.vx-action-green{background:#06c98d;color:#fff;box-shadow:0 5px 12px rgba(6,201,141,.18)}.vx-action-blue{background:#5790ed;color:#fff;box-shadow:0 5px 12px rgba(87,144,237,.18)}.vx-action-neutral{background:#fff;color:#1e293b;border-color:#dbe2ea}.dark .vx-action-neutral{background:#182235;color:#f8fafc;border-color:#334155}.vx-more{position:relative}.vx-more-menu{position:absolute;right:0;top:calc(100% + 8px);z-index:50;width:230px;padding:7px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;box-shadow:0 18px 45px rgba(15,23,42,.18)}.dark .vx-more-menu{background:#111827;border-color:#334155}.vx-more-link{display:flex;width:100%;align-items:center;gap:9px;border-radius:8px;padding:10px 11px;color:#334155;text-decoration:none;font-size:13px;font-weight:700;background:transparent;border:0;cursor:pointer;text-align:left}.vx-more-link:hover{background:#f8fafc}.dark .vx-more-link{color:#e2e8f0}.dark .vx-more-link:hover{background:#1f2937}
        .vx-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));padding:20px 22px;margin-bottom:20px}.vx-summary-cell{display:flex;gap:12px;align-items:center;min-width:0;padding:2px 18px;border-right:1px solid #e8edf3}.vx-summary-cell:first-child{padding-left:0}.vx-summary-cell:last-child{border-right:0;padding-right:0}.dark .vx-summary-cell{border-color:#263244}.vx-summary-icon{display:flex;align-items:center;justify-content:center;width:40px;height:40px;flex:0 0 40px;border-radius:12px;background:#eef4ff;color:#3b73df}.vx-summary-label{font-size:11px;color:#64748b;margin-bottom:3px}.vx-summary-value{font-size:14px;font-weight:750;color:#172033;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.dark .vx-summary-value{color:#f8fafc}.vx-summary-sub{font-size:11px;color:#94a3b8;margin-top:2px}
        .vx-middle{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,.95fr);gap:18px;margin-bottom:18px}.vx-status-card{padding:22px}.vx-card-heading{font-size:14px;font-weight:800;color:#0f172a;margin:0}.dark .vx-card-heading{color:#fff}.vx-timeline{display:grid;grid-template-columns:repeat(4,1fr);margin-top:30px;position:relative}.vx-step{text-align:center;position:relative}.vx-step:before{content:'';position:absolute;top:12px;left:-50%;right:50%;height:2px;background:#dbe2ea;z-index:0}.vx-step:first-child:before{display:none}.vx-step.done:before,.vx-step.active:before{background:#63c9a5}.vx-step-dot{position:relative;z-index:1;display:flex;align-items:center;justify-content:center;width:26px;height:26px;margin:0 auto 10px;border-radius:999px;border:2px solid #d4dbe5;background:#fff;color:#94a3b8}.dark .vx-step-dot{background:#111827}.vx-step.done .vx-step-dot{border-color:#20bf83;color:#20bf83}.vx-step.active .vx-step-dot{border-color:#4f8ff0;color:#4f8ff0;box-shadow:0 0 0 4px #eaf2ff}.vx-step-label{font-size:12px;color:#475569}.vx-step.done .vx-step-label{color:#08a66f;font-weight:700}.vx-step.active .vx-step-label{color:#2866dd;font-weight:700}.dark .vx-step-label{color:#cbd5e1}
        .vx-progress-card{padding:22px}.vx-progress-body{display:flex;align-items:center;gap:22px;margin-top:22px}.vx-ring{--pct:0;display:grid;place-items:center;width:104px;height:104px;flex:0 0 104px;border-radius:50%;background:conic-gradient(#5b8ff1 calc(var(--pct)*1%),#edf1f6 0);position:relative}.vx-ring:after{content:'';position:absolute;inset:11px;border-radius:50%;background:#fff}.dark .vx-ring:after{background:#111827}.vx-ring strong{position:relative;z-index:1;font-size:22px}.vx-progress-stats{display:grid;gap:10px;font-size:13px;color:#475569}.dark .vx-progress-stats{color:#cbd5e1}.vx-progress-link{color:#2563eb;font-weight:700;text-decoration:none;margin-top:2px;display:inline-block}
        .vx-detail-card{padding:20px 22px;margin-bottom:18px}.vx-details{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px 50px}.vx-detail-row{display:grid;grid-template-columns:135px 1fr;gap:16px;font-size:13px}.vx-detail-label{color:#64748b}.vx-detail-value{color:#1e293b;font-weight:600}.dark .vx-detail-value{color:#e2e8f0}
        .vx-lower{display:grid;grid-template-columns:minmax(0,3fr) minmax(290px,.9fr);gap:18px;margin-bottom:18px}.vx-table-card{overflow:hidden}.vx-section-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:17px 19px;border-bottom:1px solid #edf1f5}.dark .vx-section-head{border-color:#263244}.vx-section-note{font-size:11px;color:#94a3b8}.vx-table-wrap{overflow-x:auto}.vx-table{width:100%;border-collapse:collapse;font-size:13px}.vx-table th{padding:11px 18px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:700;border-bottom:1px solid #edf1f5}.vx-table td{padding:14px 18px;border-bottom:1px solid #f0f3f7;color:#334155}.dark .vx-table td{color:#dbe4f0;border-color:#222e40}.vx-table tr:last-child td{border-bottom:0}.vx-item{display:flex;align-items:center;gap:11px;min-width:220px}.vx-thumb{width:42px;height:42px;flex:0 0 42px;border-radius:9px;overflow:hidden;border:1px solid #e2e8f0;background:#f8fafc;display:grid;place-items:center}.vx-thumb img{width:100%;height:100%;object-fit:cover}.vx-item-name{font-size:13px;font-weight:750;color:#172033}.dark .vx-item-name{color:#fff}.vx-item-sub{font-size:10px;color:#94a3b8;margin-top:3px}.vx-status{display:inline-flex;border-radius:999px;padding:4px 8px;font-size:10px;font-weight:700}.vx-status-gray{background:#f1f5f9;color:#64748b}.vx-status-blue{background:#e8f0ff;color:#2866dd}.vx-status-green{background:#e7f8f1;color:#0b9a6a}.vx-status-amber{background:#fff5dd;color:#b7791f}.vx-count-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 10px;border-radius:8px;border:1px solid #8eb6fa;background:#fff;color:#2866dd;font-size:11px;font-weight:700;cursor:pointer}.dark .vx-count-btn{background:#111827}.vx-cost{padding:18px 20px}.vx-cost-lines{margin-top:18px;display:grid;gap:12px}.vx-cost-row{display:flex;justify-content:space-between;gap:20px;font-size:13px;color:#475569}.dark .vx-cost-row{color:#cbd5e1}.vx-cost-total{padding-top:13px;margin-top:2px;border-top:1px solid #e2e8f0;font-weight:800;color:#0f172a}.dark .vx-cost-total{color:#fff;border-color:#334155}
        .vx-media{padding:18px 20px}.vx-upload{display:flex;align-items:center;justify-content:center;gap:12px;min-height:82px;margin-top:14px;border:1px dashed #cbd5e1;border-radius:11px;background:#fbfcfe;color:#64748b;text-align:center}.dark .vx-upload{background:#0f172a;border-color:#334155}.vx-upload button{border:0;background:transparent;color:#2563eb;font:inherit;font-weight:700;cursor:pointer}.vx-attachment-list{display:grid;gap:7px;margin-top:12px}.vx-attachment{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 11px;border-radius:8px;background:#f8fafc;font-size:12px}.dark .vx-attachment{background:#1f2937}
        @media(max-width:1050px){.vx-summary{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.vx-summary-cell{border:0;padding:0}.vx-middle,.vx-lower{grid-template-columns:1fr}.vx-title-row{align-items:flex-start;flex-direction:column}.vx-actions{justify-content:flex-start}}
        @media(max-width:640px){.vx-pallet-redesign{padding-bottom:20px}.vx-title{font-size:23px}.vx-title-row{margin-bottom:14px}.vx-actions{display:grid;grid-template-columns:1fr 1fr;width:100%}.vx-action{width:100%;padding:0 10px;font-size:12px}.vx-actions .vx-receive{grid-column:1/-1}.vx-summary{grid-template-columns:1fr 1fr;padding:15px;gap:15px}.vx-summary-cell{gap:8px}.vx-summary-icon{width:34px;height:34px;flex-basis:34px}.vx-middle{gap:12px}.vx-status-card,.vx-progress-card{padding:16px}.vx-step-label{font-size:10px}.vx-progress-body{justify-content:flex-start}.vx-detail-card{padding:16px}.vx-details{grid-template-columns:1fr;gap:12px}.vx-detail-row{grid-template-columns:110px 1fr}.vx-lower{gap:12px}.vx-section-head{padding:14px}.vx-table th,.vx-table td{padding:11px 12px}.vx-cost,.vx-media{padding:16px}.vx-more{position:static}.vx-more-menu{left:10px;right:10px;top:auto;width:auto;margin-top:8px}}
    </style>

    <div
        class="vx-pallet-redesign"
        x-data="{ moreOpen: false }"
        @barcode-scanned.window="
            const line = window.vxPendingScanLine;
            window.vxPendingScanLine = null;
            if (line && $event.detail?.value) $wire.scanLineIntoInventory(line, $event.detail.value);
        "
    >
        <div class="vx-title-row">
            <div class="vx-title-wrap">
                <h1 class="vx-title">{{ $record->displayName() }}</h1>
                <div class="vx-meta-pills">
                    <span class="vx-pill vx-pill-blue">{{ $statusLabel }}</span>
                    <span class="vx-pill vx-pill-gray">{{ $casesReceived }} / {{ $casesExpected }} cases</span>
                </div>
            </div>

            <div class="vx-actions">
                @unless(in_array($record->status, ['received', 'processed'], true))
                    <a class="vx-action vx-action-green vx-receive" href="{{ $receiveUrl }}">
                        <x-heroicon-o-inbox-arrow-down /> Continue receiving ({{ $casesReceived }} of {{ $casesExpected }})
                    </a>
                    <a class="vx-action vx-action-blue" href="{{ $receiveUrl }}#receiving-barcode-input"><x-heroicon-o-qr-code /> Scan Item</a>
                    <a class="vx-action vx-action-green" href="{{ $receiveUrl }}"><x-heroicon-o-clipboard-document-check /> Review & Receive</a>
                @endunless
                <div class="vx-more" @click.outside="moreOpen=false">
                    <button type="button" class="vx-action vx-action-neutral" @click="moreOpen=!moreOpen"><span>⋮</span> More <span>⌄</span></button>
                    <div class="vx-more-menu" x-cloak x-show="moreOpen" x-transition>
                        <a class="vx-more-link" href="{{ $itemsUrl }}">Items from this pallet</a>
                        <a class="vx-more-link" href="{{ $addLinesUrl }}">Add / edit manifest lines</a>
                        <button class="vx-more-link" type="button" wire:click="mountAction('add_attachments')" @click="moreOpen=false">Add photos / documents</button>
                        <a class="vx-more-link" href="{{ $editUrl }}">Edit pallet details</a>
                    </div>
                </div>
            </div>
        </div>

        <section class="vx-card vx-summary">
            <div class="vx-summary-cell"><div class="vx-summary-icon"><x-heroicon-o-building-storefront class="h-5 w-5" /></div><div class="min-w-0"><div class="vx-summary-label">Vendor</div><div class="vx-summary-value">{{ $record->vendor?->name ?? '—' }}</div></div></div>
            <div class="vx-summary-cell"><div class="vx-summary-icon"><x-heroicon-o-document-text class="h-5 w-5" /></div><div class="min-w-0"><div class="vx-summary-label">PO / Reference</div><div class="vx-summary-value">{{ $record->reference ?: '—' }}</div></div></div>
            <div class="vx-summary-cell"><div class="vx-summary-icon"><x-heroicon-o-calendar-days class="h-5 w-5" /></div><div class="min-w-0"><div class="vx-summary-label">Received</div><div class="vx-summary-value">{{ $record->received_date?->format('M d, Y') ?? '—' }}</div></div></div>
            <div class="vx-summary-cell"><div class="vx-summary-icon"><x-heroicon-o-map-pin class="h-5 w-5" /></div><div class="min-w-0"><div class="vx-summary-label">Ship To</div><div class="vx-summary-value">{{ $shipTo }}</div></div></div>
            <div class="vx-summary-cell"><div class="vx-summary-icon"><x-heroicon-o-currency-dollar class="h-5 w-5" /></div><div class="min-w-0"><div class="vx-summary-label">Total Cost</div><div class="vx-summary-value">${{ number_format($landed, 2) }}</div></div></div>
        </section>

        <div class="vx-middle">
            <section class="vx-card vx-status-card">
                <h2 class="vx-card-heading">Pallet Status</h2>
                <div class="vx-timeline">
                    @foreach($phases as $status => $phase)
                        @php $isActive = $status === $record->status; $isDone = $phase['number'] < $currentPhase; @endphp
                        <div class="vx-step {{ $isDone ? 'done' : '' }} {{ $isActive ? 'active' : '' }}">
                            <div class="vx-step-dot">@if($isDone)<x-heroicon-m-check class="h-4 w-4" />@else<span>•</span>@endif</div>
                            <div class="vx-step-label">{{ $phase['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="vx-card vx-progress-card">
                <h2 class="vx-card-heading">Receiving Progress</h2>
                <div class="vx-progress-body">
                    <div class="vx-ring" style="--pct:{{ $progressPct }}"><strong>{{ $progressPct }}%</strong></div>
                    <div class="vx-progress-stats">
                        <div>{{ $casesReceived }} of {{ $casesExpected }} cases</div>
                        <div>{{ $progressPct }}% complete</div>
                        <a class="vx-progress-link" href="{{ $receiveUrl }}">Open receiving station →</a>
                    </div>
                </div>
            </section>
        </div>

        <section class="vx-card vx-detail-card">
            <div class="vx-details">
                <div class="vx-detail-row"><span class="vx-detail-label">Received Date</span><span class="vx-detail-value">{{ $record->received_date?->format('M d, Y') ?? '—' }}</span></div>
                <div class="vx-detail-row"><span class="vx-detail-label">Shipping Cost</span><span class="vx-detail-value">${{ number_format((float)$record->shipping_cost, 2) }}</span></div>
                <div class="vx-detail-row"><span class="vx-detail-label">Carrier</span><span class="vx-detail-value">{{ $record->carrier ?: '—' }}</span></div>
                <div class="vx-detail-row"><span class="vx-detail-label">Tracking #</span><span class="vx-detail-value">{{ $record->tracking_number ?: '—' }}</span></div>
                @if($record->notes)<div class="vx-detail-row" style="grid-column:1/-1"><span class="vx-detail-label">Notes</span><span class="vx-detail-value">{{ $record->notes }}</span></div>@endif
            </div>
        </section>

        <div class="vx-lower">
            <section class="vx-card vx-table-card">
                <div class="vx-section-head"><h2 class="vx-card-heading">What should be on this pallet ({{ $lines->count() }})</h2><span class="vx-section-note">{{ $casesReceived }} of {{ $casesExpected }} cases confirmed</span></div>
                @if($lines->isEmpty())
                    <div style="padding:34px 20px;text-align:center;color:#64748b;font-size:13px">No manifest lines yet. <a href="{{ $addLinesUrl }}" style="color:#2563eb;font-weight:700">Add lines</a> to build the expected shipment.</div>
                @else
                    <div class="vx-table-wrap"><table class="vx-table"><thead><tr><th>Item</th><th>Location</th><th>Expected</th><th>Received</th><th>Status</th><th style="text-align:right">Action</th></tr></thead><tbody>
                    @foreach($lines as $line)
                        @php
                            $in = $line->cases->where('status', '!=', 'expected')->count();
                            $expected = (int)$line->case_count;
                            $mapped = $line->isFullyMapped();
                            $complete = $expected > 0 && $in >= $expected;
                        @endphp
                        <tr>
                            <td><div class="vx-item">
                                <label class="vx-thumb" @if($line->inventoryItem) title="Photograph / replace item photo" @endif>
                                    @if($line->inventoryItem)<input type="file" accept="image/*" capture="environment" class="hidden" wire:model="linePhotos.{{ $line->id }}" />@endif
                                    @if($line->inventoryItem?->hasImage())<img src="{{ $line->inventoryItem->imageUrl() }}" alt="">@else<x-heroicon-o-cube class="h-5 w-5 text-gray-400" />@endif
                                </label>
                                <div><div class="vx-item-name">{{ $line->inventoryItem?->name ?? $line->description }}</div><div class="vx-item-sub">{{ $line->inventoryItem?->sku ?: 'No SKU' }} · {{ number_format((float)$line->quantity_per_case) }}/case · ${{ number_format((float)$line->unit_cost,2) }} each</div></div>
                            </div></td>
                            <td>{{ $line->location?->name ?? '—' }}</td><td>{{ $expected }} cases</td><td>{{ $in }} cases</td>
                            <td>@if(!$mapped)<span class="vx-status vx-status-amber">Needs barcode</span>@elseif($complete)<span class="vx-status vx-status-green">Complete</span>@elseif($in>0)<span class="vx-status vx-status-blue">Partial</span>@else<span class="vx-status vx-status-gray">Not started</span>@endif</td>
                            <td style="text-align:right">
                                @if(!$mapped)
                                    <button type="button" class="vx-count-btn" onclick="window.vxPendingScanLine={{ $line->id }};window.dispatchEvent(new CustomEvent('open-camera-scanner'))"><x-heroicon-o-qr-code class="h-4 w-4" /> Scan barcode</button>
                                @elseif(!$complete)
                                    <button type="button" class="vx-count-btn" wire:click="mountAction('confirmCase', { line: {{ $line->id }} })"><x-heroicon-o-plus class="h-4 w-4" /> Count a case</button>
                                @else <span style="color:#94a3b8">—</span> @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody></table></div>
                    <div style="padding:12px 18px;border-top:1px solid #edf1f5;text-align:center"><a href="{{ $itemsUrl }}" style="font-size:12px;color:#2563eb;font-weight:700;text-decoration:none">View all items on this pallet →</a></div>
                @endif
            </section>

            <aside class="vx-card vx-cost">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:start"><h2 class="vx-card-heading">Landed Cost Summary</h2>@if($units>0)<span class="vx-section-note">{{ number_format($units) }} units · ${{ number_format($landed/$units,2) }} each</span>@endif</div>
                <div class="vx-cost-lines"><div class="vx-cost-row"><span>Goods</span><strong>${{ number_format($goods,2) }}</strong></div><div class="vx-cost-row"><span>Shipping</span><strong>${{ number_format((float)$record->shipping_cost,2) }}</strong></div><div class="vx-cost-row"><span>Payment fees</span><strong>${{ number_format((float)$record->payment_fees,2) }}</strong></div><div class="vx-cost-row vx-cost-total"><span>Total</span><strong>${{ number_format($landed,2) }}</strong></div></div>
            </aside>
        </div>

        <section class="vx-card vx-media">
            <h2 class="vx-card-heading">Media & Attachments</h2>
            @if($record->attachments->isNotEmpty())<div class="vx-attachment-list">@foreach($record->attachments as $attachment)<div class="vx-attachment"><span>{{ $attachment->file_name }}</span><a href="{{ $attachment->getFileUrl() }}" target="_blank" style="color:#2563eb;font-weight:700;text-decoration:none">View</a></div>@endforeach</div>@endif
            <div class="vx-upload"><x-heroicon-o-arrow-up-tray class="h-5 w-5" /><div>Drag and drop support is handled in the upload dialog, or <button type="button" wire:click="mountAction('add_attachments')">click to upload</button><div style="font-size:10px;color:#94a3b8;margin-top:3px">Photos, documents, and signatures related to this pallet</div></div></div>
        </section>
    </div>
</x-filament-panels::page>
