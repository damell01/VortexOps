<x-filament-panels::page>
@php
    $badge = [
        'create'    => ['New item',   'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300'],
        'update'    => ['Update',     'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'],
        'unchanged' => ['No change',  'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'],
        'skip'      => ['Not importing', 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'],
    ];
    $input = 'w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white';
@endphp

<div class="space-y-5">
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">1</span>
            <div class="min-w-0 flex-1">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Choose the sheet</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload the whole Streamer Log workbook (Google Sheets → File → Download → Microsoft Excel .xlsx) — the <b>Product cost ref sheet</b> tab is picked for you. A CSV download only holds the tab that was open. The name column may have a blank header; SKU, Type, Auction or BIN?, Cost and Sale price / Target are read when present.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-200">Spreadsheet</label>
                        <input type="file" wire:model="upload" accept=".xlsx,.xls,.csv"
                               class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-500 dark:text-gray-300">
                        <div wire:loading wire:target="upload" class="mt-2 text-xs text-primary-600 dark:text-primary-400">Reading the file…</div>
                        @error('upload') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if ($sheets !== [])
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-200">Worksheet</label>
                            <select wire:model.live="sheet" class="min-h-10 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                @foreach ($sheets as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach
                            </select>
                            <p class="mt-1.5 text-[11px] text-gray-400">{{ $fileName }}</p>
                        </div>
                    @endif
                </div>

                @if ($storedPath)
                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <input type="checkbox" wire:model.live="overwrite" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600">
                            Replace costs and targets that already have a value
                        </label>
                        <button type="button" wire:click="startOver" class="vx-plain text-sm font-medium text-gray-500 hover:underline dark:text-gray-400">Start over</button>
                    </div>
                    <p class="mt-1.5 text-[11px] leading-5 text-gray-400">Off by default. Re-imports fill blanks without overwriting warehouse-corrected values unless you turn this on.</p>
                @endif
            </div>
        </div>
    </section>

    @if ($error)
        <x-guide.panel tone="amber" title="That sheet could not be read"><p>{{ $error }}</p></x-guide.panel>
    @endif

    @if ($rows !== [])
        <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-start gap-3 border-b border-gray-200 p-5 dark:border-gray-700">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">2</span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Preview before import</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Review what is new, what already exists, the sheet cost/target, and the expected margin. Fix any row with <b>Edit</b>, or set it to <b>Don't import</b>. Rows that look like something already in the catalogue start as <b>Don't import</b> — choose to update the existing item, import as new, or leave it out. Nothing is written yet.</p>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        <div class="rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-500/20 dark:bg-green-500/10"><div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ $summary['create'] }}</div><div class="text-[11px] font-semibold uppercase tracking-wide text-green-700/70 dark:text-green-300/70">New items</div></div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-500/20 dark:bg-amber-500/10"><div class="text-2xl font-bold text-amber-700 dark:text-amber-300">{{ $summary['update'] }}</div><div class="text-[11px] font-semibold uppercase tracking-wide text-amber-700/70 dark:text-amber-300/70">Updates</div></div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50"><div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $summary['unchanged'] }}</div><div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Existing / no change</div></div>
                        <div class="rounded-lg border p-3 {{ $summary['warnings'] ? 'border-red-200 bg-red-50 dark:border-red-500/20 dark:bg-red-500/10' : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50' }}"><div class="text-2xl font-bold {{ $summary['warnings'] ? 'text-red-700 dark:text-red-300' : 'text-gray-700 dark:text-gray-300' }}">{{ $summary['warnings'] }}</div><div class="text-[11px] font-semibold uppercase tracking-wide {{ $summary['warnings'] ? 'text-red-700/70 dark:text-red-300/70' : 'text-gray-500' }}">Need a look</div></div>
                        <div class="rounded-lg border p-3 {{ $summary['duplicates'] ? 'border-violet-200 bg-violet-50 dark:border-violet-500/20 dark:bg-violet-500/10' : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50' }}"><div class="text-2xl font-bold {{ $summary['duplicates'] ? 'text-violet-700 dark:text-violet-300' : 'text-gray-700 dark:text-gray-300' }}">{{ $summary['duplicates'] }}</div><div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Possible duplicates</div></div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50"><div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $summary['skipped'] }}</div><div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Not importing</div></div>
                    </div>
                </div>
            </div>

            <div class="flex gap-1.5 overflow-x-auto border-b border-gray-200 p-3 dark:border-gray-700">
                @foreach ([
                    'all'       => ['Everything', $summary['total']],
                    'create'    => ['New items',  $summary['create']],
                    'update'    => ['Updates',    $summary['update']],
                    'unchanged' => ['Existing / no change',  $summary['unchanged']],
                    'warnings'  => ['Need a look', $summary['warnings']],
                    'duplicates' => ['Possible duplicates', $summary['duplicates']],
                    'skip'      => ['Not importing', $summary['skipped']],
                ] as $key => [$label, $count])
                    <button type="button" wire:click="setFilter('{{ $key }}')" wire:key="import-filter-{{ $key }}"
                            class="vx-plain flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors {{ $filter === $key ? 'bg-primary-600 text-white' : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800' }}">
                        {{ $label }}
                        <span class="rounded px-1 text-[11px] {{ $filter === $key ? 'bg-white/20' : 'bg-gray-100 dark:bg-gray-800' }}">{{ $count }}</span>
                    </button>
                @endforeach
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-sm">
                    <thead class="bg-gray-50 text-left text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2.5">Row</th>
                            <th class="px-4 py-2.5">Product</th>
                            <th class="px-4 py-2.5">Cost</th>
                            <th class="px-4 py-2.5">Target</th>
                            <th class="px-4 py-2.5">Margin</th>
                            <th class="px-4 py-2.5">What happens</th>
                            <th class="px-4 py-2.5">Changes</th>
                            <th class="px-4 py-2.5">Import?</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($this->visibleRows as $row)
                            @php [$label, $classes] = $badge[$row['action']]; @endphp
                            <tr wire:key="import-row-{{ $row['line'] }}-{{ $row['action'] }}-{{ $row['decision'] }}" class="{{ $row['action'] === 'skip' ? 'opacity-60' : '' }} {{ $row['warnings'] ? 'bg-red-50/50 dark:bg-red-500/5' : ($row['duplicate'] ? 'bg-violet-50/50 dark:bg-violet-500/5' : '') }}">
                                <td class="px-4 py-3 align-top text-xs text-gray-400">{{ $row['line'] }}</td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $row['name'] }}@if($row['edited'])<span class="ml-1.5 rounded bg-primary-100 px-1.5 py-0.5 text-[10px] font-semibold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">edited</span>@endif</div>
                                    @if ($row['sku'])<div class="text-xs text-gray-400">{{ $row['sku'] }}</div>@endif
                                    @foreach ($row['warnings'] as $warning)<div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $warning }}</div>@endforeach
                                    @foreach ($row['similar'] as $similar)<div class="mt-1 text-xs text-violet-700 dark:text-violet-300">Looks like existing: <b>{{ $similar['name'] }}</b>@if($similar['sku']) ({{ $similar['sku'] }})@endif</div>@endforeach
                                    <div class="mt-1.5 flex gap-3">
                                        <button type="button" wire:click="startEdit({{ $row['line'] }})" class="vx-plain text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">Edit</button>
                                        @if($row['edited'])<button type="button" wire:click="undoEdit({{ $row['line'] }})" class="vx-plain text-xs font-medium text-gray-500 hover:underline">Undo edit</button>@endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top font-semibold">{{ $row['sheet_cost'] !== null ? '$'.number_format($row['sheet_cost'],2) : '—' }}</td>
                                <td class="px-4 py-3 align-top font-semibold">{{ $row['sheet_target'] !== null ? '$'.number_format($row['sheet_target'],2) : '—' }}</td>
                                <td class="px-4 py-3 align-top">@if($row['sheet_margin'] !== null)<div class="font-semibold {{ $row['sheet_margin'] < 0 ? 'text-red-600' : 'text-emerald-600' }}">${{ number_format($row['sheet_margin'],2) }}</div><div class="text-[10px] text-gray-400">{{ $row['sheet_margin_pct'] !== null ? number_format($row['sheet_margin_pct'],1).'%' : '' }}</div>@else — @endif</td>
                                <td class="px-4 py-3 align-top"><span class="inline-flex rounded-md px-2 py-0.5 text-xs font-semibold {{ $classes }}">{{ $label }}</span>@if ($row['match'] && $row['action'] !== 'skip')<div class="mt-1 text-xs text-gray-500 dark:text-gray-400">matched on {{ $row['matched_by'] }} → {{ $row['match']['name'] }}</div>@endif</td>
                                <td class="px-4 py-3 align-top">@if ($row['changes'] === [] || $row['action'] === 'skip')<span class="text-xs text-gray-400">—</span>@else<ul class="space-y-0.5">@foreach ($row['changes'] as $change)<li class="text-xs text-gray-600 dark:text-gray-300"><span class="font-medium">{{ $change['field'] }}</span> @if ($change['from'] !== null)<span class="text-gray-400">{{ $change['from'] }} →</span>@endif {{ $change['to'] }}</li>@endforeach</ul>@endif</td>
                                <td class="px-4 py-3 align-top">
                                    <select aria-label="Import row {{ $row['line'] }}?" wire:change="decide({{ $row['line'] }}, $event.target.value)" class="vxw-select" style="min-width:12rem;font-size:12.5px">
                                        <option value="import" @selected($row['decision'] === 'import')>{{ $row['similar'] !== [] ? 'Import as a new item' : 'Import' }}</option>
                                        @if ($row['similar'] !== [] || $row['decision'] === 'new')<option value="new" @selected($row['decision'] === 'new')>New item — not a duplicate</option>@endif
                                        @foreach ($row['similar'] as $similar)<option value="match:{{ $similar['id'] }}" @selected($row['decision'] === 'match:'.$similar['id'])>Update “{{ \Illuminate\Support\Str::limit($similar['name'], 28) }}”</option>@endforeach
                                        @if (str_starts_with($row['decision'], 'match:') && $row['match'])<option value="{{ $row['decision'] }}" selected>Update “{{ \Illuminate\Support\Str::limit($row['match']['name'], 28) }}”</option>@endif
                                        <option value="skip" @selected($row['decision'] === 'skip')>Don't import</option>
                                    </select>
                                </td>
                            </tr>
                            @if ($editingLine === $row['line'])
                                <tr wire:key="import-edit-{{ $row['line'] }}" class="bg-primary-50/40 dark:bg-primary-500/5">
                                    <td></td>
                                    <td colspan="7" class="px-4 py-3">
                                        <form wire:submit="saveEdit" style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
                                            <label class="vxw-field" style="grid-column:span 2"><span class="vxw-label">Product name</span><input wire:model="editForm.name" type="text" class="vxw-input" required>@error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                                            <label class="vxw-field"><span class="vxw-label">SKU</span><input wire:model="editForm.sku" type="text" class="vxw-input"></label>
                                            <label class="vxw-field"><span class="vxw-label">Type</span><input wire:model="editForm.type" type="text" list="import-types" class="vxw-input"></label>
                                            <label class="vxw-field"><span class="vxw-label">Auction or BIN?</span><select wire:model="editForm.sold_as" class="vxw-select"><option value="">—</option>@foreach (\App\Models\Product::soldAsOptions() + array_filter([($editForm['sold_as'] ?? '') => $editForm['sold_as'] ?? null]) as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach</select></label>
                                            <label class="vxw-field"><span class="vxw-label">Cost</span><input wire:model="editForm.cost" type="number" step="0.01" min="0" class="vxw-input">@error('cost')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                                            <label class="vxw-field"><span class="vxw-label">Target</span><input wire:model="editForm.sale_price" type="number" step="0.01" min="0" class="vxw-input">@error('sale_price')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                                            <div class="flex flex-wrap items-center gap-2" style="grid-column:1/-1">
                                                <button type="submit" class="vxw-btn vxw-btn--primary vxw-btn--sm">Save row</button>
                                                <button type="button" wire:click="cancelEdit" class="vxw-btn vxw-btn--ghost vxw-btn--sm">Cancel</button>
                                                <span class="text-xs text-gray-400">Changes apply to this import only — the spreadsheet itself is not changed.</span>
                                            </div>
                                        </form>
                                        <datalist id="import-types"><option value="box"><option value="pack"><option value="single"><option value="case"></datalist>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                        @if ($this->visibleRows === [])<tr><td colspan="8" class="px-4 py-8 text-center text-sm text-gray-400">Nothing in this group.</td></tr>@endif
                    </tbody>
                </table>
            </div>

            @if ($this->hiddenRowCount > 0)<div class="border-t border-gray-200 px-4 py-2.5 text-xs text-gray-400 dark:border-gray-700">… and {{ $this->hiddenRowCount }} more rows not shown. They are included in the import as they stand — filter to find them.</div>@endif
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">3</span><div><h2 class="text-base font-semibold text-gray-950 dark:text-white">Import it</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Writes {{ $summary['create'] + $summary['update'] }} rows that actually change something. Existing no-change rows are left alone@if($summary['skipped']), and the {{ $summary['skipped'] }} marked “Don't import” are left out@endif.</p></div></div>
                <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import" class="vx-plain shrink-0 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-60"><span wire:loading.remove wire:target="import">Import {{ $summary['create'] + $summary['update'] }} rows</span><span wire:loading wire:target="import">Importing…</span></button>
            </div>
            @if ($result)<div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-500/20 dark:bg-green-500/10 dark:text-green-300">Done — <b>{{ $result['created'] }}</b> created, <b>{{ $result['updated'] }}</b> updated, <b>{{ $result['unchanged'] }}</b> already matched.</div>@endif
        </section>
    @endif
</div>
</x-filament-panels::page>
