<x-filament-panels::page>
@php
    $palletUrl = \App\Filament\Resources\PalletResource::getUrl('view', ['record' => $this->record]);
@endphp
<style>
.vx-ai{max-width:1180px;margin:0 auto;display:grid;gap:14px}.vx-card{border:1px solid #e5e7eb;background:#fff;border-radius:16px;box-shadow:0 1px 2px rgba(15,23,42,.04)}.dark .vx-card{background:#101827;border-color:#263248}.vx-head{padding:18px 20px}.vx-stepper{display:grid;grid-template-columns:repeat(4,1fr);padding:14px 18px}.vx-step{text-align:center;font-size:10px;font-weight:800;color:#94a3b8;position:relative}.vx-step:before{content:'';position:absolute;top:11px;left:-50%;right:50%;height:2px;background:#e5e7eb}.vx-step:first-child:before{display:none}.vx-dot{position:relative;z-index:1;width:24px;height:24px;border-radius:50%;margin:0 auto 5px;display:grid;place-items:center;background:#f3f4f6}.vx-step.done,.vx-step.active{color:#7c3aed}.vx-step.done .vx-dot,.vx-step.active .vx-dot{background:#ede9fe;color:#7c3aed}.vx-step.done:before,.vx-step.active:before{background:#c4b5fd}.vx-section{padding:20px}.vx-upload{border:2px dashed #d8dee8;border-radius:14px;padding:30px 18px;text-align:center;background:#fafbfc}.dark .vx-upload{background:#111827;border-color:#374151}.vx-btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;border-radius:10px;padding:8px 13px;border:1px solid #d1d5db;font-size:12px;font-weight:800;color:#374151;background:#fff}.dark .vx-btn{background:#111827;border-color:#475569;color:#e5e7eb}.vx-btn.primary{background:#7c3aed;border-color:#7c3aed;color:#fff}.vx-btn.success{background:#059669;border-color:#059669;color:#fff}.vx-status{display:flex;gap:14px;align-items:flex-start;border-radius:14px;background:#f5f3ff;padding:16px}.dark .vx-status{background:#24183f}.vx-spin{width:34px;height:34px;border:3px solid #ddd6fe;border-top-color:#7c3aed;border-radius:50%;animation:vxspin 1s linear infinite;flex:none}@keyframes vxspin{to{transform:rotate(360deg)}}.vx-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.vx-stat{border-radius:12px;background:#f8fafc;padding:12px}.dark .vx-stat{background:#1f2937}.vx-stat label{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.06em;font-weight:800;color:#94a3b8}.vx-stat strong{display:block;margin-top:3px;font-size:18px}.vx-lines{display:grid;gap:10px}.vx-line{border:1px solid #e5e7eb;border-radius:14px;padding:14px}.dark .vx-line{border-color:#334155}.vx-line-grid{display:grid;grid-template-columns:minmax(220px,1.3fr) 90px 90px 110px minmax(220px,.9fr);gap:10px;align-items:start}.vx-label{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.06em;font-weight:800;color:#94a3b8;margin-bottom:4px}.vx-input{width:100%;min-height:40px;border:1px solid #d1d5db;border-radius:9px;padding:7px 9px;font-size:12px;background:#fff}.dark .vx-input{background:#111827;border-color:#475569;color:#fff}.vx-match{border-radius:11px;padding:10px;background:#f8fafc}.dark .vx-match{background:#1f2937}.vx-match-name{font-size:12px;font-weight:800}.vx-meta{font-size:10px;color:#6b7280;margin-top:3px}.vx-alt{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}.vx-alt button{border:1px solid #d1d5db;border-radius:8px;padding:5px 7px;font-size:10px;font-weight:700}.dark .vx-alt button{border-color:#475569}.vx-high{color:#047857}.vx-medium{color:#b45309}.vx-low{color:#b91c1c}.vx-footer{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}.vx-error{border-radius:12px;background:#fef2f2;color:#b91c1c;padding:12px;font-size:12px}
@media(max-width:900px){.vx-line-grid{grid-template-columns:1fr 1fr}.vx-line-grid>div:first-child,.vx-line-grid>div:last-child{grid-column:1/-1}.vx-summary{grid-template-columns:1fr 1fr}}
@media(max-width:640px){.vx-ai{gap:10px}.vx-head,.vx-section{padding:14px}.vx-stepper{padding:12px 8px}.vx-step{font-size:9px}.vx-btn{min-height:44px}.vx-footer{display:grid;grid-template-columns:1fr 1fr}.vx-footer .vx-btn{width:100%}}
</style>
<div class="vx-ai">
    <section class="vx-card vx-head">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><div class="text-xs font-bold uppercase tracking-wide text-violet-600">AI Manifest</div><h1 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $this->record->displayName() }}</h1><p class="mt-1 text-xs text-gray-500">Upload the supplier manifest, let AI work in the background, then review every suggested mapping before anything is committed.</p></div>
            <a href="{{ $palletUrl }}" class="vx-btn">Back to Pallet</a>
        </div>
    </section>

    @php $keys=['upload','processing','verify','done']; $current=array_search($stage,$keys,true); @endphp
    <section class="vx-card vx-stepper">
        @foreach(['Upload','AI Analysis','Review Matches','Approved'] as $i=>$label)
            <div class="vx-step {{ $i < $current ? 'done' : ($i === $current ? 'active' : '') }}"><div class="vx-dot">{{ $i < $current ? '✓' : $i+1 }}</div>{{ $label }}</div>
        @endforeach
    </section>

    @if($stage === 'upload')
        <section class="vx-card vx-section space-y-4">
            <div><h2 class="text-sm font-bold">Upload manifest / PO / packing slip</h2><p class="mt-1 text-xs text-gray-500">PDF, image, CSV, TXT, XLS, or XLSX up to 20 MB. The AI job runs on the dedicated queue so you do not have to wait on this screen.</p></div>
            @if($parseError)<div class="vx-error"><strong>AI analysis failed.</strong><div class="mt-1">{{ $parseError }}</div></div>@endif
            <div class="vx-upload">
                <x-heroicon-o-document-arrow-up class="mx-auto h-9 w-9 text-violet-500" />
                <div class="mt-2 text-sm font-semibold">Choose a manifest file</div>
                <div class="mt-1 text-xs text-gray-500">VortexOps will extract line items and suggest existing inventory matches.</div>
                <label class="vx-btn primary mt-4 cursor-pointer">Choose File<input type="file" wire:model="slipFile" accept="image/*,.pdf,.csv,.txt,.xls,.xlsx" class="sr-only" /></label>
                @if($slipFile)<div class="mt-3 text-xs font-semibold text-emerald-600">✓ {{ $slipFile->getClientOriginalName() }}</div>@endif
                <div wire:loading wire:target="slipFile" class="mt-2 text-xs text-gray-400">Uploading…</div>
            </div>
            <div class="flex flex-wrap gap-2"><button type="button" wire:click="parseSlip" wire:loading.attr="disabled" class="vx-btn primary" @disabled(!$slipFile)><span wire:loading.remove wire:target="parseSlip">Launch AI Analysis</span><span wire:loading wire:target="parseSlip">Starting job…</span></button><a href="{{ $palletUrl }}" class="vx-btn">Cancel</a></div>
        </section>
    @endif

    @if($stage === 'processing')
        <section wire:poll.10000ms="checkProcessing" class="vx-card vx-section">
            <div class="vx-status"><div class="vx-spin"></div><div><div class="font-bold text-violet-900 dark:text-violet-100">AI analysis is running in the background</div><div class="mt-1 text-xs text-violet-700 dark:text-violet-300">You can close this page or work anywhere else in VortexOps. You will receive a notification when extraction and inventory matching are finished.</div><div class="mt-2 text-[10px] text-violet-500">Task #{{ $aiTaskId }} · Checking status every 10 seconds while this page is open</div></div></div>
            <div class="mt-4 flex gap-2"><a href="{{ $palletUrl }}" class="vx-btn primary">Return to Pallet</a><button wire:click="checkProcessing" class="vx-btn">Check Now</button></div>
        </section>
    @endif

    @if($stage === 'verify')
        <section class="vx-card vx-section space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-sm font-bold">Review AI Suggestions</h2><p class="mt-1 text-xs text-gray-500">Nothing below has been added to the pallet yet. Confirm existing-item matches or choose Create New, then approve the manifest.</p></div><button wire:click="startOver" class="vx-btn">Analyze Another File</button></div>
            <div class="vx-summary">
                <div class="vx-stat"><label>Lines</label><strong>{{ count($parsedLines) }}</strong></div>
                <div class="vx-stat"><label>Suggested Matches</label><strong>{{ collect($parsedLines)->whereNotNull('matched_item_id')->count() }}</strong></div>
                <div class="vx-stat"><label>New Items</label><strong>{{ collect($parsedLines)->where('create_new_item',true)->count() }}</strong></div>
                <div class="vx-stat"><label>Need Review</label><strong>{{ collect($parsedLines)->filter(fn($l)=>empty($l['matched_item_id']) || ($l['match_confidence_score']??0)<.95)->count() }}</strong></div>
            </div>
            <div class="vx-lines">
                @forelse($parsedLines as $i=>$line)
                    <div class="vx-line" wire:key="manifest-line-{{ $i }}">
                        <div class="vx-line-grid">
                            <div><label class="vx-label">Manifest Item</label><input class="vx-input" wire:model="parsedLines.{{ $i }}.description" /></div>
                            <div><label class="vx-label">Cases</label><input class="vx-input" type="number" min="1" wire:model="parsedLines.{{ $i }}.case_count" /></div>
                            <div><label class="vx-label">Units / Case</label><input class="vx-input" type="number" min="1" wire:model="parsedLines.{{ $i }}.quantity_per_case" /></div>
                            <div><label class="vx-label">Unit Cost</label><input class="vx-input" wire:model="parsedLines.{{ $i }}.unit_cost" /></div>
                            <div><label class="vx-label">Inventory Decision</label><div class="vx-match">
                                @if(!empty($line['matched_item_id']) && empty($line['create_new_item']))
                                    <div class="vx-match-name">✓ {{ $line['matched_item_name'] }}</div>
                                    <div class="vx-meta {{ ($line['match_confidence']??'')==='high'?'vx-high':((($line['match_confidence']??'')==='medium')?'vx-medium':'vx-low') }}">{{ ucfirst($line['match_confidence'] ?: 'low') }} confidence · {{ $line['match_stage'] ?: 'suggested' }}</div>
                                    @if(!empty($line['match_reasons']))<div class="vx-meta">{{ implode(' · ', array_slice($line['match_reasons'],0,2)) }}</div>@endif
                                @else
                                    <div class="vx-match-name">Create new inventory item</div><div class="vx-meta">No existing item is currently selected.</div>
                                @endif
                                @if(!empty($line['alternatives']))<div class="vx-alt">@foreach($line['alternatives'] as $alt)<button type="button" wire:click="chooseMatch({{ $i }}, {{ $alt['id'] }})">{{ $alt['name'] }}</button>@endforeach</div>@endif
                                <div class="vx-alt"><button type="button" wire:click="chooseCreateNew({{ $i }})">+ Create New</button></div>
                            </div></div>
                        </div>
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2"><div><label class="vx-label">SKU</label><input class="vx-input" wire:model="parsedLines.{{ $i }}.sku" /></div><div><label class="vx-label">Barcode / UPC</label><input class="vx-input" wire:model="parsedLines.{{ $i }}.barcode" /></div></div>
                        <div class="mt-2 text-right"><button type="button" wire:click="removeLine({{ $i }})" class="text-xs font-semibold text-red-500">Remove line</button></div>
                    </div>
                @empty<div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">No lines were extracted. Add one manually or analyze a different file.</div>@endforelse
            </div>
            <button type="button" wire:click="addLine" class="vx-btn">+ Add Line</button>
            <div class="vx-footer border-t border-gray-100 pt-4 dark:border-gray-800"><div class="text-xs text-gray-500">Review the suggested mappings before approval. AI never commits inventory mappings automatically.</div><button type="button" wire:click="import" wire:loading.attr="disabled" class="vx-btn success"><span wire:loading.remove wire:target="import">Approve & Build Manifest</span><span wire:loading wire:target="import">Building manifest…</span></button></div>
        </section>
    @endif

    @if($stage === 'done')
        <section class="vx-card vx-section text-center"><x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-emerald-500" /><h2 class="mt-2 text-lg font-bold">Manifest approved</h2><p class="mt-1 text-sm text-gray-500">{{ $created }} lines created · {{ $matched }} mapped · {{ $unmatched }} unmapped.</p><a href="{{ $palletUrl }}" class="vx-btn primary mt-4">Return to Pallet</a></section>
    @endif
</div>
</x-filament-panels::page>
