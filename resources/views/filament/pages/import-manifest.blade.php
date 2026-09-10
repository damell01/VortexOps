<x-filament-panels::page>
@php
    $palletUrl = \App\Filament\Resources\PalletResource::getUrl('view', ['record' => $this->record]);
    $sourceUrl = $aiTaskId ? route('admin.manifest-source', ['task' => $aiTaskId]) : null;
    $previewableImage = in_array($sourceExtension, ['jpg','jpeg','png','gif','webp'], true);
    $previewablePdf = $sourceExtension === 'pdf';
    $matchedCount = collect($parsedLines)->whereNotNull('matched_item_id')->count();
    $newItemCount = collect($parsedLines)->where('create_new_item', true)->count();
    $reviewCount = collect($parsedLines)->filter(fn ($l) => empty($l['matched_item_id']) || ($l['match_confidence_score'] ?? 0) < .95)->count();
    $confidenceScores = collect($parsedLines)->pluck('match_confidence_score')->filter(fn ($v) => is_numeric($v) && (float) $v > 0);
    $avgConfidence = $confidenceScores->count() ? round($confidenceScores->avg() * 100) : 0;
    $totalCases = collect($parsedLines)->sum(fn ($l) => max(0, (int) ($l['case_count'] ?? 0)));
    $totalUnits = collect($parsedLines)->sum(fn ($l) => max(0, (int) ($l['case_count'] ?? 0)) * max(1, (float) ($l['quantity_per_case'] ?? 1)));
    $estimatedValue = collect($parsedLines)->sum(function ($l) {
        $cost = (float) str_replace(['$', ','], '', (string) ($l['unit_cost'] ?? 0));
        return max(0, (int) ($l['case_count'] ?? 0)) * max(1, (float) ($l['quantity_per_case'] ?? 1)) * $cost;
    });
@endphp
<style>
.vx-ai{max-width:1680px;margin:0 auto;display:grid;gap:14px}.vx-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 1px 2px rgba(15,23,42,.04)}.dark .vx-card{background:#111827;border-color:#263248}.vx-page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.vx-breadcrumb{font-size:10px;color:#64748b;margin-bottom:5px}.vx-title-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.vx-title{font-size:24px;font-weight:800;line-height:1.15;color:#111827}.dark .vx-title{color:#fff}.vx-beta{display:inline-flex;align-items:center;border:1px solid #c4b5fd;background:#f5f3ff;color:#6d28d9;border-radius:6px;padding:2px 7px;font-size:9px;font-weight:800}.vx-sub{margin-top:4px;font-size:11px;color:#64748b}.vx-actions{display:flex;gap:8px;flex-wrap:wrap}.vx-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:38px;border:1px solid #d9dee7;background:#fff;color:#334155;border-radius:9px;padding:8px 12px;font-size:10px;font-weight:800;transition:.15s}.vx-btn:hover{background:#f8fafc}.vx-btn.primary{background:linear-gradient(135deg,#7c3aed,#6d28d9);border-color:#7c3aed;color:#fff;box-shadow:0 5px 14px rgba(124,58,237,.18)}.vx-btn.success{background:#059669;border-color:#059669;color:#fff}.dark .vx-btn{background:#111827;color:#e5e7eb;border-color:#475569}.dark .vx-btn.primary{background:#7c3aed;color:#fff}.vx-stepper{padding:10px 14px;display:grid;grid-template-columns:repeat(4,1fr);gap:6px}.vx-step{display:flex;align-items:center;gap:10px;position:relative;padding:2px 6px}.vx-step:after{content:'';position:absolute;left:48px;right:-8px;top:17px;height:1px;background:#d8dee8}.vx-step:last-child:after{display:none}.vx-step-dot{position:relative;z-index:1;flex:none;width:32px;height:32px;border-radius:999px;display:grid;place-items:center;background:#64748b;color:#fff;font-size:11px;font-weight:800;box-shadow:0 0 0 4px #fff}.dark .vx-step-dot{box-shadow:0 0 0 4px #111827}.vx-step.done .vx-step-dot{background:#1677d2}.vx-step.active .vx-step-dot{background:#7c3aed;box-shadow:0 0 0 4px #ede9fe}.vx-step-copy{position:relative;z-index:1;background:#fff;padding-right:6px}.dark .vx-step-copy{background:#111827}.vx-step-copy strong{display:block;font-size:10px;color:#334155}.dark .vx-step-copy strong{color:#e5e7eb}.vx-step-copy span{display:block;font-size:8px;color:#94a3b8;margin-top:1px}.vx-review-shell{display:grid;grid-template-columns:minmax(380px,.72fr) minmax(0,1.65fr);gap:14px;align-items:start}.vx-source{padding:10px;position:sticky;top:10px}.vx-source-title{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:800;color:#172036;padding:3px 2px 10px}.dark .vx-source-title{color:#fff}.vx-file-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:0 2px 10px}.vx-file-meta{display:flex;align-items:center;gap:9px;min-width:0}.vx-file-icon{width:30px;height:34px;border-radius:7px;background:#fee2e2;color:#dc2626;display:grid;place-items:center;font-weight:800;font-size:9px;flex:none}.vx-file-name{font-size:10px;font-weight:800;color:#1f2937;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.dark .vx-file-name{color:#f8fafc}.vx-file-sub{font-size:8px;color:#94a3b8;margin-top:1px}.vx-source-frame,.vx-source-img{width:100%;height:calc(100vh - 245px);min-height:620px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:9px}.vx-source-frame{border:1px solid #e5e7eb}.vx-source-img{display:block;object-fit:contain}.vx-source-fallback{min-height:420px;display:grid;place-items:center;text-align:center;color:#64748b;border:1px dashed #d1d5db;border-radius:9px}.vx-upload-more{margin-top:10px;border:1px dashed #c7d2fe;border-radius:10px;padding:14px;text-align:center;color:#64748b;font-size:9px;background:#fafaff}.vx-review{display:grid;gap:10px}.vx-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}.vx-stat{border:1px solid #edf0f4;background:#f8fafc;border-radius:11px;padding:10px 11px;display:flex;align-items:center;gap:9px}.dark .vx-stat{background:#1f2937;border-color:#334155}.vx-stat-icon{width:34px;height:34px;border-radius:999px;display:grid;place-items:center;font-size:13px;font-weight:900;background:#e0f2fe;color:#0369a1;flex:none}.vx-stat.green .vx-stat-icon{background:#dcfce7;color:#15803d}.vx-stat.gold .vx-stat-icon{background:#fef3c7;color:#a16207}.vx-stat.purple .vx-stat-icon{background:#f3e8ff;color:#7e22ce}.vx-stat.conf{background:#ecfdf5;border-color:#d1fae5}.vx-stat strong{display:block;font-size:17px;line-height:1;color:#0f172a}.dark .vx-stat strong{color:#fff}.vx-stat span{display:block;font-size:8px;color:#64748b;margin-top:3px}.vx-confidence-row{display:flex;justify-content:space-between;gap:6px;align-items:end}.vx-confidence-bar{height:5px;background:#d1fae5;border-radius:999px;overflow:hidden;margin-top:7px}.vx-confidence-bar>i{display:block;height:100%;background:#10b981;border-radius:999px}.vx-items-card{overflow:hidden}.vx-items-head{display:flex;align-items:end;justify-content:space-between;gap:12px;padding:12px 14px}.vx-items-head h2{font-size:14px;font-weight:800;color:#172036}.dark .vx-items-head h2{color:#fff}.vx-items-head p{margin-top:2px;font-size:8px;color:#94a3b8}.vx-mini-search{min-width:180px;border:1px solid #d8dee8;border-radius:8px;padding:7px 9px;font-size:9px;color:#94a3b8;background:#fff}.dark .vx-mini-search{background:#111827;border-color:#475569}.vx-table-head,.vx-item-row{display:grid;grid-template-columns:34px 1.7fr 68px 76px 84px 92px minmax(150px,1fr) 54px;gap:8px;align-items:center}.vx-table-head{padding:8px 12px;background:#f8fafc;border-top:1px solid #edf0f4;border-bottom:1px solid #edf0f4;font-size:8px;font-weight:800;color:#64748b}.dark .vx-table-head{background:#182130;border-color:#334155}.vx-item-row{padding:8px 12px;border-bottom:1px solid #edf0f4}.dark .vx-item-row{border-color:#2d3748}.vx-item-index{font-size:9px;color:#64748b;text-align:center}.vx-item-name{font-size:10px;font-weight:800;color:#1f2937;line-height:1.25}.dark .vx-item-name{color:#f8fafc}.vx-item-sub{font-size:8px;color:#94a3b8;margin-top:2px}.vx-input{width:100%;min-height:34px;border:1px solid #d5dbe5;border-radius:7px;padding:5px 7px;font-size:10px;background:#fff}.dark .vx-input{background:#0f172a;border-color:#475569;color:#fff}.vx-input:focus{outline:none;border-color:#8b5cf6;box-shadow:0 0 0 2px rgba(139,92,246,.1)}.vx-money{display:flex;align-items:center}.vx-money span{height:34px;display:grid;place-items:center;padding:0 7px;border:1px solid #d5dbe5;border-right:0;border-radius:7px 0 0 7px;background:#f8fafc;font-size:9px;color:#64748b}.vx-money .vx-input{border-radius:0 7px 7px 0}.vx-match{min-width:0}.vx-status-pill{display:inline-flex;align-items:center;gap:4px;border-radius:6px;padding:4px 7px;font-size:8px;font-weight:800;background:#dcfce7;color:#15803d}.vx-status-pill.new{background:#eff6ff;color:#2563eb}.vx-status-pill.review{background:#fff7ed;color:#c2410c}.vx-match-meta{font-size:8px;color:#64748b;margin-top:3px}.vx-alt{display:flex;flex-wrap:wrap;gap:4px;margin-top:5px}.vx-alt button{border:1px solid #d8dee8;border-radius:6px;padding:3px 5px;font-size:8px;background:#fff;color:#475569}.dark .vx-alt button{background:#111827;border-color:#475569;color:#e5e7eb}.vx-row-actions{display:flex;gap:4px;justify-content:flex-end}.vx-icon-btn{width:30px;height:30px;border:1px solid #d8dee8;border-radius:7px;background:#fff;display:grid;place-items:center;font-size:10px;font-weight:800}.dark .vx-icon-btn{background:#111827;border-color:#475569}.vx-row-extra{grid-column:2/-1;display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:end;padding-top:2px}.vx-label{display:block;font-size:7px;text-transform:uppercase;letter-spacing:.05em;font-weight:800;color:#94a3b8;margin-bottom:3px}.vx-remove{font-size:8px;font-weight:800;color:#dc2626;padding-bottom:8px}.vx-items-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px}.vx-totals{display:grid;grid-template-columns:repeat(3,1fr);gap:0;min-width:420px;border:1px solid #ddd6fe;background:#f5f3ff;border-radius:9px;padding:8px 12px}.vx-total{padding:0 14px;border-left:1px solid #ddd6fe}.vx-total:first-child{border-left:0}.vx-total span{display:block;font-size:8px;color:#64748b}.vx-total strong{display:block;font-size:15px;color:#111827;margin-top:2px}.vx-footer{position:sticky;bottom:8px;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;background:rgba(255,255,255,.96);backdrop-filter:blur(10px);border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 24px rgba(15,23,42,.08)}.dark .vx-footer{background:rgba(17,24,39,.96);border-color:#334155}.vx-footer-note{font-size:9px;color:#64748b}.vx-error{border-radius:10px;background:#fef2f2;color:#b91c1c;padding:10px;font-size:11px}.vx-section{padding:16px}.vx-upload{border:2px dashed #d8dee8;border-radius:12px;padding:26px 16px;text-align:center;background:#fafbfc}.vx-status{display:flex;gap:12px;align-items:flex-start;border-radius:12px;background:#f5f3ff;padding:13px}.vx-spin{width:30px;height:30px;border:3px solid #ddd6fe;border-top-color:#7c3aed;border-radius:50%;animation:vxspin 1s linear infinite;flex:none}@keyframes vxspin{to{transform:rotate(360deg)}}
@media(max-width:1300px){.vx-summary{grid-template-columns:repeat(3,1fr)}.vx-stat.conf{grid-column:span 1}.vx-table-head,.vx-item-row{grid-template-columns:28px 1.6fr 62px 70px 78px 86px minmax(130px,1fr) 46px}}
@media(max-width:1100px){.vx-review-shell{grid-template-columns:1fr}.vx-source{position:static}.vx-source-frame,.vx-source-img{height:520px;min-height:0}.vx-table-head{display:none}.vx-item-row{grid-template-columns:40px 1fr 1fr 1fr}.vx-item-row>div:nth-child(2){grid-column:2/-1}.vx-item-row>div:nth-child(7){grid-column:2/-1}.vx-row-actions{grid-column:4}.vx-row-extra{grid-column:2/-1}.vx-items-foot{align-items:stretch;flex-direction:column}.vx-totals{min-width:0}}
@media(max-width:640px){.vx-page-head{display:grid}.vx-actions{width:100%}.vx-actions .vx-btn{flex:1}.vx-stepper{grid-template-columns:1fr 1fr}.vx-step:nth-child(2):after{display:none}.vx-step-copy span{display:none}.vx-summary{grid-template-columns:1fr 1fr}.vx-stat.conf{grid-column:1/-1}.vx-item-row{grid-template-columns:34px 1fr 1fr}.vx-item-row>div:nth-child(2),.vx-item-row>div:nth-child(7),.vx-row-extra{grid-column:1/-1}.vx-row-actions{grid-column:auto}.vx-row-extra{grid-template-columns:1fr}.vx-items-head{display:grid}.vx-mini-search{min-width:0}.vx-totals{grid-template-columns:1fr}.vx-total{border-left:0;border-top:1px solid #ddd6fe;padding:7px 0}.vx-total:first-child{border-top:0}.vx-footer{display:grid}.vx-footer .vx-actions{display:grid;grid-template-columns:1fr 1fr}.vx-source-frame,.vx-source-img{height:390px}.vx-title{font-size:21px}}
</style>

<div class="vx-ai">
    <div class="vx-page-head">
        <div>
            <div class="vx-breadcrumb">Pallets &nbsp;›&nbsp; Import Manifest &nbsp;›&nbsp; #{{ $this->record->id }}</div>
            <div class="vx-title-row"><h1 class="vx-title">AI Manifest Import</h1><span class="vx-beta">BETA</span></div>
            <p class="vx-sub">Upload a supplier invoice or packing list and let AI extract the items. Review, edit, and add to your pallet.</p>
        </div>
        <div class="vx-actions">
            <a href="{{ $palletUrl }}" class="vx-btn">Back to Pallet</a>
            @if($stage === 'verify')<button wire:click="import" wire:loading.attr="disabled" class="vx-btn primary"><span wire:loading.remove wire:target="import">Add Items to Pallet →</span><span wire:loading wire:target="import">Adding…</span></button>@endif
        </div>
    </div>

    @php $keys=['upload','processing','verify','done']; $current=array_search($stage,$keys,true); @endphp
    <section class="vx-card vx-stepper">
        @foreach([
            ['Upload','Add PDF or image'],
            ['AI Processing','Extract items with AI'],
            ['Review & Edit','Verify and adjust'],
            ['Add to Pallet','Confirm and import']
        ] as $i=>$step)
            <div class="vx-step {{ $i < $current ? 'done' : ($i === $current ? 'active' : '') }}">
                <div class="vx-step-dot">{{ $i < $current ? '✓' : $i+1 }}</div>
                <div class="vx-step-copy"><strong>{{ $step[0] }}</strong><span>{{ $step[1] }}</span></div>
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
                <div class="vx-source-title">▣ Document Preview</div>
                <div class="vx-file-row">
                    <div class="vx-file-meta"><div class="vx-file-icon">PDF</div><div class="min-w-0"><div class="vx-file-name">{{ $sourceOriginalName ?: 'Uploaded manifest' }}</div><div class="vx-file-sub">Original supplier document</div></div></div>
                    <button wire:click="startOver" class="vx-btn">Replace File</button>
                </div>
                @if($sourceUrl && $previewablePdf)
                    <iframe class="vx-source-frame" src="{{ $sourceUrl }}#toolbar=1&navpanes=0" title="Manifest source document"></iframe>
                @elseif($sourceUrl && $previewableImage)
                    <a href="{{ $sourceUrl }}" target="_blank" rel="noopener"><img class="vx-source-img" src="{{ $sourceUrl }}" alt="Manifest source document" /></a>
                @elseif($sourceUrl)
                    <div class="vx-source-fallback"><div><div class="text-sm font-semibold">Preview unavailable for this file type.</div><a href="{{ $sourceUrl }}" target="_blank" rel="noopener" class="vx-btn primary mt-4">Open Source File</a></div></div>
                @else
                    <div class="vx-source-fallback">The original source file is unavailable for this older AI task.</div>
                @endif
                <div class="vx-upload-more">Drop another file here later, or use <strong>Replace File</strong> above to analyze a different document.</div>
            </section>

            <section class="vx-review">
                <div class="vx-summary">
                    <div class="vx-stat"><div class="vx-stat-icon">☷</div><div><strong>{{ count($parsedLines) }}</strong><span>Lines Found</span></div></div>
                    <div class="vx-stat green"><div class="vx-stat-icon">✓</div><div><strong>{{ $matchedCount }}</strong><span>Matched Items</span></div></div>
                    <div class="vx-stat gold"><div class="vx-stat-icon">＋</div><div><strong>{{ $newItemCount }}</strong><span>New Items</span></div></div>
                    <div class="vx-stat purple"><div class="vx-stat-icon">▣</div><div><strong>{{ count($parsedLines) - $reviewCount }}</strong><span>Ready to Import</span></div></div>
                    <div class="vx-stat conf"><div style="width:100%"><div class="vx-confidence-row"><div><span style="margin:0;color:#166534">AI Confidence</span><strong style="font-size:14px;margin-top:3px">{{ $avgConfidence >= 90 ? 'High' : ($avgConfidence >= 70 ? 'Medium' : 'Needs Review') }}</strong></div><strong style="font-size:11px">{{ $avgConfidence }}%</strong></div><div class="vx-confidence-bar"><i style="width:{{ min(100,max(0,$avgConfidence)) }}%"></i></div></div></div>
                </div>

                <section class="vx-card vx-items-card">
                    <div class="vx-items-head">
                        <div><h2>Parsed Items</h2><p>Review the items extracted from your document. Edit details or create new items as needed.</p></div>
                        <div class="vx-mini-search">⌕ &nbsp; Search items…</div>
                    </div>
                    <div class="vx-table-head"><div>#</div><div>Item Details</div><div>Cases</div><div>Units / Case</div><div>Unit Cost</div><div>Total Cost</div><div>Match Status</div><div>Actions</div></div>
                    @forelse($parsedLines as $i=>$line)
                        @php
                            $rowCost=(float)str_replace(['$',','],'',(string)($line['unit_cost']??0));
                            $rowTotal=max(0,(int)($line['case_count']??0))*max(1,(float)($line['quantity_per_case']??1))*$rowCost;
                            $isMatched=!empty($line['matched_item_id']) && empty($line['create_new_item']);
                            $conf=(float)($line['match_confidence_score']??0);
                        @endphp
                        <div class="vx-item-row" wire:key="manifest-line-{{ $i }}">
                            <div class="vx-item-index">{{ $i+1 }}</div>
                            <div><div class="vx-item-name">{{ $line['description'] ?: 'Untitled manifest item' }}</div><div class="vx-item-sub">{{ $isMatched ? 'Matched to: '.($line['matched_item_name'] ?? 'inventory item') : 'From supplier manifest' }}</div></div>
                            <div><input class="vx-input" type="number" min="1" wire:model="parsedLines.{{ $i }}.case_count" /></div>
                            <div><input class="vx-input" type="number" min="1" wire:model="parsedLines.{{ $i }}.quantity_per_case" /></div>
                            <div><div class="vx-money"><span>$</span><input class="vx-input" wire:model="parsedLines.{{ $i }}.unit_cost" /></div></div>
                            <div class="text-[10px] font-bold text-slate-700 dark:text-slate-200">${{ number_format($rowTotal,2) }}</div>
                            <div class="vx-match">
                                @if($isMatched)
                                    <span class="vx-status-pill">✓ Matched</span><div class="vx-match-meta">Confidence: {{ $conf >= .95 ? 'High' : ($conf >= .75 ? 'Medium' : 'Low') }}</div>
                                @elseif(!empty($line['create_new_item']))
                                    <span class="vx-status-pill new">＋ Create New</span><div class="vx-match-meta">No existing item selected</div>
                                @else
                                    <span class="vx-status-pill review">Review</span><div class="vx-match-meta">Needs inventory decision</div>
                                @endif
                                @if(!empty($line['alternatives']))<div class="vx-alt">@foreach(array_slice($line['alternatives'],0,3) as $alt)<button type="button" wire:click="chooseMatch({{ $i }}, {{ $alt['id'] }})">{{ $alt['name'] }}</button>@endforeach</div>@endif
                            </div>
                            <div class="vx-row-actions"><button type="button" class="vx-icon-btn" title="Create new" wire:click="chooseCreateNew({{ $i }})">＋</button><button type="button" class="vx-icon-btn" title="Remove line" wire:click="removeLine({{ $i }})">⋮</button></div>
                            <div class="vx-row-extra">
                                <div><label class="vx-label">Item Description</label><input class="vx-input" wire:model="parsedLines.{{ $i }}.description" /></div>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><div><label class="vx-label">SKU</label><input class="vx-input" wire:model="parsedLines.{{ $i }}.sku" /></div><div><label class="vx-label">Barcode</label><input class="vx-input" wire:model="parsedLines.{{ $i }}.barcode" /></div></div>
                                <button type="button" class="vx-remove" wire:click="removeLine({{ $i }})">Remove line</button>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-xs text-gray-500">No extracted lines were returned.</div>
                    @endforelse
                    <div class="vx-items-foot">
                        <button type="button" wire:click="addLine" class="vx-btn">＋ Add Manual Item</button>
                        <div class="vx-totals"><div class="vx-total"><span>Total Cases</span><strong>{{ number_format($totalCases) }}</strong></div><div class="vx-total"><span>Total Units</span><strong>{{ number_format($totalUnits) }}</strong></div><div class="vx-total"><span>Estimated Value</span><strong>${{ number_format($estimatedValue,2) }}</strong></div></div>
                    </div>
                </section>

                <div class="vx-footer"><div class="vx-footer-note">{{ $reviewCount }} item{{ $reviewCount===1?'':'s' }} still need review. Approving saves the manifest; physical receiving remains separate.</div><div class="vx-actions"><button wire:click="startOver" class="vx-btn">Start Over</button><button wire:click="import" wire:loading.attr="disabled" class="vx-btn primary"><span wire:loading.remove wire:target="import">Add Items to Pallet →</span><span wire:loading wire:target="import">Adding…</span></button></div></div>
            </section>
        </div>
    @endif

    @if($stage === 'done')
        <section class="vx-card vx-section text-center"><x-heroicon-o-check-circle class="mx-auto h-10 w-10 text-emerald-500" /><h2 class="mt-2 text-lg font-bold">Manifest approved</h2><p class="mt-1 text-xs text-gray-500">{{ $created }} lines processed · {{ $matched }} matched · {{ $unmatched }} unmatched.</p><a href="{{ $palletUrl }}" class="vx-btn primary mt-4">Return to Pallet</a></section>
    @endif
</div>
</x-filament-panels::page>