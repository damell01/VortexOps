<x-filament-panels::page>
@php
    $sim = $this->simulation();
    $productOptions = $this->productOptions();
    $streamerOptions = $this->streamerOptions();
@endphp
<style>
.vx-sim-wrap{max-width:1500px;margin:0 auto;display:grid;gap:14px}.vx-sim-card{border:1px solid #e5e7eb;background:#fff;border-radius:18px;box-shadow:0 1px 2px rgba(15,23,42,.04)}.dark .vx-sim-card{border-color:#334155;background:#111827}.vx-sim-pad{padding:18px}.vx-sim-btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;border-radius:12px;padding:8px 13px;font-size:11px;font-weight:800;border:1px solid #d1d5db;background:#fff;color:#374151}.dark .vx-sim-btn{background:#111827;border-color:#475569;color:#e5e7eb}.vx-sim-btn.primary{background:#7c3aed;border-color:#7c3aed;color:#fff}.vx-sim-btn.danger{color:#b91c1c;border-color:#fecaca}.vx-sim-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}.vx-sim-kpi{padding:12px;border-radius:14px;background:#f8fafc}.dark .vx-sim-kpi{background:#1f2937}.vx-sim-kpi label{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:#94a3b8}.vx-sim-kpi strong{display:block;margin-top:4px;font-size:19px}.vx-show-edit{border-top:1px solid #eef2f7;padding:16px}.dark .vx-show-edit{border-color:#263248}.vx-show-edit:first-child{border-top:0}.vx-fields{display:grid;grid-template-columns:1.4fr 140px 1.2fr repeat(4,110px);gap:8px}.vx-input{width:100%;min-height:40px;border:1px solid #d1d5db;border-radius:10px;padding:6px 8px;font-size:11px;background:#fff}.dark .vx-input{background:#111827;border-color:#475569;color:#fff}.vx-label{display:block;margin-bottom:3px;font-size:8px;text-transform:uppercase;letter-spacing:.06em;font-weight:800;color:#8b5cf6}.vx-product-row{display:grid;grid-template-columns:minmax(220px,1fr) 100px 80px;gap:8px;align-items:end;margin-top:8px}.vx-week-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.vx-week{border:1px solid #e5e7eb;border-radius:14px;padding:12px}.dark .vx-week{border-color:#374151}.vx-result-row{display:grid;grid-template-columns:minmax(0,1.4fr) repeat(4,minmax(90px,.45fr));gap:10px;padding:12px 0;border-top:1px solid #eef2f7;align-items:center}.dark .vx-result-row{border-color:#263248}.vx-note{font-size:10px;color:#64748b;line-height:1.45}.vx-pill{display:inline-flex;border-radius:999px;padding:4px 8px;background:#ede9fe;color:#6d28d9;font-size:9px;font-weight:800}.vx-safe{border:1px solid #c4b5fd;background:#faf5ff;color:#5b21b6;border-radius:14px;padding:12px;font-size:11px}.dark .vx-safe{background:#241338;color:#ddd6fe;border-color:#6d28d9}
@media(max-width:1100px){.vx-fields{grid-template-columns:1fr 1fr 1fr}.vx-sim-kpis{grid-template-columns:repeat(3,1fr)}.vx-week-grid{grid-template-columns:repeat(2,1fr)}.vx-result-row{grid-template-columns:1fr 1fr 1fr}.vx-result-row>div:first-child{grid-column:1/-1}}
@media(max-width:700px){.vx-sim-pad{padding:14px}.vx-fields{grid-template-columns:1fr 1fr}.vx-fields>div:first-child,.vx-fields>div:nth-child(3){grid-column:1/-1}.vx-product-row{grid-template-columns:1fr 90px}.vx-product-row>button{grid-column:1/-1}.vx-sim-kpis,.vx-week-grid{grid-template-columns:1fr 1fr}.vx-result-row{grid-template-columns:1fr 1fr}}
</style>

<div class="vx-sim-wrap">
    <section class="vx-sim-card vx-sim-pad">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-violet-600">Payroll Lab</div>
                <h1 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">Test payroll without touching production data</h1>
                <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500">Use real catalog costs and current streamer payment structures with completely mock shows. Switch products, revenue, hours, shipments, tips, or people and the projected weekly pay runs update instantly.</p>
            </div>
            <a href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}" class="vx-sim-btn">Back to Payroll</a>
        </div>
        <div class="vx-safe mt-4"><strong>Read-only sandbox.</strong> This page never creates Shows, Payouts, Pay Runs, Streamer Logs, inventory movements, or stock deductions.</div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button wire:click="loadPreset('single')" class="vx-sim-btn {{ $mode==='single'?'primary':'' }}">1 Show</button>
            <button wire:click="loadPreset('week')" class="vx-sim-btn {{ $mode==='week'?'primary':'' }}">1 Week</button>
            <button wire:click="loadPreset('month')" class="vx-sim-btn {{ $mode==='month'?'primary':'' }}">4-Week Month</button>
            <button wire:click="addShow" class="vx-sim-btn">+ Add Mock Show</button>
        </div>
    </section>

    <section class="vx-sim-card vx-sim-pad">
        <div class="vx-sim-kpis">
            <div class="vx-sim-kpi"><label>Mock Gross</label><strong>${{ number_format($sim['gross'],2) }}</strong></div>
            <div class="vx-sim-kpi"><label>Catalog COGS</label><strong>${{ number_format($sim['cogs'],2) }}</strong></div>
            <div class="vx-sim-kpi"><label>Configured Burden</label><strong>${{ number_format($sim['burden'],2) }}</strong></div>
            <div class="vx-sim-kpi"><label>Projected Payroll</label><strong class="text-violet-600">${{ number_format($sim['payroll'],2) }}</strong></div>
            <div class="vx-sim-kpi"><label>After COGS + Payroll</label><strong class="{{ $sim['business'] < 0 ? 'text-red-600':'text-emerald-600' }}">${{ number_format($sim['business'],2) }}</strong></div>
        </div>
    </section>

    <section class="vx-sim-card overflow-hidden">
        <div class="vx-sim-pad border-b border-gray-100 dark:border-gray-800">
            <h2 class="text-sm font-bold text-gray-950 dark:text-white">Mock Show Inputs</h2>
            <p class="mt-1 text-xs text-gray-500">The money inputs are fake. Product cost and streamer compensation settings are read live from the database.</p>
        </div>
        @foreach($shows as $i => $show)
            <div class="vx-show-edit" wire:key="mock-show-{{ $i }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="vx-fields flex-1">
                        <div><label class="vx-label">Show name</label><input class="vx-input" wire:model.live.debounce.300ms="shows.{{ $i }}.name" /></div>
                        <div><label class="vx-label">Date</label><input type="date" class="vx-input" wire:model.live="shows.{{ $i }}.date" /></div>
                        <div><label class="vx-label">Payment structure</label><select class="vx-input" wire:model.live="shows.{{ $i }}.streamer_id"><option value="">Choose streamer</option>@foreach($streamerOptions as $id=>$label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                        <div><label class="vx-label">Gross</label><input type="number" step="0.01" class="vx-input" wire:model.live.debounce.300ms="shows.{{ $i }}.gross" /></div>
                        <div><label class="vx-label">Hours</label><input type="number" step="0.25" class="vx-input" wire:model.live.debounce.300ms="shows.{{ $i }}.hours" /></div>
                        <div><label class="vx-label">Shipments</label><input type="number" class="vx-input" wire:model.live.debounce.300ms="shows.{{ $i }}.shipments" /></div>
                        <div><label class="vx-label">Tips</label><input type="number" step="0.01" class="vx-input" wire:model.live.debounce.300ms="shows.{{ $i }}.tips" /></div>
                    </div>
                    @if(count($shows)>1)<button wire:click="removeShow({{ $i }})" class="vx-sim-btn danger">Remove</button>@endif
                </div>
                <div class="mt-3 rounded-xl bg-gray-50 p-3 dark:bg-gray-800/70">
                    <div class="flex items-center justify-between gap-3"><div><div class="text-[9px] font-bold uppercase tracking-wide text-gray-500">Products used for mock COGS</div><div class="mt-0.5 text-[10px] text-gray-500">Changing these does not alter inventory.</div></div><button wire:click="addProduct({{ $i }})" class="vx-sim-btn">+ Product</button></div>
                    @foreach($show['products'] ?? [] as $p => $row)
                        <div class="vx-product-row" wire:key="mock-show-{{ $i }}-product-{{ $p }}">
                            <div><label class="vx-label">Catalog item</label><select class="vx-input" wire:model.live="shows.{{ $i }}.products.{{ $p }}.product_id">@foreach($productOptions as $id=>$label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                            <div><label class="vx-label">Qty</label><input type="number" min="0" step="1" class="vx-input" wire:model.live.debounce.300ms="shows.{{ $i }}.products.{{ $p }}.quantity" /></div>
                            <button wire:click="removeProduct({{ $i }},{{ $p }})" class="vx-sim-btn danger">Remove</button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>

    <section class="vx-sim-card vx-sim-pad">
        <div class="flex items-center justify-between gap-3"><div><h2 class="text-sm font-bold text-gray-950 dark:text-white">Projected Weekly Pay Runs</h2><p class="mt-1 text-xs text-gray-500">The month view automatically groups mock shows into weekly payroll totals.</p></div><span class="vx-pill">{{ $sim['weeks']->count() }} week{{ $sim['weeks']->count()===1?'':'s' }}</span></div>
        <div class="vx-week-grid mt-3">
            @foreach($sim['weeks'] as $week)
                <div class="vx-week">
                    <div class="text-xs font-bold text-gray-950 dark:text-white">{{ $week['label'] }}</div>
                    <div class="mt-1 text-[10px] text-gray-500">{{ $week['shows'] }} mock show{{ $week['shows']===1?'':'s' }}</div>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs"><div><span class="text-gray-400">Gross</span><div class="font-bold">${{ number_format($week['gross'],0) }}</div></div><div><span class="text-gray-400">COGS</span><div class="font-bold">${{ number_format($week['cogs'],0) }}</div></div><div><span class="text-gray-400">Payroll</span><div class="font-bold text-violet-600">${{ number_format($week['payroll'],2) }}</div></div><div><span class="text-gray-400">Business</span><div class="font-bold {{ $week['business']<0?'text-red-600':'text-emerald-600' }}">${{ number_format($week['business'],0) }}</div></div></div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="vx-sim-card vx-sim-pad">
        <h2 class="text-sm font-bold text-gray-950 dark:text-white">Show-by-Show Calculation Audit</h2>
        <p class="mt-1 text-xs text-gray-500">Use this to verify the exact numbers and compare different real payment structures against the same mock economics.</p>
        <div class="mt-3">
            @foreach($sim['rows'] as $row)
                <div class="vx-result-row">
                    <div><div class="flex flex-wrap items-center gap-2"><strong class="text-sm text-gray-950 dark:text-white">{{ $row['name'] }}</strong><span class="vx-pill">{{ $row['payout_type'] }}</span></div><div class="mt-1 text-[10px] text-gray-500">{{ $row['date']->format('M j, Y') }} · {{ $row['streamer']?->name ?? 'No streamer' }} · {{ $row['products']->count() }} product line(s)</div><div class="vx-note mt-2">{{ $row['note'] }}</div></div>
                    <div><div class="text-[9px] uppercase text-gray-400">Gross</div><div class="font-bold">${{ number_format($row['gross'],2) }}</div></div>
                    <div><div class="text-[9px] uppercase text-gray-400">COGS</div><div class="font-bold">${{ number_format($row['product_cost'],2) }}</div></div>
                    <div><div class="text-[9px] uppercase text-gray-400">Payout</div><div class="font-bold text-violet-600">${{ number_format($row['payout'],2) }}</div></div>
                    <div><div class="text-[9px] uppercase text-gray-400">After payroll</div><div class="font-bold {{ $row['business_after_payroll']<0?'text-red-600':'text-emerald-600' }}">${{ number_format($row['business_after_payroll'],2) }}</div></div>
                </div>
            @endforeach
        </div>
    </section>
</div>
</x-filament-panels::page>
