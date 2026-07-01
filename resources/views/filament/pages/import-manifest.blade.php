<x-filament-panels::page>
    <div class="space-y-6 max-w-3xl">

        {{-- ── Progress bar ────────────────────────────────────────────────── --}}
        @php
            $stages = ['upload' => 'Upload', 'mapping' => 'Map Columns', 'preview' => 'Preview', 'done' => 'Done'];
            $stageKeys = array_keys($stages);
            $currentIdx = array_search($stage, $stageKeys);
        @endphp
        <div class="flex items-center gap-0">
            @foreach ($stages as $key => $label)
                @php
                    $idx   = array_search($key, $stageKeys);
                    $done  = $idx < $currentIdx;
                    $active = $key === $stage;
                @endphp
                <div class="flex items-center {{ $loop->last ? '' : 'flex-1' }}">
                    <div class="flex flex-col items-center">
                        <div class="h-8 w-8 rounded-full flex items-center justify-center text-xs font-semibold
                            {{ $done ? 'bg-green-500 text-white' : ($active ? 'bg-violet-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-400') }}">
                            @if ($done)
                                <x-heroicon-o-check class="h-4 w-4" />
                            @else
                                {{ $idx + 1 }}
                            @endif
                        </div>
                        <span class="mt-1 text-[11px] font-medium {{ $active ? 'text-violet-600 dark:text-violet-400' : 'text-gray-400' }}">{{ $label }}</span>
                    </div>
                    @if (! $loop->last)
                        <div class="flex-1 h-0.5 mx-2 mb-4 {{ $done ? 'bg-green-400' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════
             Stage 1 — Upload
        ═══════════════════════════════════════════════════════════════════ --}}
        @if ($stage === 'upload')
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-6 space-y-5">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Upload Packing Slip</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Upload a CSV export of your vendor's packing slip. The first row should be the header.
                        Column names don't matter — you'll map them to fields on the next screen.
                    </p>
                    <p class="mt-2 text-xs text-gray-400">
                        Pallet: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $this->record->reference ?? "#{$this->record->id}" }}</span>
                        @if ($this->record->vendor)
                            &nbsp;·&nbsp; Vendor: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $this->record->vendor->name }}</span>
                        @endif
                    </p>
                </div>

                <div class="border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl px-6 py-8 text-center">
                    <x-heroicon-o-document-arrow-up class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                    <p class="mt-2 text-sm text-gray-500">Select a CSV file from your vendor</p>
                    <label class="mt-4 inline-block cursor-pointer rounded-lg bg-violet-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-violet-700">
                        Choose CSV
                        <input wire:model="manifestFile" type="file" accept=".csv,text/csv,text/plain" class="sr-only" />
                    </label>
                    <div wire:loading wire:target="manifestFile" class="mt-3 text-xs text-gray-400">Parsing headers…</div>
                </div>

                <div>
                    <a href="{{ route('manifest.template') }}" class="text-sm text-violet-600 dark:text-violet-400 underline" target="_blank">
                        Download example CSV template
                    </a>
                </div>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════
             Stage 2 — Column Mapping
        ═══════════════════════════════════════════════════════════════════ --}}
        @if ($stage === 'mapping')
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-6 space-y-5">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Map Your Columns</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Tell us what each column in your CSV means. We've made our best guess — adjust anything that's wrong.
                    </p>
                </div>

                @if ($mappingError)
                    <div class="rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300 flex items-center gap-2">
                        <x-heroicon-o-exclamation-circle class="h-4 w-4 flex-shrink-0" />
                        {{ $mappingError }}
                    </div>
                @endif

                <div class="space-y-3">
                    @foreach ($headers as $header)
                        <div class="flex items-center gap-4">
                            <div class="w-48 flex-shrink-0">
                                <span class="inline-block rounded bg-gray-100 dark:bg-gray-800 px-3 py-1.5 text-xs font-mono text-gray-700 dark:text-gray-300 truncate max-w-full">
                                    {{ $header }}
                                </span>
                            </div>
                            <x-heroicon-o-arrow-right class="h-4 w-4 text-gray-300 flex-shrink-0" />
                            <select
                                wire:model="columnMapping.{{ $header }}"
                                class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-1.5 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500"
                            >
                                @foreach ($this->targetFieldOptions() as $value => $label)
                                    <option value="{{ $value }}" {{ ($columnMapping[$header] ?? '') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>

                @if ($this->record->vendor_id)
                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer">
                        <input wire:model="saveMapping" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500" />
                        Save this mapping for <span class="font-medium text-gray-800 dark:text-gray-200">{{ $this->record->vendor->name }}</span> — auto-apply next time
                    </label>
                @endif

                <div class="flex items-center gap-3 pt-2">
                    <button
                        wire:click="goToPreview"
                        type="button"
                        class="rounded-lg bg-violet-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500"
                    >
                        Preview Import
                    </button>
                    <a
                        href="{{ \App\Filament\Resources\PalletResource::getUrl('view', ['record' => $this->record]) }}"
                        class="rounded-lg border border-gray-200 dark:border-gray-700 px-5 py-2.5 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800"
                    >
                        Cancel
                    </a>
                </div>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════
             Stage 3 — Preview
        ═══════════════════════════════════════════════════════════════════ --}}
        @if ($stage === 'preview')
            @php
                $matchCount   = collect($allRows)->filter(fn ($r) => ! empty($r['_matched']))->count();
                $unmatchCount = count($allRows) - $matchCount;
            @endphp

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-6 space-y-5">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Preview</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ count($allRows) }} line(s) found.
                        <span class="text-green-600 dark:text-green-400 font-medium">{{ $matchCount }} auto-matched</span> to existing inventory items.
                        @if ($unmatchCount > 0)
                            <span class="text-amber-600 dark:text-amber-400 font-medium">{{ $unmatchCount }} will need manual mapping</span> after import.
                        @endif
                    </p>
                </div>

                {{-- Preview table (first 5 rows) --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="pb-2 pr-4 font-medium text-gray-500 text-xs uppercase">Description</th>
                                <th class="pb-2 pr-4 font-medium text-gray-500 text-xs uppercase">Cases</th>
                                <th class="pb-2 pr-4 font-medium text-gray-500 text-xs uppercase">Cost</th>
                                <th class="pb-2 font-medium text-gray-500 text-xs uppercase">Matched Item</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                            @foreach ($previewRows as $row)
                                <tr>
                                    <td class="py-2 pr-4 text-gray-900 dark:text-gray-100 max-w-xs truncate">{{ $row['description'] ?? '—' }}</td>
                                    <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">{{ $row['case_count'] ?? '1' }}</td>
                                    <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">{{ isset($row['unit_cost']) && $row['unit_cost'] ? '$' . number_format((float) str_replace(['$',','], '', $row['unit_cost']), 2) : '—' }}</td>
                                    <td class="py-2">
                                        @if (! empty($row['_matched']))
                                            <span class="inline-flex items-center gap-1 text-green-700 dark:text-green-300">
                                                <x-heroicon-o-check-circle class="h-3.5 w-3.5" />
                                                {{ $row['_matched'] }}
                                            </span>
                                        @else
                                            <span class="text-amber-500 dark:text-amber-400 text-xs">Needs mapping</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if (count($allRows) > 5)
                        <p class="mt-2 text-xs text-gray-400">… and {{ count($allRows) - 5 }} more rows</p>
                    @endif
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        wire:click="import"
                        wire:loading.attr="disabled"
                        type="button"
                        class="rounded-lg bg-violet-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="import">Import {{ count($allRows) }} Lines</span>
                        <span wire:loading wire:target="import">Importing…</span>
                    </button>
                    <button
                        wire:click="$set('stage', 'mapping')"
                        type="button"
                        class="rounded-lg border border-gray-200 dark:border-gray-700 px-5 py-2.5 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800"
                    >
                        Back
                    </button>
                </div>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════
             Stage 4 — Done
        ═══════════════════════════════════════════════════════════════════ --}}
        @if ($stage === 'done')
            <div class="rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 px-6 py-8 text-center space-y-4">
                <x-heroicon-o-check-circle class="h-12 w-12 mx-auto text-green-500" />
                <div>
                    <h2 class="text-lg font-semibold text-green-900 dark:text-green-100">Manifest Imported</h2>
                    <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                        {{ $created }} line(s) created.
                        {{ $matched }} auto-matched to existing inventory.
                        @if ($unmatched > 0)
                            <strong>{{ $unmatched }} line(s) need manual mapping</strong> — use the "Map Line to Item" action on the pallet.
                        @endif
                    </p>
                </div>
                <a
                    href="{{ \App\Filament\Resources\PalletResource::getUrl('view', ['record' => $this->record]) }}"
                    class="inline-block rounded-lg bg-green-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-green-700"
                >
                    Back to Pallet
                </a>
            </div>
        @endif

    </div>
</x-filament-panels::page>
