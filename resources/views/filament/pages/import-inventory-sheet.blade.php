<x-filament-panels::page>
@php
    $badge = [
        'create'    => ['New item',   'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300'],
        'update'    => ['Update',     'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'],
        'unchanged' => ['No change',  'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'],
    ];
@endphp

<div class="space-y-5">

    {{-- ── Step 1: the file ────────────────────────────────────────────── --}}
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">1</span>
            <div class="min-w-0 flex-1">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Choose the sheet</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    An .xlsx, .xls or .csv with a <b>PRODUCT NAME</b> column. SKU, Type, Cost and
                    Sale price / Target are read when they are there, wherever they sit.
                </p>

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
                            <select wire:model.live="sheet"
                                    class="min-h-10 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                @foreach ($sheets as $name)
                                    <option value="{{ $name }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-[11px] text-gray-400">{{ $fileName }}</p>
                        </div>
                    @endif
                </div>

                @if ($storedPath)
                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <input type="checkbox" wire:model.live="overwrite"
                                   class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600">
                            Replace costs and targets that already have a value
                        </label>
                        <button type="button" wire:click="startOver"
                                class="vx-plain text-sm font-medium text-gray-500 hover:underline dark:text-gray-400">
                            Start over
                        </button>
                    </div>
                    <p class="mt-1.5 text-[11px] leading-5 text-gray-400">
                        Off by default. The sheet is a starting point, not the authority — leaving this off
                        means a re-import fills in blanks and never overwrites a number the warehouse corrected.
                    </p>
                @endif
            </div>
        </div>
    </section>

    @if ($error)
        <x-guide.panel tone="amber" title="That sheet could not be read">
            <p>{{ $error }}</p>
        </x-guide.panel>
    @endif

    {{-- ── Step 2: the review ──────────────────────────────────────────── --}}
    @if ($rows !== [])
        <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-start gap-3 border-b border-gray-200 p-5 dark:border-gray-700">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">2</span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">What this would do</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Every row in the sheet, matched against the catalogue. Nothing has been written yet.
                    </p>

                    <div class="mt-4 grid gap-3 sm:grid-cols-4">
                        <div class="rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-500/20 dark:bg-green-500/10">
                            <div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ $summary['create'] }}</div>
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-green-700/70 dark:text-green-300/70">New items</div>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-500/20 dark:bg-amber-500/10">
                            <div class="text-2xl font-bold text-amber-700 dark:text-amber-300">{{ $summary['update'] }}</div>
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-700/70 dark:text-amber-300/70">Updated</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50">
                            <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $summary['unchanged'] }}</div>
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Already matched</div>
                        </div>
                        <div class="rounded-lg border p-3 {{ $summary['warnings'] ? 'border-red-200 bg-red-50 dark:border-red-500/20 dark:bg-red-500/10' : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50' }}">
                            <div class="text-2xl font-bold {{ $summary['warnings'] ? 'text-red-700 dark:text-red-300' : 'text-gray-700 dark:text-gray-300' }}">{{ $summary['warnings'] }}</div>
                            <div class="text-[11px] font-semibold uppercase tracking-wide {{ $summary['warnings'] ? 'text-red-700/70 dark:text-red-300/70' : 'text-gray-500' }}">Need a look</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filters --}}
            <div class="flex gap-1.5 overflow-x-auto border-b border-gray-200 p-3 dark:border-gray-700">
                @foreach ([
                    'all'       => ['Everything', $summary['total']],
                    'create'    => ['New items',  $summary['create']],
                    'update'    => ['Updates',    $summary['update']],
                    'unchanged' => ['No change',  $summary['unchanged']],
                    'warnings'  => ['Need a look', $summary['warnings']],
                ] as $key => [$label, $count])
                    <button type="button" wire:click="setFilter('{{ $key }}')"
                            class="vx-plain flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors
                                {{ $filter === $key ? 'bg-primary-600 text-white' : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800' }}">
                        {{ $label }}
                        <span class="rounded px-1 text-[11px] {{ $filter === $key ? 'bg-white/20' : 'bg-gray-100 dark:bg-gray-800' }}">{{ $count }}</span>
                    </button>
                @endforeach
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2.5 font-bold">Row</th>
                            <th class="px-4 py-2.5 font-bold">Product</th>
                            <th class="px-4 py-2.5 font-bold">What happens</th>
                            <th class="px-4 py-2.5 font-bold">Changes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($this->visibleRows as $row)
                            @php
                                [$label, $classes] = $badge[$row['action']];
                            @endphp
                            <tr class="{{ $row['warnings'] ? 'bg-red-50/50 dark:bg-red-500/5' : '' }}">
                                <td class="px-4 py-3 align-top text-xs text-gray-400">{{ $row['line'] }}</td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $row['name'] }}</div>
                                    @if ($row['sku'])
                                        <div class="text-xs text-gray-400">{{ $row['sku'] }}</div>
                                    @endif
                                    @foreach ($row['warnings'] as $warning)
                                        <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $warning }}</div>
                                    @endforeach
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-semibold {{ $classes }}">{{ $label }}</span>
                                    @if ($row['match'])
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            matched on {{ $row['matched_by'] }} → {{ $row['match']['name'] }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top">
                                    @if ($row['changes'] === [])
                                        <span class="text-xs text-gray-400">—</span>
                                    @else
                                        <ul class="space-y-0.5">
                                            @foreach ($row['changes'] as $change)
                                                <li class="text-xs text-gray-600 dark:text-gray-300">
                                                    <span class="font-medium">{{ $change['field'] }}</span>
                                                    @if ($change['from'] !== null)
                                                        <span class="text-gray-400">{{ $change['from'] }} →</span>
                                                    @endif
                                                    {{ $change['to'] }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        @if ($this->visibleRows === [])
                            <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">Nothing in this group.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>

            @if ($this->hiddenRowCount > 0)
                <div class="border-t border-gray-200 px-4 py-2.5 text-xs text-gray-400 dark:border-gray-700">
                    … and {{ $this->hiddenRowCount }} more rows not shown. They are all included in the import.
                </div>
            @endif
        </section>

        {{-- ── Step 3: commit ──────────────────────────────────────────── --}}
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">3</span>
                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Import it</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Writes the {{ $summary['create'] + $summary['update'] }} rows above that change something.
                            Nothing else in the catalogue is touched.
                        </p>
                    </div>
                </div>

                <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import"
                        class="vx-plain shrink-0 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-60">
                    <span wire:loading.remove wire:target="import">Import {{ $summary['create'] + $summary['update'] }} rows</span>
                    <span wire:loading wire:target="import">Importing…</span>
                </button>
            </div>

            @if ($result)
                <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-500/20 dark:bg-green-500/10 dark:text-green-300">
                    Done — <b>{{ $result['created'] }}</b> created, <b>{{ $result['updated'] }}</b> updated,
                    <b>{{ $result['unchanged'] }}</b> already matched. The table above has been re-read against the
                    catalogue as it is now, which is why most rows say "No change".
                </div>
            @endif
        </section>
    @endif
</div>
</x-filament-panels::page>
