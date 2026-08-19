<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Mode Tab Strip ──────────────────────────────────────────────────── --}}
        <div class="flex rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-1 gap-1">
            <button wire:click="switchMode('lookup')" type="button"
                class="flex-1 rounded-lg px-4 py-2.5 text-sm font-medium transition-colors {{ $mode === 'lookup' ? 'bg-violet-600 text-white shadow' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                <x-heroicon-o-magnifying-glass class="h-4 w-4 inline -mt-0.5 mr-1" />
                Look Up
            </button>
            <button wire:click="switchMode('quickadd')" type="button"
                class="flex-1 rounded-lg px-4 py-2.5 text-sm font-medium transition-colors {{ $mode === 'quickadd' ? 'bg-emerald-600 text-white shadow' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                <x-heroicon-o-plus-circle class="h-4 w-4 inline -mt-0.5 mr-1" />
                Quick Add
            </button>
            <button wire:click="switchMode('receive')" type="button"
                class="flex-1 rounded-lg px-4 py-2.5 text-sm font-medium transition-colors {{ $mode === 'receive' ? 'bg-blue-600 text-white shadow' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                <x-heroicon-o-truck class="h-4 w-4 inline -mt-0.5 mr-1" />
                Receive Pallet
            </button>
        </div>

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- LOOK UP MODE                                                        --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @if($mode === 'lookup')

        {{-- Scanner Input --}}
        <div class="rounded-xl border-2 border-violet-300 dark:border-violet-700 bg-violet-50 dark:bg-violet-950 px-6 py-5 space-y-3">
            <div class="flex items-center gap-3">
                <x-heroicon-o-qr-code class="h-5 w-5 text-violet-500" />
                <h2 class="text-sm font-semibold text-violet-900 dark:text-violet-100">Look Up Item</h2>
                <span class="text-xs text-violet-600 dark:text-violet-400">Scan a barcode, or type a SKU — works with any Bluetooth or USB scanner</span>
            </div>

            <div class="flex gap-2 md:gap-3 items-center flex-wrap md:flex-nowrap">
                <input
                    wire:model="scanInput"
                    wire:keydown.enter="submitScan"
                    id="scanner-input"
                    type="text"
                    placeholder="Scan barcode or type SKU…"
                    autofocus
                    autocomplete="off"
                    class="flex-1 min-w-0 rounded-lg border border-violet-300 dark:border-violet-600 bg-white dark:bg-gray-900 px-3 md:px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none font-mono"
                />
                <button wire:click="submitScan" type="button"
                    class="hidden md:inline-block rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500 flex-shrink-0">
                    Look Up
                </button>
                <button type="button" id="camera-scan-btn" title="Scan with camera"
                    class="flex-shrink-0 rounded-lg border border-violet-300 dark:border-violet-600 px-3 py-2.5 text-violet-600 dark:text-violet-400 hover:bg-violet-100 dark:hover:bg-violet-900 focus:outline-none focus:ring-2 focus:ring-violet-500 hidden">
                    <x-heroicon-o-camera class="h-5 w-5" />
                </button>
                <button wire:click="submitScan" type="button"
                    class="md:hidden flex-shrink-0 rounded-lg bg-violet-600 px-3 py-2.5 text-white hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500">
                    <x-heroicon-o-arrow-right class="h-5 w-5" />
                </button>
            </div>

            <div id="camera-container" class="hidden space-y-2">
                <video id="camera-video" class="w-full max-w-sm rounded-lg border border-violet-300" autoplay playsinline muted></video>
                <p class="text-xs text-violet-600 dark:text-violet-400">Point camera at barcode. Detection is automatic.</p>
                <button type="button" id="camera-stop-btn" class="text-xs text-red-500 underline">Stop camera</button>
            </div>
        </div>

        {{-- Error --}}
        @if ($errorMessage)
            <div class="rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 px-5 py-4 text-sm text-red-700 dark:text-red-300 flex items-start gap-3">
                <x-heroicon-o-exclamation-circle class="h-5 w-5 flex-shrink-0 mt-0.5 text-red-400" />
                <span>{{ $errorMessage }}</span>
            </div>
        @endif

        {{-- Result --}}
        @if ($result)
            <div class="space-y-4">

                {{-- Item header --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $result['name'] }}</h2>
                                @if ($result['is_low'])
                                    <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">Low Stock</span>
                                @endif
                            </div>
                            <div class="mt-1 flex items-center gap-4 text-xs text-gray-400 flex-wrap">
                                @if ($result['category'])
                                    <span>{{ $result['category'] }}</span>
                                @endif
                                @if ($result['sku'])
                                    <span class="font-mono">SKU: {{ $result['sku'] }}</span>
                                @endif
                                @if ($result['barcode'])
                                    <span class="font-mono">Barcode: {{ $result['barcode'] }}</span>
                                @endif
                                </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $result['total_qty'] }}</p>
                            <p class="text-xs text-gray-400">total units</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-3 flex-wrap">
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 px-4 py-2 text-center">
                            <p class="text-xs text-gray-400">Avg Cost</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">${{ $result['avg_cost'] }}</p>
                        </div>
                        @if ($result['reorder'])
                            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 px-4 py-2 text-center">
                                <p class="text-xs text-gray-400">Reorder At</p>
                                <p class="text-sm font-semibold {{ $result['is_low'] ? 'text-amber-600' : 'text-gray-900 dark:text-gray-100' }}">{{ $result['reorder'] }} units</p>
                            </div>
                        @endif
                        <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('edit', ['record' => $result['id']]) }}"
                            class="ml-auto rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800">
                            Edit Item
                        </a>
                        @if (! $adjustMode)
                            <button wire:click="openAdjust" type="button"
                                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">
                                Adjust Stock
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Adjust stock form --}}
                @if ($adjustMode)
                    <div class="rounded-xl border border-violet-200 dark:border-violet-800 bg-violet-50 dark:bg-violet-950 px-6 py-5 space-y-4">
                        <h3 class="text-sm font-semibold text-violet-900 dark:text-violet-100">Adjust Stock</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Location</label>
                                <select wire:model="adjustLocationId" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                    @foreach ($result['stock'] as $s)
                                        <option value="{{ $s['location_id'] }}">{{ $s['location'] }} ({{ $s['qty'] }} units)</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Quantity Change (+ add, − remove)</label>
                                <input wire:model="adjustQty" type="number" step="1" placeholder="e.g. 5 or -3"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm font-mono text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Reason (optional)</label>
                                <input wire:model="adjustReason" type="text" placeholder="Cycle count, damage, etc."
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button wire:click="applyAdjust" type="button"
                                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">
                                Apply Adjustment
                            </button>
                            <button wire:click="$set('adjustMode', false)" type="button"
                                class="rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800">
                                Cancel
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Stock by location --}}
                @if (count($result['stock']))
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                        <div class="px-6 py-3">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Stock by Location</h3>
                        </div>
                        @foreach ($result['stock'] as $s)
                            <div class="px-6 py-3 flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $s['location'] }}</span>
                                <span class="text-sm font-semibold {{ $s['qty'] <= 0 ? 'text-red-500' : 'text-gray-900 dark:text-gray-100' }}">{{ number_format($s['qty'], 0) }} units</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5 text-sm text-gray-400">
                        No stock records yet.
                    </div>
                @endif

                {{-- Recent movements --}}
                @if (count($result['movements']))
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                        <div class="px-6 py-3">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Recent Movements</h3>
                        </div>
                        @foreach ($result['movements'] as $m)
                            <div class="px-6 py-3 flex items-center gap-4 text-sm">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {{ str_contains($m['type'], 'in') || str_contains($m['type'], 'receipt') ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300' }}">
                                    {{ ucwords(str_replace('_', ' ', $m['type'])) }}
                                </span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $m['qty'] > 0 ? '+' : '' }}{{ number_format($m['qty'], 0) }}</span>
                                @if ($m['location'] !== '—')
                                    <span class="text-gray-500">→ {{ $m['location'] }}</span>
                                @endif
                                <span class="ml-auto text-gray-400 text-xs">{{ $m['date'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>
        @endif

        {{-- Empty state --}}
        @if (! $result && ! $errorMessage)
            <x-empty-state
                :icon="'<x-heroicon-o-qr-code class=\"h-12 w-12\" />'"
                title="Ready to Scan"
                description="Point your scanner at any item barcode or type the SKU to look up inventory details, stock levels, and recent movements."
            >
                <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
                    <p>💡 <strong>Pro tip:</strong> Use the camera button for quick hands-free scanning</p>
                    <p>⚡ Works with Bluetooth scanners, USB scanners, or your phone's camera</p>
                </div>
            </x-empty-state>
        @endif

        @endif {{-- end lookup mode --}}

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- QUICK ADD MODE                                                      --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @if($mode === 'quickadd')

        {{-- Config row --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Destination Location</label>
                <select wire:model="qaLocationId"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    @foreach ($this->locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}@if($loc->type) ({{ $loc->type }})@endif</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Units per Scan</label>
                <input wire:model="qaQty" type="number" step="1" min="1" placeholder="1"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2.5 text-sm font-mono text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>
        </div>

        {{-- Scanner input --}}
        <div class="rounded-xl border-2 border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950 px-6 py-5 space-y-3">
            <div class="flex items-center gap-3">
                <x-heroicon-o-plus-circle class="h-5 w-5 text-emerald-500" />
                <h2 class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">Quick Add to Stock</h2>
                <span class="text-xs text-emerald-600 dark:text-emerald-400">Each scan adds the quantity above to the selected location</span>
            </div>
            <div class="flex gap-3 items-center">
                <input wire:model="scanInput" wire:keydown.enter="submitScan"
                    type="text" placeholder="Scan barcode or type SKU…"
                    autofocus autocomplete="off"
                    class="flex-1 rounded-lg border border-emerald-300 dark:border-emerald-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none font-mono" />
                <button wire:click="submitScan" type="button"
                    class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    Add
                </button>
            </div>
        </div>

        {{-- Flash / error --}}
        @if ($qaFlash)
            @if (isset($qaFlash['error']))
                <div class="rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 px-5 py-4 text-sm text-red-700 dark:text-red-300 flex items-start gap-3">
                    <x-heroicon-o-exclamation-circle class="h-5 w-5 flex-shrink-0 mt-0.5 text-red-400" />
                    <span>{{ $qaFlash['error'] }}</span>
                </div>
            @else
                <div class="rounded-lg bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 dark:border-emerald-800 px-5 py-4 flex items-center gap-4">
                    <x-heroicon-o-check-circle class="h-6 w-6 text-emerald-500 flex-shrink-0" />
                    <div>
                        <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">{{ $qaFlash['name'] }}</p>
                        <p class="text-xs text-emerald-600 dark:text-emerald-400">+{{ $qaFlash['qty'] }} units added to {{ $qaFlash['location'] }}</p>
                    </div>
                </div>
            @endif
        @endif

        {{-- Empty state --}}
        @if (! $qaFlash)
            <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 px-8 py-10 text-center space-y-2">
                <x-heroicon-o-arrow-down-tray class="h-9 w-9 mx-auto text-gray-300 dark:text-gray-600" />
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Select a location, set quantity, then scan</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">Stock is credited instantly — scan multiple items in sequence</p>
            </div>
        @endif

        @endif {{-- end quickadd mode --}}

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- RECEIVE PALLET MODE                                                 --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @if($mode === 'receive')

        {{-- Pallet selector --}}
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Select Pallet</label>
            <select wire:model.live="rcvPalletId"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">— choose a pallet —</option>
                @foreach ($this->pallets as $p)
                    <option value="{{ $p->id }}">
                        {{ $p->reference ?: "Pallet #{$p->id}" }}
                        @if($p->status === 'received') ✓ received @endif
                    </option>
                @endforeach
            </select>
        </div>

        @if($rcvPalletId)

        {{-- Scanner input --}}
        <div class="rounded-xl border-2 border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-950 px-6 py-5 space-y-3">
            <div class="flex items-center gap-3">
                <x-heroicon-o-truck class="h-5 w-5 text-blue-500" />
                <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-100">Receive Cases</h2>
                <span class="text-xs text-blue-600 dark:text-blue-400">Scan the item's barcode or type its SKU to receive all its cases</span>
            </div>
            <div class="flex gap-3 items-center">
                <input wire:model="scanInput" wire:keydown.enter="submitScan"
                    type="text" placeholder="Scan item barcode or type SKU…"
                    autofocus autocomplete="off"
                    class="flex-1 rounded-lg border border-blue-300 dark:border-blue-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none font-mono" />
                <button wire:click="submitScan" type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Receive
                </button>
            </div>
        </div>

        {{-- Flash / Error --}}
        @if ($rcvFlash)
            <div class="rounded-lg bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 px-5 py-4 flex items-center gap-3">
                <x-heroicon-o-check-circle class="h-5 w-5 text-blue-500 flex-shrink-0" />
                <span class="text-sm text-blue-800 dark:text-blue-200">{{ $rcvFlash }}</span>
            </div>
        @endif
        @if ($rcvError)
            <div class="rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 px-5 py-4 flex items-start gap-3">
                <x-heroicon-o-exclamation-circle class="h-5 w-5 flex-shrink-0 mt-0.5 text-red-400" />
                <span class="text-sm text-red-700 dark:text-red-300">{{ $rcvError }}</span>
            </div>
        @endif

        {{-- Pallet progress --}}
        @if ($rcvProgress)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">

                {{-- Header --}}
                <div class="px-6 py-4 flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $rcvProgress['reference'] }}</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Vendor: {{ $rcvProgress['vendor'] }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $rcvProgress['done_lines'] }}<span class="text-base font-normal text-gray-400">/{{ $rcvProgress['total_lines'] }}</span></p>
                        <p class="text-xs text-gray-400">lines received</p>
                    </div>
                </div>

                {{-- Progress bar --}}
                @php $pct = $rcvProgress['total_lines'] > 0 ? round($rcvProgress['done_lines'] / $rcvProgress['total_lines'] * 100) : 0; @endphp
                <div class="px-6 py-3">
                    <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                        <div class="h-full rounded-full bg-blue-500 transition-all duration-300" style="width: {{ $pct }}%"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1 text-right">{{ $pct }}% complete</p>
                </div>

                {{-- Per-line status --}}
                @foreach ($rcvProgress['lines'] as $ln)
                    <div class="px-6 py-3 flex items-center gap-4">
                        {{-- Status icon --}}
                        <div class="flex-shrink-0 w-6 text-center">
                            @if ($ln['done'])
                                <span class="text-green-500 text-base">✓</span>
                            @elseif (! $ln['is_mapped'])
                                <span class="text-amber-400 text-base" title="Not mapped — go to pallet detail to map this line">⚠</span>
                            @else
                                <span class="text-gray-300 dark:text-gray-600 text-base">○</span>
                            @endif
                        </div>

                        {{-- Line info --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                {{ $ln['item_name'] }}
                                <span class="text-xs font-normal text-gray-400 ml-1">#{{ $ln['line_number'] }}</span>
                            </p>
                            <div class="flex items-center gap-3 mt-0.5 text-xs text-gray-400">
                                @if ($ln['barcode'])
                                    <span class="font-mono">{{ $ln['barcode'] }}</span>
                                @elseif ($ln['sku'])
                                    <span class="font-mono">{{ $ln['sku'] }}</span>
                                @endif
                                @if (! $ln['is_mapped'])
                                    <span class="text-amber-500">needs mapping</span>
                                @endif
                            </div>
                        </div>

                        {{-- Case counter --}}
                        <div class="flex-shrink-0 text-right">
                            <span class="text-sm font-semibold {{ $ln['done'] ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-gray-100' }}">
                                {{ $ln['received_cases'] }}/{{ $ln['total_cases'] }}
                            </span>
                            <p class="text-xs text-gray-400">cases</p>
                        </div>
                    </div>
                @endforeach

            </div>

        @else

        {{-- Empty state when no pallet selected --}}
        <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 px-8 py-12 text-center space-y-2">
            <x-heroicon-o-truck class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Select a pallet to start receiving</p>
            <p class="text-xs text-gray-400 dark:text-gray-500">Scan each item's barcode to receive all its expected cases at once</p>
        </div>

        @endif

        @endif {{-- end receive mode --}}

    </div>

    {{-- Camera scanning — active only in lookup mode (uses global barcode scanner) --}}
    @if($mode === 'lookup')
        <script>
        (function () {
            function setup() {
                const btn       = document.getElementById('camera-scan-btn');
                const container = document.getElementById('camera-container');
                const video     = document.getElementById('camera-video');
                const stopBtn   = document.getElementById('camera-stop-btn');

                if (!btn || btn.dataset.scannerBound === '1') return;
                btn.dataset.scannerBound = '1';
                btn.classList.remove('hidden');

                let codeReader  = null;
                let stream      = null;
                let scanning    = false;
                let lastScanned = null;

                btn.addEventListener('click', async () => {
                    // Loaded on demand: the decoder is not shipped to pages that never
                    // scan, so it is fetched the first time one is opened.
                    await window.ensureBarcodeScanner?.();

                    if (!window.barcodeScanner?.BrowserMultiFormatReader) {
                        alert('Barcode scanner not available');
                        return;
                    }

                    try {
                        codeReader = new window.barcodeScanner.BrowserMultiFormatReader();
                        stream = await navigator.mediaDevices.getUserMedia({
                            video: {
                                facingMode: 'environment',
                                width: { ideal: 1280 },
                                height: { ideal: 720 }
                            }
                        });

                        video.srcObject = stream;
                        container.classList.remove('hidden');
                        btn.classList.add('hidden');

                        // Wait for video to be ready
                        await new Promise(resolve => {
                            const checkReady = () => {
                                if (video.readyState >= video.HAVE_CURRENT_DATA) {
                                    resolve();
                                } else {
                                    setTimeout(checkReady, 50);
                                }
                            };
                            checkReady();
                        });

                        scanning = true;
                        await codeReader.decodeFromVideoElement(
                            video,
                            (result, err) => {
                                if (result && scanning) {
                                    const barcode = result.getText();
                                    if (barcode !== lastScanned) {
                                        lastScanned = barcode;
                                        console.log('[inventory-camera] Detected:', barcode);
                                        stopCamera();
                                        @@this.set('scanInput', barcode).then(() => @@this.call('submitScan'));
                                    }
                                }
                            }
                        );
                    } catch (e) {
                        console.error('Camera error:', e);
                        alert('Camera error: ' + (e.message || 'Unable to start camera'));
                    }
                });

                stopBtn.addEventListener('click', stopCamera);

                function stopCamera() {
                    scanning = false;
                    if (codeReader) {
                        try {
                            codeReader.reset();
                        } catch (e) {
                            console.log('Error resetting reader:', e);
                        }
                    }
                    if (stream) {
                        stream.getTracks().forEach(t => t.stop());
                        stream = null;
                    }
                    video.srcObject = null;
                    container.classList.add('hidden');
                    btn.classList.remove('hidden');
                }
            }

            setup();
            document.addEventListener('livewire:navigated', setup);
        })();
        </script>
    @endif
</x-filament-panels::page>
