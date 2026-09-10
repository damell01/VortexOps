<x-filament-panels::page>
@php
    $palletUrl = \App\Filament\Resources\PalletResource::getUrl('view', ['record' => $this->record]);
    $sourceUrl = $aiTaskId ? route('admin.manifest-source', ['task' => $aiTaskId]) : null;
    $previewableImage = in_array($sourceExtension, ['jpg','jpeg','png','gif','webp'], true);
    $previewablePdf = $sourceExtension === 'pdf';
    $reviewCount = collect($parsedLines)->filter(fn($l)=>empty($l['matched_item_id']) || ($l['match_confidence_score']??0)<.95)->count();
    $matchedCount = collect($parsedLines)->whereNotNull('matched_item_id')->count();
    $newItemCount = collect($parsedLines)->where('create_new_item',true)->count();
@endphp
<style>
.vx-ai{max-width:1680px;margin:0 auto;display:grid;gap:14px}.vx-card{border:1px solid #e6eaf0;background:#fff;border-radius:16px;box-shadow:0 1px 2px rgba(15,23,42,.03)}.dark .vx-card{background:#111827;border-color:#263248}.vx-head{padding:16px 18px}.vx-headbar{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}.vx-kicker{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#7c3aed}.vx-title{margin-top:2px;font-size:22px;line-height:1.2;font-weight:800;color:#0f172a}.dark .vx-title{color:#fff}.vx-sub{margin-top:4px;font-size:11px;line-height:1.45;color:#64748b}.vx-btn{display:inline-flex;align-items:center;justify-content:center;gap:5px;min-height:36px;border-radius:9px;padding:7px 11px;border:1px solid #d7dce3;font-size:10px;font-weight:800;color:#334155;background:#fff;transition:.15s ease}.vx-btn:hover{background:#f8fafc}.dark .vx-btn{background:#111827;border-color:#475569;color:#e5e7eb}.vx-btn.primary{background:#7c3aed;border-color:#7c3aed;color:#fff}.vx-btn.success{background:#059669;border-color:#059669;color:#fff}.vx-stepper{display:grid;grid-template-columns:repeat(4,1fr);padding:11px 16px}.vx-step{text-align:center;font-size:9px;font-weight:800;color:#94a3b8;position:relative}.vx-step:before{content:'';position:absolute;top:10px;left:-50%;right:50%;height:2px;background:#e5e7eb}.vx-step:first-child:before{display:none}.vx-dot{position:relative;z-index:1;width:22px;height:22px;border-radius:999px;margin:0 auto 4px;display:grid;place-items:center;background:#f3f4f6;font-size:9px}.vx-step.done,.vx-step.active{color:#7c3aed}.vx-step.done .vx-dot,.vx-step.active .vx-dot{background:#ede9fe;color:#7c3aed}.vx-step.done:before,.vx-step.active:before{background:#c4b5fd}.vx-section{padding:16px}.vx-upload{border:2px dashed #d8dee8;border-radius:14px;padding:28px 18px;text-align:center;background:#fafbfc}.dark .vx-upload{background:#111827;border-color:#374151}.vx-status{display:flex;gap:12px;align-items:flex-start;border-radius:12px;background:#f5f3ff;padding:14px}.dark .vx-status{background:#24183f}.vx-spin{width:30px;height:30px;border:3px solid #ddd6fe;border-top-color:#7c3aed;border-radius:50%;animation:vxspin 1s linear infinite;flex:none}@keyframes vxspin{to{transform:rotate(360deg)}}
.vx-review-shell{display:grid;grid-template-columns:minmax(440px,.82fr) minmax(0,1.55fr);gap:14px;align-items:start}.vx-source{position:sticky;top:10px;overflow:hidden}.vx-source-head{padding:12px 14px;border-bottom:1px solid #e6eaf0}.dark .vx-source-head{border-color:#263248}.vx-source-frame,.vx-source-img{width:100%;height:calc(100vh - 205px);min-height:680px;background:#f8fafc}.vx-source-frame{border:0}.vx-source-img{display:block;object-fit:contain}.vx-source-fallback{padding:34px 18px;text-align:center;color:#64748b}
.vx-review{display:grid;gap:10px}.vx-review-toolbar{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:14px 15px}.vx-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.vx-stat{border:1px solid #edf0f4;border-radius:12px;background:#fafbfc;padding:10px 12px}.dark .vx-stat{background:#1f2937;border-color:#334155}.vx-stat label{display:block;font-size:8px;text-transform:uppercase;letter-spacing:.06em;font-weight:800;color:#94a3b8}.vx-stat strong{display:block;margin-top:2px;font-size:18px;line-height:1.1}.vx-review-note{display:flex;align-items:center;gap:7px;font-size:10px;color:#64748b}.vx-review-note strong{color:#b45309}
.vx-lines{display:grid;gap:8px}.vx-line{border:1px solid #e6eaf0;border-radius:13px;background:#fff;overflow:hidden}.dark .vx-line{background:#111827;border-color:#334155}.vx-line-top{display:grid;grid-template-columns:minmax(0,1.6fr) 74px 84px 92px;gap:8px;padding:10px 11px;border-bottom:1px solid #edf0f4}.dark .vx-line-top{border-color:#2b3647}.vx-label{display:block;font-size:8px;text-transform:uppercase;letter-spacing:.055em;font-weight:800;color:#94a3b8;margin-bottom:3px}.vx-input{width:100%;min-height:34px;border:1px solid #d5dae1;border-radius:8px;padding:6px 8px;font-size:11px;background:#fff}.vx-input:focus{outline:none;border-color:#8b5cf6;box-shadow:0 0 0 2px rgba(139,92,246,.1)}.dark .vx-input{background:#0f172a;border-color:#475569;color:#fff}.vx-line-body{display:grid;grid-template-columns:minmax(0,1fr) minmax(250px,.78fr);gap:10px;padding:10px 11px}.vx-meta-fields{display:grid;grid-template-columns:1fr 1fr;gap:8px}.vx-match{border:1px solid #e7eaf0;border-radius:10px;padding:9px 10px;background:#fafbfc}.dark .vx-match{background:#1f2937;border-color:#334155}.vx-match-head{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}.vx-match-name{font-size:11px;font-weight:800;line-height:1.3}.vx-pill{display:inline-flex;align-items:center;border-radius:999px;padding:2px 6px;font-size:8px;font-weight:800;white-space:nowrap}.vx-pill.high{background:#ecfdf5;color:#047857}.vx-pill.medium{background:#fffbeb;color:#b45309}.vx-pill.low{background:#fef2f2;color:#b91c1c}.dark .vx-pill.high{background:#064e3b;color:#a7f3d0}.dark .vx-pill.medium{background:#78350f;color:#fde68a}.dark .vx-pill.low{background:#7f1d1d;color:#fecaca}.vx-meta{font-size:9px;color:#6b7280;margin-top:3px;line-height:1.3}.vx-alt{display:flex;flex-wrap:wrap;gap:5px;margin-top:7px}.vx-alt button{border:1px solid #d7dce3;border-radius:7px;padding:4px 7px;font-size:9px;font-weight:700;background:#fff}.dark .vx-alt button{border-color:#475569;background:#111827;color:#e5e7eb}.vx-line-actions{display:flex;justify-content:flex-end;padding:0 11px 9px}.vx-remove{font-size:9px;font-weight:800;color:#dc2626}.vx-footer{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap;position:sticky;bottom:8px;background:rgba(255,255,255,.97);backdrop-filter:blur(9px);padding:10px 11px;border:1px solid #e6eaf0;border-radius:12px;box-shadow:0 8px 24px rgba(15,23,42,.06)}.dark .vx-footer{background:rgba(17,24,39,.97);border-color:#334155}.vx-error{border-radius:10px;background:#fef2f2;color:#b91c1c;padding:10px;font-size:11px}
@media(max-width:1180px){.vx-review-shell{grid-template-columns:1fr}.vx-source{position:static}.vx-source-frame,.vx-source-img{height:520px;min-height:0}}
@media(max-width:900px){.vx-line-top{grid-template-columns:1fr 1fr}.vx-line-top>div:first-child{grid-column:1/-1}.vx-line-body{grid-template-columns:1fr}.vx-summary{grid-template-columns:1fr 1fr}}
@media(max-width:640px){.vx-ai{gap:9px}.vx-head,.vx-section{padding:12px}.vx-headbar,.vx-review-toolbar{display:grid;grid-template-columns:1fr}.vx-stepper{padding:10px 5px}.vx-step{font-size:8px}.vx-btn{min-height:42px}.vx-summary{grid-template-columns:1fr 1fr}.vx-line-top{grid-template-columns:1fr 1fr;padding:9px}.vx-line-top>div:first-child{grid-column:1/-1}.vx-line-body{padding:9px}.vx-meta-fields{grid-template-columns:1fr}.vx-footer{display:grid;grid-template-columns:1fr}.vx-footer .vx-btn{width:100%}.vx-source-frame,.vx-source-img{height:410px}.vx-title{font-size:19px}}
</style>
<div class="vx-ai">
    <section class="vx-card vx-head">
        <div class="vx-headbar">
            <div>
                <div class="vx-kicker">AI Manifest Review</div>
                <h1 class="vx-title">{{ $this->record->displayName() }}</h1>
                <p class="vx-sub">Compare the uploaded document with AI extraction, confirm inventory matches, and approve the staged manifest.</p>
            </div>
            <a href="{{ $palletUrl }}" class="vx-btn">Back to Pallet</a>
        </div>
    </section>

    @php $keys=['upload','processing','verify','done']; $current=array_search($stage,$keys,true); @endphp
    <section class="vx-card vx-stepper">
        @foreach(['Upload','AI Analysis','Review','Approved'] as $i=>$label)
            <div class="vx-step {{ $i < $current ? 'done' : ($i === $current ? 'active' : '') }}">
                <div class="vx-dot">{{ $i < $current ? '✓' : $i+1 }}</div>{{ $label }}
            </div>
        @endforeach
    </section>

    @if($stage === 'upload')
        <section class="vx-card vx-section space-y-4">
            <div><h2 class="text-sm font-bold">Upload manifest / PO / packing slip</h2><p class="mt-1 text-xs text-gray-500">PDF, Word, image, CSV, TXT, XLS, or XLSX up to 20 MB.</p></div>
            @if($parseError)<div class="vx-error"><strong>AI analysis failed.</strong><div class="mt-1">{{ $parseError }}</div></div>@endif
            <div class="vx-upload"><x-heroicon-o-document-arrow-up class="mx-auto h-8 w-8 text-violet-500" /><div class="mt-2 text-sm font-semibold">Choose a manifest file</div><div class="mt-1 text-xs text-gray-500">VortexOps extracts the document, normalizes product lines, and suggests inventory matches.</div><label class="vx-btn primary mt-4 cursor-pointer">Choose File<input type="file" wire:model="slipFile" accept="image/*,.pdf,.csv,.txt,.xls,.xlsx,.doc,.docx" class="sr-only" /></label>@if($slipFile)<div class="mt-3 text-xs font-semibold text-emerald-600">✓ {{ $slipFile->getClientOriginalName() }}</div>@endif<div wire:loading wire:target="slipFile" class="mt-2 text-xs text-gray-400">Uploading…</div></div>
            <div class="flex flex-wrap gap-2"><button type="button" wire:click="parseSlip" wire:loading.attr="disabled" class="vx-btn primary" @disabled(!$slipFile)><span wire:loading.remove wire:target="parseSlip">Launch AI Analysis</span><span wire:loading wire:target="parseSlip">Starting job…</span></button><a href="{{ $palletUrl }}" class="vx-btn">Cancel</a></div>
        </section>
    @endif

    @if($stage === 'processing')
        <section wire:poll.10000ms="checkProcessing" class="vx-card vx-section"><div class="vx-status"><div class="vx-spin"></div><div><div class="font-bold text-violet-900 dark:text-violet-100">AI analysis is running in the background</div><div class="mt-1 text-xs text-violet-700 dark:text-violet-300">You can leave this page. The original source stays attached so it can be compared with the extracted results.</div><div class="mt-2 text-[10px] text-violet-500">Task #{{ $aiTaskId }} · checking every 10 seconds</div></div></div><div class="mt-4 flex gap-2"><a href="{{ $palletUrl }}" class="vx-btn primary">Return to Pallet</a><button wire:click="checkProcessing" class="vx-btn">Check Now</button></div></section>
    @endif

    @if($stage === 'verify')
        <div class="vx-review-shell">
            <section class="vx-card vx-source">
                <div class="vx-source-head"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="vx-label">Original Source</div><div class="truncate text-xs font-bold">{{ $sourceOriginalName ?: 'Uploaded manifest' }}</div></div>@if($sourceUrl)<a href="{{ $sourceUrl }}" target="_blank" rel="noopener" class="vx-btn">Open Full Size</a>@endif</div></div>
                @if($sourceUrl && $previewablePdf)<iframe class="vx-source-frame" src="{{ $sourceUrl }}#toolbar=1&navpanes=0" title="Manifest source document"></iframe>@elseif($sourceUrl && $previewableImage)<a href="{{ $sourceUrl }}" target="_blank" rel="noopener"><img class="vx-source-img" src="{{ $sourceUrl }}" alt="Manifest source document" /></a>@elseif($sourceUrl)<div class="vx-source-fallback"><x-heroicon-o-document-text class="mx-auto h-10 w-10" /><div class="mt-3 text-sm font-semibold">Preview unavailable for this file type.</div><a href="{{ $sourceUrl }}" target="_blank" rel="noopener" class="vx-btn primary mt-4">Open Source File</a></div>@else<div class="vx-source-fallback">The original source file is unavailable for this older AI task.</div>@endif
            </section>

            <section class="vx-review">
                <div class="vx-card vx-review-toolbar">
                    <div><h2 class="text-sm font-bold">Review AI Suggestions</h2><p class="mt-1 text-[10px] text-gray-500">Edit extracted values, confirm each inventory decision, then approve. Physical receiving is still a separate step.</p></div>
                    <button wire:click="startOver" class="vx-btn">Analyze Another</button>
                </div>

                <div class="vx-summary">
                    <div class="vx-stat"><label>Lines</label><strong>{{ count($parsedLines) }}</strong></div>
                    <div class="vx-stat"><label>Matched</label><strong>{{ $matchedCount }}</strong></div>
                    <div class="vx-stat"><label>New Items</label><strong>{{ $newItemCount }}</strong></div>
                    <div class="vx-stat"><label>Needs Review</label><strong>{{ $reviewCount }}</strong></div>
                </div>

                @if($reviewCount > 0)<div class="vx-review-note"><x-heroicon-o-exclamation-triangle class="h-4 w-4" /><span><strong>{{ $reviewCount }} line{{ $reviewCount === 1 ? '' : 's' }}</strong> should be checked before approval.</span></div>@endif

                <div class="vx-lines">
                    @forelse($parsedLines as $i=>$line)
                        @php $confidence = $line['match_confidence'] ?: 'low'; @endphp
                        <div class="vx-line" wire:key="manifest-line-{{ $i }}">
                            <div class="vx-line-top">
                                <div><label class="vx-label">Manifest Item</label><input class="vx-input" wire:model="parsedLines.{{ $i }}.description" /></div>
                                <div><label class="vx-label">Cases</label><input class="vx-input" type="number" min="1" wire:model="parsedLines.{{ $i }}.case_count" /></div>
                                <div><label class="vx-label">Units / Case</label><input class="vx-input" type="number" min="1" wire:model="parsedLines.{{ $i }}.quantity_per_case" /></div>
                                <div><label class="vx-label">Unit Cost</label><input class="vx-input" wire:model="parsedLines.{{ $i }}.unit_cost" /></div>
                            </div>
                            <div class="vx-line-body">
                                <div class="vx-meta-fields">
                                    <div><label class="vx-label">SKU</label><input class="vx-input" wire:model="parsedLines.{{ $i }}.sku" /></div>
                                    <div><label class="vx-label">Barcode</label><input class="vx-input" wire:model="parsedLines.{{ $i }}.barcode" /></div>
                                </div>
                                <div>
                                    <label class="vx-label">Inventory Decision</label>
                                    <div class="vx-match">
                                        @if(!empty($line['matched_item_id']) && empty($line['create_new_item']))
                                            <div class="vx-match-head"><div class="vx-match-name">✓ {{ $line['matched_item_name'] }}</div><span class="vx-pill {{ $confidence }}">{{ ucfirst($confidence) }}</span></div>
                                            <div class="vx-meta">{{ $line['match_stage'] ?: 'Suggested match' }}</div>
                                            @if(!empty($line['match_reasons']))<div class="vx-meta">{{ implode(' · ', array_slice($line['match_reasons'],0,2)) }}</div>@endif
                                        @else
                                            <div class="vx-match-head"><div class="vx-match-name">Create new inventory item</div><span class="vx-pill low">New</span></div><div class="vx-meta">No existing item is selected for this manifest line.</div>
                                        @endif
                                        @if(!empty($line['alternatives']))<div class="vx-alt">@foreach($line['alternatives'] as $alt)<button type="button" wire:click="chooseMatch({{ $i }}, {{ $alt['id'] }})">{{ $alt['name'] }}</button>@endforeach</div>@endif
                                        <div class="vx-alt"><button type="button" wire:click="chooseCreateNew({{ $i }})">+ Create New</button></div>
                                    </div>
                                </div>
                            </div>
                            <div class="vx-line-actions"><button type="button" wire:click="removeLine({{ $i }})" class="vx-remove">Remove line</button></div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-xs text-gray-500">No extracted lines were returned.</div>
                    @endforelse
                </div>

                <div><button type="button" wire:click="addLine" class="vx-btn">+ Add Line</button></div>
                <div class="vx-footer"><div class="text-[10px] text-gray-500">Review anything marked medium, low, new, or unmatched before approval.</div><div class="flex gap-2"><button wire:click="startOver" class="vx-btn">Start Over</button><button wire:click="import" wire:loading.attr="disabled" class="vx-btn success"><span wire:loading.remove wire:target="import">Approve Manifest</span><span wire:loading wire:target="import">Approving…</span></button></div></div>
            </section>
        </div>
    @endif

    @if($stage === 'done')<section class="vx-card vx-section text-center"><x-heroicon-o-check-circle class="mx-auto h-10 w-10 text-emerald-500" /><h2 class="mt-2 text-lg font-bold">Manifest approved</h2><p class="mt-1 text-xs text-gray-500">{{ $created }} lines processed · {{ $matched }} matched · {{ $unmatched }} unmatched.</p><a href="{{ $palletUrl }}" class="vx-btn primary mt-4">Return to Pallet</a></section>@endif
</div>
</x-filament-panels::page>
