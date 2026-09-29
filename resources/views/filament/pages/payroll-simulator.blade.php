<x-filament-panels::page>
@php
    $sim = $this->simulation();
    $productOptions = $this->productOptions();
    $streamerOptions = $this->streamerOptions();
@endphp

<div class="space-y-6">
    <section class="vx-card p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-violet-600">Payroll Simulator</div>
                <h1 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">Test a weekly pay run without touching real data</h1>
                <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500">Shows are inputs. Payroll is calculated at the <b>weekly pay-run level</b>: all shows for the week are grouped, each streamer’s weekly totals are combined, and then the standard team formula or that person’s adjustment is applied.</p>
            </div>
            <a href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}" class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold">Back to Payroll</a>
        </div>
        <div class="vx-safe mt-4"><strong>Safe sandbox.</strong> No Shows, Streamer Logs, Pay Runs, payouts, inventory movements, or stock quantities are created or changed.</div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button wire:click="loadPreset('single')" class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold {{ $mode==='single'?'primary':'' }}">1 Show</button>
            <button wire:click="loadPreset('week')" class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold {{ $mode==='week'?'primary':'' }}">1 Week</button>
            <button wire:click="loadPreset('month')" class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold {{ $mode==='month'?'primary':'' }}">4-Week Month</button>
            <button wire:click="addShow" class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold">+ Add Mock Show</button>
        </div>
    </section>

    <section class="vx-card p-5">
        <div class="flex items-start justify-between gap-4">
            <div><h2 class="text-sm font-bold text-gray-950 dark:text-white">How the weekly calculation works</h2><p class="mt-1 text-xs text-gray-500">This follows the spreadsheet logic, but combines the week before calculating pay.</p></div><span class="vx-status vx-status--draft">Weekly cadence</span>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-4 text-xs">
            <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800/70"><div class="font-bold">1. Combine the week</div><div class="mt-1 text-gray-500">Add Gross, Product Cost, Hours, Shipments and Tips from all shows assigned to that streamer during the week.</div></div>
            <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800/70"><div class="font-bold">2. Calculate burden</div><div class="mt-1 text-gray-500">Burden = weekly shipments × shipment rate + weekly hours × hourly burden rate.</div></div>
            <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800/70"><div class="font-bold">3. Calculate weekly net</div><div class="mt-1 text-gray-500">Weekly Net = Gross − Product Cost − Burden.</div></div>
            <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800/70"><div class="font-bold">4. Calculate streamer pay</div><div class="mt-1 text-gray-500">Streamer Pay = Weekly Net × configured pay % + weekly tips when tips are included.</div></div>
        </div>
    </section>

    <section class="vx-card p-5">
        <h2 class="text-sm font-bold text-gray-950 dark:text-white">Scenario Summary</h2>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 mt-3">
            <div class="rounded-[10px] border border-[var(--vx-divider)] p-4"><label>Mock Gross</label><strong>${{ number_format($sim['gross'],2) }}</strong></div>
            <div class="rounded-[10px] border border-[var(--vx-divider)] p-4"><label>Product Cost</label><strong>${{ number_format($sim['cogs'],2) }}</strong></div>
            <div class="rounded-[10px] border border-[var(--vx-divider)] p-4"><label>Operating Burden</label><strong>${{ number_format($sim['burden'],2) }}</strong></div>
            <div class="rounded-[10px] border border-[var(--vx-divider)] p-4"><label>Total Streamer Pay</label><strong class="text-violet-600">${{ number_format($sim['streamer_pay'],2) }}</strong></div>
            <div class="rounded-[10px] border border-[var(--vx-divider)] p-4"><label>Business After Pay</label><strong class="{{ $sim['business'] < 0 ? 'text-red-600':'text-emerald-600' }}">${{ number_format($sim['business'],2) }}</strong></div>
        </div>
    </section>

    <section class="vx-card p-5">
        <div><h2 class="text-sm font-bold text-gray-950 dark:text-white">Projected Weekly Pay Runs</h2><p class="mt-1 text-xs text-gray-500">Each card is one weekly pay run. Streamer pay is not calculated show-by-show.</p></div>
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            @foreach($sim['weeks'] as $week)
                <div class="vx-week">
                    <div class="flex items-start justify-between gap-3"><div><div class="text-sm font-bold text-gray-950 dark:text-white">{{ $week['label'] }}</div><div class="mt-1 text-[10px] text-gray-500">{{ $week['shows'] }} shows · {{ $week['people'] }} streamer{{ $week['people']===1?'':'s' }}</div></div><div class="text-right"><div class="text-[9px] uppercase text-gray-400">Weekly Payroll</div><div class="text-lg font-bold text-violet-600">${{ number_format($week['streamer_pay'],2) }}</div></div></div>
                    <div class="mt-3 grid grid-cols-4 gap-2 text-xs"><div><span class="text-gray-400">Gross</span><div class="font-bold">${{ number_format($week['gross'],0) }}</div></div><div><span class="text-gray-400">Product Cost</span><div class="font-bold">${{ number_format($week['cogs'],0) }}</div></div><div><span class="text-gray-400">Burden</span><div class="font-bold">${{ number_format($week['burden'],0) }}</div></div><div><span class="text-gray-400">Business</span><div class="font-bold {{ $week['business']<0?'text-red-600':'text-emerald-600' }}">${{ number_format($week['business'],0) }}</div></div></div>
                    <div class="mt-4 space-y-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                        @foreach($week['payouts'] as $payout)
                            <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800/70">
                                <div class="flex items-start justify-between gap-3"><div><div class="font-bold text-sm">{{ $payout['name'] }}</div><div class="mt-1 text-[10px] text-gray-500">{{ $payout['shows'] }} show{{ $payout['shows']===1?'':'s' }} · {{ number_format($payout['pay_rate'],2) }}% {{ $payout['using_custom']?'individual adjustment':'team default' }}</div></div><div class="text-right"><div class="text-[9px] uppercase text-gray-400">Streamer Pay</div><div class="font-bold text-violet-600">${{ number_format($payout['streamer_pay'],2) }}</div></div></div>
                                <div class="mt-2 text-[10px] leading-4 text-gray-500">{{ $payout['note'] }}</div>
                                <div class="mt-2 text-[10px] text-gray-400">Shows: {{ $payout['show_names']->implode(', ') }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="vx-card p-5">
        <h2 class="text-sm font-bold text-gray-950 dark:text-white">Pay by Streamer</h2>
        <p class="mt-1 text-xs text-gray-500">Total across the selected simulator period. The actual pay is still broken into weekly pay runs above.</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($sim['people'] as $person)
                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700"><div class="font-bold text-sm">{{ $person['name'] }}</div><div class="mt-1 text-[10px] text-gray-500">{{ $person['shows'] }} shows across {{ $person['weeks'] }} week{{ $person['weeks']===1?'':'s' }}</div><div class="mt-2 text-xl font-bold text-violet-600">${{ number_format($person['pay'],2) }}</div></div>
            @endforeach
        </div>
    </section>

    <section class="vx-card overflow-hidden">
        <details class="vx-details" open>
            <summary class="vx-pad flex items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800"><div><h2 class="text-sm font-bold text-gray-950 dark:text-white">Mock Show Inputs</h2><p class="mt-1 text-xs text-gray-500">These are only building blocks for each weekly pay run. Edit anything to test a different scenario.</p></div><span class="vx-chevron text-gray-400 transition">⌄</span></summary>
            @foreach($shows as $i => $show)
                <div class="border-b border-gray-100 p-4 last:border-b-0 dark:border-gray-800" wire:key="mock-show-{{ $i }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="vx-fields flex-1">
                            <div><label class="mb-1 block text-xs font-semibold text-[var(--vx-muted)]">Show</label><input class="min-h-11 w-full rounded-[10px] border border-[var(--vx-border)] bg-[var(--vx-surface)] px-3 text-sm" wire:model.live.debounce.300ms="shows.{{ $i }}.name" /></div>
                            <div><label class="mb-1 block text-xs font-semibold text-[var(--vx-muted)]">Date</label><input type="date" class="min-h-11 w-full rounded-[10px] border border-[var(--vx-border)] bg-[var(--vx-surface)] px-3 text-sm" wire:model.live="shows.{{ $i }}.date" /></div>
                            <div><label class="mb-1 block text-xs font-semibold text-[var(--vx-muted)]">Streamer</label><select class="min-h-11 w-full rounded-[10px] border border-[var(--vx-border)] bg-[var(--vx-surface)] px-3 text-sm" wire:model.live="shows.{{ $i }}.streamer_id"><option value="">Choose streamer</option>@foreach($streamerOptions as $id=>$label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                            <div><label class="mb-1 block text-xs font-semibold text-[var(--vx-muted)]">Gross</label><input type="number" step="0.01" class="min-h-11 w-full rounded-[10px] border border-[var(--vx-border)] bg-[var(--vx-surface)] px-3 text-sm" wire:model.live.debounce.300ms="shows.{{ $i }}.gross" /></div>
                            <div><label class="mb-1 block text-xs font-semibold text-[var(--vx-muted)]">Hours</label><input type="number" step="0.25" class="min-h-11 w-full rounded-[10px] border border-[var(--vx-border)] bg-[var(--vx-surface)] px-3 text-sm" wire:model.live.debounce.300ms="shows.{{ $i }}.hours" /></div>
                            <div><label class="mb-1 block text-xs font-semibold text-[var(--vx-muted)]">Shipments</label><input type="number" class="min-h-11 w-full rounded-[10px] border border-[var(--vx-border)] bg-[var(--vx-surface)] px-3 text-sm" wire:model.live.debounce.300ms="shows.{{ $i }}.shipments" /></div>
                            <div><label class="mb-1 block text-xs font-semibold text-[var(--vx-muted)]">Tips</label><input type="number" step="0.01" class="min-h-11 w-full rounded-[10px] border border-[var(--vx-border)] bg-[var(--vx-surface)] px-3 text-sm" wire:model.live.debounce.300ms="shows.{{ $i }}.tips" /></div>
                        </div>
                        @if(count($shows)>1)<button wire:click="removeShow({{ $i }})" class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold danger">Remove</button>@endif
                    </div>
                    <div class="mt-3 rounded-xl bg-gray-50 p-3 dark:bg-gray-800/70">
                        <div class="flex items-center justify-between gap-3"><div><div class="text-[9px] font-bold uppercase tracking-wide text-gray-500">Mock products</div><div class="mt-0.5 text-[10px] text-gray-500">Used only for product cost. Inventory is never changed.</div></div><button wire:click="addProduct({{ $i }})" class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold">+ Product</button></div>
                        @foreach($show['products'] ?? [] as $p => $row)
                            <div class="vx-product-row" wire:key="mock-show-{{ $i }}-product-{{ $p }}"><div><label class="mb-1 block text-xs font-semibold text-[var(--vx-muted)]">Catalog item</label><select class="min-h-11 w-full rounded-[10px] border border-[var(--vx-border)] bg-[var(--vx-surface)] px-3 text-sm" wire:model.live="shows.{{ $i }}.products.{{ $p }}.product_id">@foreach($productOptions as $id=>$label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div><div><label class="mb-1 block text-xs font-semibold text-[var(--vx-muted)]">Qty</label><input type="number" min="0" step="1" class="min-h-11 w-full rounded-[10px] border border-[var(--vx-border)] bg-[var(--vx-surface)] px-3 text-sm" wire:model.live.debounce.300ms="shows.{{ $i }}.products.{{ $p }}.quantity" /></div><button wire:click="removeProduct({{ $i }},{{ $p }})" class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold danger">Remove</button></div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </details>
    </section>
</div>
</x-filament-panels::page>
