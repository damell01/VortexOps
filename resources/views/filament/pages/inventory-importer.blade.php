<x-filament-panels::page>
    <style>
        .vx-importer{display:grid;gap:1rem}.vx-import-card{border:1px solid rgb(229 231 235);border-radius:1rem;background:white;padding:1rem}.dark .vx-import-card{background:rgb(17 24 39);border-color:rgb(55 65 81)}
        .vx-import-title{font-size:1rem;font-weight:850}.vx-import-muted{font-size:.8rem;color:rgb(107 114 128)}.vx-import-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.65rem;margin-top:.8rem}.vx-import-kpi{border-radius:.8rem;background:rgb(249 250 251);padding:.75rem}.dark .vx-import-kpi{background:rgb(31 41 55)}.vx-import-kpi strong{display:block;font-size:1.2rem}.vx-import-kpi span{font-size:.7rem;text-transform:uppercase;font-weight:800;color:rgb(107 114 128)}
        .vx-import-actions{display:flex;flex-wrap:wrap;gap:.6rem;margin-top:.85rem}.vx-import-btn{min-height:46px;border-radius:.75rem;padding:.7rem .9rem;font-size:.84rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;gap:.4rem}.vx-import-primary{background:rgb(37 99 235);color:white}.vx-import-success{background:rgb(5 150 105);color:white}.vx-import-secondary{border:1px solid rgb(209 213 219);background:rgb(249 250 251);color:rgb(55 65 81)}.dark .vx-import-secondary{border-color:rgb(75 85 99);background:rgb(31 41 55);color:rgb(229 231 235)}
        .vx-import-filters{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:.75rem}.vx-import-filter{min-height:42px;border-radius:999px;padding:.5rem .75rem;border:1px solid rgb(209 213 219);font-size:.78rem;font-weight:800}.vx-import-filter-active{background:rgb(17 24 39);color:white;border-color:rgb(17 24 39)}.dark .vx-import-filter-active{background:white;color:rgb(17 24 39);border-color:white}
        .vx-import-list{display:grid;gap:.65rem;margin-top:.9rem}.vx-import-row{border:1px solid rgb(229 231 235);border-radius:.85rem;padding:.8rem}.dark .vx-import-row{border-color:rgb(55 65 81)}.vx-import-row-top{display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start}.vx-import-badge{display:inline-flex;border-radius:999px;padding:.28rem .55rem;font-size:.69rem;font-weight:850}.vx-import-new{background:rgb(236 253 245);color:rgb(4 120 87)}.vx-import-existing{background:rgb(239 246 255);color:rgb(29 78 216)}.vx-import-conflict{background:rgb(254 242 242);color:rgb(185 28 28)}.vx-import-meta{margin-top:.35rem;display:flex;flex-wrap:wrap;gap:.5rem;font-size:.74rem;color:rgb(107 114 128)}.vx-import-reason{margin-top:.55rem;font-size:.78rem}.vx-import-changes{margin-top:.55rem;border-radius:.65rem;background:rgb(249 250 251);padding:.6rem;font-size:.74rem}.dark .vx-import-changes{background:rgb(31 41 55)}
        .vx-import-warning{border-radius:.75rem;background:rgb(255 247 237);color:rgb(154 52 18);padding:.75rem;font-size:.8rem}.dark .vx-import-warning{background:rgba(154,52,18,.16);color:rgb(253 186 116)}
        .vx-import-successbox{border-radius:.75rem;background:rgb(236 253 245);color:rgb(4 120 87);padding:.8rem;font-size:.82rem;font-weight:700}
        .vx-import-drop input[type=file]{display:block;width:100%;min-height:48px;border:1px dashed rgb(156 163 175);border-radius:.8rem;padding:.7rem;background:rgb(249 250 251)}.dark .vx-import-drop input[type=file]{background:rgb(31 41 55);border-color:rgb(75 85 99)}
        @media(max-width:700px){.vx-importer{gap:.75rem}.vx-import-card{border-radius:.85rem;padding:.85rem}.vx-import-kpis{grid-template-columns:1fr 1fr}.vx-import-actions{display:grid;grid-template-columns:1fr}.vx-import-btn{width:100%;min-height:50px}.vx-import-row-top{gap:.5rem}.vx-import-row{padding:.75rem}.vx-import-filters{display:grid;grid-template-columns:1fr 1fr}.vx-import-filter{border-radius:.7rem}.vx-import-kpi:last-child{grid-column:span 2}}
    </style>

    <div class="vx-importer" data-vx-page="inventory-importer">
        <section class="vx-import-card">
            <div class="vx-import-title">Upload Inventory Spreadsheet</div>
            <div class="vx-import-muted">You can upload the same sheet again later with additional products. VortexOps reviews it first and separates new items, existing items, and conflicts before anything is written.</div>

            <label class="vx-import-drop" style="display:block;margin-top:.8rem">
                <input type="file" wire:model="file" accept=".xlsx,.xls,.csv,.txt" />
            </label>

            <div class="vx-import-actions">
                <button type="button" wire:click="buildReview" wire:loading.attr="disabled" class="vx-import-btn vx-import-primary">
                    <span wire:loading.remove wire:target="buildReview">Review Spreadsheet</span>
                    <span wire:loading wire:target="buildReview">Reading spreadsheet…</span>
                </button>
                @if($reviewRows)
                    <button type="button" wire:click="resetImporter" class="vx-import-btn vx-import-secondary">Start Over</button>
                @endif
            </div>

            @if($recognizedHeaders)
                <div class="vx-import-muted" style="margin-top:.7rem">Recognized columns: {{ implode(', ', $recognizedHeaders) }}</div>
            @endif
        </section>

        @foreach($warnings as $warning)
            <div class="vx-import-warning">{{ $warning }}</div>
        @endforeach

        @if($lastImportResult)
            <div class="vx-import-successbox">
                Import complete: {{ $lastImportResult['created'] }} created, {{ $lastImportResult['updated'] }} updated, {{ $lastImportResult['skipped'] }} existing skipped.
            </div>
        @endif

        @if($reviewRows)
            <section class="vx-import-card">
                <div class="vx-import-title">Import Review</div>
                <div class="vx-import-muted">Nothing below is imported until you press Import Reviewed Items.</div>
                <div class="vx-import-kpis">
                    <div class="vx-import-kpi"><strong>{{ $summary['total'] }}</strong><span>Total Rows</span></div>
                    <div class="vx-import-kpi"><strong>{{ $summary['new'] }}</strong><span>New Items</span></div>
                    <div class="vx-import-kpi"><strong>{{ $summary['existing'] }}</strong><span>Already Exist</span></div>
                    <div class="vx-import-kpi"><strong>{{ $summary['updates'] }}</strong><span>Have Changes</span></div>
                    <div class="vx-import-kpi"><strong>{{ $summary['conflict'] }}</strong><span>Need Attention</span></div>
                </div>

                <div class="vx-import-filters">
                    <button type="button" wire:click="$toggle('showNew')" class="vx-import-filter {{ $showNew ? 'vx-import-filter-active' : '' }}">New ({{ $summary['new'] }})</button>
                    <button type="button" wire:click="$toggle('showExisting')" class="vx-import-filter {{ $showExisting ? 'vx-import-filter-active' : '' }}">Existing ({{ $summary['existing'] }})</button>
                    <button type="button" wire:click="$toggle('showConflicts')" class="vx-import-filter {{ $showConflicts ? 'vx-import-filter-active' : '' }}">Attention ({{ $summary['conflict'] }})</button>
                </div>

                <label style="display:flex;align-items:flex-start;gap:.65rem;margin-top:.9rem;padding:.75rem;border:1px solid rgb(229 231 235);border-radius:.75rem">
                    <input type="checkbox" wire:model="updateExisting" style="margin-top:.15rem;width:20px;height:20px" />
                    <span><strong style="display:block;font-size:.83rem">Update existing items from this sheet</strong><span class="vx-import-muted">Off by default. When off, existing items are shown for review but skipped. When on, nonblank descriptive/pricing fields from the sheet update the matched item; SKU/barcode identifiers are not overwritten.</span></span>
                </label>

                <div class="vx-import-list">
                    @foreach($this->filteredRows() as $row)
                        @php $status = $row['status']; $data = $row['data']; @endphp
                        <article class="vx-import-row">
                            <div class="vx-import-row-top">
                                <div style="min-width:0">
                                    <strong style="display:block;overflow-wrap:anywhere">{{ $data['name'] ?? $row['existing_name'] ?? 'Unnamed row' }}</strong>
                                    <div class="vx-import-meta">
                                        <span>Row {{ $row['sheet_row'] }}</span>
                                        @if(!empty($data['sku']))<span>SKU {{ $data['sku'] }}</span>@endif
                                        @if(!empty($data['barcode']))<span>Barcode {{ $data['barcode'] }}</span>@endif
                                        @if(!empty($data['upc']))<span>UPC {{ $data['upc'] }}</span>@endif
                                    </div>
                                </div>
                                <span class="vx-import-badge {{ $status === 'new' ? 'vx-import-new' : ($status === 'existing' ? 'vx-import-existing' : 'vx-import-conflict') }}">
                                    {{ $status === 'new' ? 'NEW' : ($status === 'existing' ? 'EXISTING' : 'ATTENTION') }}
                                </span>
                            </div>

                            <div class="vx-import-reason">{{ $row['reason'] }}</div>

                            @if($status === 'existing' && $row['existing_name'])
                                <div class="vx-import-muted" style="margin-top:.35rem">Matched to: <strong>{{ $row['existing_name'] }}</strong></div>
                            @endif

                            @if(!empty($row['changes']))
                                <div class="vx-import-changes">
                                    <strong>Changes found in sheet:</strong>
                                    @foreach($row['changes'] as $field => $change)
                                        <div style="margin-top:.25rem"><span style="font-weight:700">{{ ucwords(str_replace('_',' ',$field)) }}:</span> {{ $change['from'] === '' ? 'blank' : $change['from'] }} → {{ $change['to'] }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                <div class="vx-import-actions" style="margin-top:1rem">
                    <button type="button" wire:click="importReviewed" wire:confirm="Import the reviewed inventory rows? New items will be created. Existing items will only be updated if the update option is enabled." wire:loading.attr="disabled" class="vx-import-btn vx-import-success">
                        <span wire:loading.remove wire:target="importReviewed">Import Reviewed Items</span>
                        <span wire:loading wire:target="importReviewed">Importing…</span>
                    </button>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
