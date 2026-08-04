<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Mode Tab Strip with Session Info ──────────────────────────────────── --}}
        <div class="space-y-2">
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
                <button wire:click="switchMode('stage')" type="button"
                    class="flex-1 rounded-lg px-4 py-2.5 text-sm font-medium transition-colors {{ $mode === 'stage' ? 'bg-amber-600 text-white shadow' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                    <x-heroicon-o-inbox-stack class="h-4 w-4 inline -mt-0.5 mr-1" />
                    Stage Pallet
                </button>
            </div>

            {{-- Session Info Bar --}}
            @if($currentSessionId)
            <div class="rounded-lg bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 px-4 py-3 flex items-center justify-between">
                <div class="text-sm">
                    <p class="font-semibold text-blue-900 dark:text-blue-100">Active Session</p>
                    <p class="text-xs text-blue-600 dark:text-blue-400">Scanned: {{ count($scannedCodes) }} items • Mode: {{ ucfirst($scanMode) }}</p>
                </div>
                <button wire:click="endReceivingSession" type="button"
                    class="rounded px-3 py-1.5 text-xs font-medium bg-blue-600 text-white hover:bg-blue-700">
                    End Session
                </button>
            </div>
            @endif
        </div>

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- STAGE PALLET MODE (NEW)                                             --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @if($mode === 'stage')

        <div class="rounded-xl border-2 border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950 px-6 py-5 space-y-4">
            <div class="flex items-center gap-3 mb-4">
                <x-heroicon-o-inbox-stack class="h-5 w-5 text-amber-600" />
                <h2 class="text-sm font-semibold text-amber-900 dark:text-amber-100">Stage New Pallet for Receiving</h2>
            </div>

            {{-- Vendor Selection --}}
            <div>
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300 block mb-2">Vendor</label>
                <select wire:model="stagingVendorId"
                    class="w-full rounded-lg border border-amber-300 dark:border-amber-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:ring-2 focus:ring-amber-500">
                    <option value="">Select vendor…</option>
                    @foreach(\App\Models\Vendor::orderBy('name')->get() as $vendor)
                        <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- PO/Reference Number --}}
            <div>
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300 block mb-2">PO / Reference #</label>
                <input
                    wire:model="stagingReference"
                    type="text"
                    placeholder="e.g., PO-2024-001234 or Invoice #5678"
                    class="w-full rounded-lg border border-amber-300 dark:border-amber-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500"
                />
            </div>

            {{-- Expected Delivery Date --}}
            <div>
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300 block mb-2">Expected Delivery Date (Optional)</label>
                <input
                    wire:model="stagingExpectedDeliveryDate"
                    type="date"
                    class="w-full rounded-lg border border-amber-300 dark:border-amber-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:ring-2 focus:ring-amber-500"
                />
            </div>

            {{-- Packing Slip Upload --}}
            <div class="border-2 border-dashed border-amber-300 dark:border-amber-600 rounded-lg p-4">
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300 block mb-2">Packing Slip (Optional)</label>
                <div class="text-center">
                    @if($stagingPackingSlipFile)
                        <div class="space-y-2">
                            <x-heroicon-o-check-circle class="h-8 w-8 text-green-500 mx-auto" />
                            <p class="text-sm font-medium text-green-700 dark:text-green-300">{{ $stagingPackingSlipFile->getClientOriginalName() }}</p>
                            <button wire:click="$set('stagingPackingSlipFile', null)" type="button" class="text-xs text-amber-600 hover:underline">
                                Remove
                            </button>
                        </div>
                    @else
                        <x-heroicon-o-document-arrow-up class="h-8 w-8 text-amber-400 mx-auto mb-2" />
                        <p class="text-xs text-gray-600 dark:text-gray-400">Drag and drop PDF or image, or click to select</p>
                        <input type="file" wire:model="stagingPackingSlipFile" accept=".pdf,.jpg,.jpeg,.png" class="mt-2 w-full text-xs cursor-pointer" />
                    @endif
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex gap-3 pt-4">
                <button wire:click="stagePallet" type="button"
                    class="flex-1 rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <x-heroicon-o-inbox-stack class="h-4 w-4 inline -mt-0.5 mr-1" />
                    Stage Pallet & Start Receiving
                </button>
                <button wire:click="switchMode('receive')" type="button"
                    class="flex-1 rounded-lg bg-gray-300 dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-900 dark:text-gray-100 hover:bg-gray-400 dark:hover:bg-gray-600">
                    Cancel
                </button>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- PACKING SLIP ANALYSIS MODAL                                         --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @if($showPackingSlipAnalysis && $packingSlipAnalysis)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 min-h-screen">
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-xl max-w-4xl w-full max-h-96 overflow-y-auto">
                <div class="sticky top-0 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Packing Slip Analysis</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        AI detected {{ $packingSlipAnalysis['total_matched'] }} existing items and {{ $packingSlipAnalysis['total_suggested'] }} new items
                    </p>
                </div>

                <div class="p-6 space-y-6">
                    {{-- Matched Items --}}
                    @if(!empty($packingSlipAnalysis['matched_items']))
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <x-heroicon-o-check-circle class="h-5 w-5 text-green-600" />
                            Matched Items ({{ count($packingSlipAnalysis['matched_items']) }})
                        </h4>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            @foreach($packingSlipAnalysis['matched_items'] as $idx => $match)
                            <div class="flex items-start gap-3 p-3 bg-green-50 dark:bg-green-950 rounded-lg border border-green-200 dark:border-green-800">
                                <input type="checkbox" wire:model="selectedMatchedItems" value="{{ $idx }}"
                                    class="mt-1 rounded border-gray-300 text-green-600 focus:ring-green-500" />
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $match['item_name'] }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        Extracted: {{ $match['extracted_name'] }} ({{ $match['extracted_qty'] }} units @ ${{ $match['extracted_cost'] }})
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-500">
                                        Current: {{ $match['item_sku'] }} • ${{ number_format($match['current_cost'], 2) }}
                                        <span class="ml-2 font-semibold text-green-700 dark:text-green-300">{{ $match['confidence'] }}% confidence</span>
                                    </p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Suggested New Items --}}
                    @if(!empty($packingSlipAnalysis['suggested_items']))
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <x-heroicon-o-plus-circle class="h-5 w-5 text-blue-600" />
                            New Items to Create ({{ count($packingSlipAnalysis['suggested_items']) }})
                        </h4>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            @foreach($packingSlipAnalysis['suggested_items'] as $idx => $suggestion)
                            <div class="flex items-start gap-3 p-3 bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800">
                                <input type="checkbox" wire:model="selectedSuggestedItems" value="{{ $idx }}"
                                    class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $suggestion['name'] }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        Category: {{ $suggestion['category'] }} • Qty: {{ $suggestion['qty'] }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-500">
                                        SKU: {{ $suggestion['sku'] ?? '(none)' }} • Cost: ${{ $suggestion['unit_cost'] ?? '0.00' }}
                                    </p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Action Buttons --}}
                <div class="border-t border-gray-200 dark:border-gray-700 px-6 py-4 flex gap-3 bg-gray-50 dark:bg-gray-800 sticky bottom-0">
                    <button wire:click="confirmPackingSlipAnalysis" type="button"
                        class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
                        <x-heroicon-o-check class="h-4 w-4 inline -mt-0.5 mr-1" />
                        Confirm & Create Items
                    </button>
                    <button wire:click="cancelPackingSlipAnalysis" type="button"
                        class="flex-1 rounded-lg bg-gray-300 dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-900 dark:text-gray-100">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
        @endif

        @endif

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- LOOK UP MODE                                                        --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @if($mode === 'lookup')

        {{-- Scanner Input --}}
        <div class="rounded-xl border-2 border-violet-300 dark:border-violet-700 bg-violet-50 dark:bg-violet-950 px-6 py-5 space-y-3">
            <div class="flex items-center gap-3">
                <x-heroicon-o-qr-code class="h-5 w-5 text-violet-500" />
                <h2 class="text-sm font-semibold text-violet-900 dark:text-violet-100">Look Up Item</h2>
                <span class="text-xs text-violet-600 dark:text-violet-400">Scan or type to find inventory and check pricing</span>
            </div>

            <div class="flex gap-3 items-center">
                <input
                    wire:model="scanInput"
                    wire:keydown.enter="submitScan"
                    id="scanner-input"
                    type="text"
                    placeholder="Scan barcode or type SKU…"
                    autofocus
                    autocomplete="off"
                    class="flex-1 rounded-lg border border-violet-300 dark:border-violet-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none font-mono"
                />
                <button id="camera-scan-btn" type="button" class="hidden rounded-lg bg-violet-200 dark:bg-violet-800 px-3 py-2.5 text-violet-600 dark:text-violet-300 hover:bg-violet-300 dark:hover:bg-violet-700" title="Scan with camera">
                    <x-heroicon-o-video-camera class="h-5 w-5" />
                </button>
                <button wire:click="submitScan" type="button"
                    class="rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500">
                    Look Up
                </button>
            </div>
        </div>

        {{-- Camera Feed (Hidden by default) --}}
        <div id="camera-container" class="hidden rounded-lg overflow-hidden bg-black">
            <video id="camera-video" class="w-full aspect-video object-cover"></video>
            <div class="bg-gray-900 px-4 py-3 flex gap-2">
                <button id="camera-stop-btn" type="button" class="flex-1 rounded px-3 py-2 text-sm font-medium bg-gray-700 text-white hover:bg-gray-600">
                    <x-heroicon-o-x-mark class="h-4 w-4 inline -mt-0.5 mr-1" />
                    Close Camera
                </button>
            </div>
        </div>

        {{-- Results with Cost Display --}}
        @if($result)
        <div class="space-y-4">
            {{-- Cost Warnings (NEW) --}}
            @if($costWarnings)
            <div class="space-y-2 mb-4">
                @foreach($costWarnings as $warning)
                <div class="rounded-lg border {{ $warning['severity'] === 'alert' ? 'border-red-300 dark:border-red-800 bg-red-50 dark:bg-red-950' : ($warning['severity'] === 'warning' ? 'border-yellow-300 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950' : 'border-blue-300 dark:border-blue-800 bg-blue-50 dark:bg-blue-950') }} px-4 py-3">
                    <div class="flex items-start gap-3">
                        @if($warning['severity'] === 'alert')
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 flex-shrink-0 {{ 'text-red-600 dark:text-red-400' }} mt-0.5" />
                        @elseif($warning['severity'] === 'warning')
                        <x-heroicon-o-exclamation-circle class="h-5 w-5 flex-shrink-0 {{ 'text-yellow-600 dark:text-yellow-400' }} mt-0.5" />
                        @else
                        <x-heroicon-o-information-circle class="h-5 w-5 flex-shrink-0 {{ 'text-blue-600 dark:text-blue-400' }} mt-0.5" />
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold {{ $warning['severity'] === 'alert' ? 'text-red-900 dark:text-red-100' : ($warning['severity'] === 'warning' ? 'text-yellow-900 dark:text-yellow-100' : 'text-blue-900 dark:text-blue-100') }}">
                                {{ $warning['title'] }}
                            </p>
                            <p class="text-xs {{ $warning['severity'] === 'alert' ? 'text-red-700 dark:text-red-300' : ($warning['severity'] === 'warning' ? 'text-yellow-700 dark:text-yellow-300' : 'text-blue-700 dark:text-blue-300') }} mt-1">
                                {{ $warning['message'] }}
                            </p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Item Header --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $result['name'] }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $result['sku'] ?? '—' }}</p>
                        @if($result['barcode'])
                        <p class="text-xs text-gray-400 dark:text-gray-500 font-mono mt-2">{{ $result['barcode'] }}</p>
                        @endif
                    </div>

                    {{-- Cost Metrics (NEW) --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-blue-50 dark:bg-blue-950 rounded-lg p-3">
                            <p class="text-xs font-medium text-blue-600 dark:text-blue-400">Average Cost</p>
                            <p class="text-xl font-bold text-blue-900 dark:text-blue-100">${{ number_format($result['avg_cost'], 2) }}</p>
                        </div>
                        <div class="bg-green-50 dark:bg-green-950 rounded-lg p-3">
                            <p class="text-xs font-medium text-green-600 dark:text-green-400">Total Stock</p>
                            <p class="text-xl font-bold text-green-900 dark:text-green-100">{{ $result['total_qty'] }} units</p>
                        </div>
                        <div class="bg-purple-50 dark:bg-purple-950 rounded-lg p-3">
                            <p class="text-xs font-medium text-purple-600 dark:text-purple-400">Inventory Value</p>
                            <p class="text-lg font-bold text-purple-900 dark:text-purple-100">${{ $result['inventory_value'] }}</p>
                        </div>
                        @if($result['is_low'])
                        <div class="bg-red-50 dark:bg-red-950 rounded-lg p-3">
                            <p class="text-xs font-medium text-red-600 dark:text-red-400">⚠ Low Stock</p>
                            <p class="text-sm font-semibold text-red-900 dark:text-red-100">Below {{ $result['reorder'] }}</p>
                        </div>
                        @endif
                        @if($result['pricing_anomaly'])
                        <div class="bg-orange-50 dark:bg-orange-950 rounded-lg p-3 col-span-2">
                            <p class="text-xs font-medium text-orange-600 dark:text-orange-400">⚠ Price Variance</p>
                            <p class="text-sm font-semibold text-orange-900 dark:text-orange-100">
                                {{ $result['pricing_anomaly']['variance_pct'] }}% variation detected
                            </p>
                            <p class="text-xs text-orange-700 dark:text-orange-300 mt-1">
                                Range: ${{ $result['pricing_anomaly']['min_cost'] }} – ${{ $result['pricing_anomaly']['max_cost'] }}
                            </p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Stock by Location --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h4 class="font-semibold text-gray-900 dark:text-white mb-4">Stock by Location</h4>
                <div class="space-y-2">
                    @forelse($result['stock'] as $s)
                    <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $s['location'] }}</span>
                        <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $s['qty'] }} units</span>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">No stock in any location</p>
                    @endforelse
                </div>
            </div>

            {{-- Vendor Costs --}}
            @if(!empty($result['vendor_costs']))
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h4 class="font-semibold text-gray-900 dark:text-white mb-4">Vendor Pricing</h4>
                <div class="space-y-2">
                    @foreach($result['vendor_costs'] as $vc)
                    <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $vc['vendor_name'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $vc['qty'] }} units received</p>
                        </div>
                        <span class="text-sm font-bold text-gray-900 dark:text-white">${{ $vc['avg_cost'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Cost Trend (Historical Pricing) --}}
            @if(!empty($result['cost_trend']))
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h4 class="font-semibold text-gray-900 dark:text-white mb-4">Price History</h4>
                <div class="space-y-1.5 text-xs">
                    @foreach($result['cost_trend'] as $trend)
                    <div class="flex justify-between items-center p-2.5 bg-gray-50 dark:bg-gray-800 rounded">
                        <div>
                            <p class="font-medium text-gray-700 dark:text-gray-300">{{ $trend['date'] }}</p>
                            <p class="text-gray-500 dark:text-gray-400">{{ $trend['vendor'] }} • {{ $trend['qty'] }} units</p>
                        </div>
                        <span class="font-semibold text-gray-900 dark:text-white">${{ $trend['cost'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Recent Movements --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h4 class="font-semibold text-gray-900 dark:text-white mb-4">Recent Activity</h4>
                <div class="space-y-2">
                    @forelse($result['movements'] as $m)
                    <div class="text-xs p-2 bg-gray-50 dark:bg-gray-800 rounded">
                        <p class="font-medium text-gray-900 dark:text-white">{{ ucfirst(str_replace('_', ' ', $m['type'])) }} • {{ $m['qty'] }} units</p>
                        <p class="text-gray-600 dark:text-gray-400">{{ $m['location'] }} • {{ $m['date'] }}</p>
                        @if($m['reason'])
                        <p class="text-gray-500 dark:text-gray-500 italic">{{ $m['reason'] }}</p>
                        @endif
                    </div>
                    @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">No recent activity</p>
                    @endforelse
                </div>
            </div>

            {{-- Actions --}}
            @if(!$adjustMode)
            <div class="flex gap-3">
                <button wire:click="openAdjust" type="button"
                    class="flex-1 rounded-lg bg-violet-600 px-4 py-3 text-sm font-medium text-white hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500">
                    <x-heroicon-o-pencil-square class="h-4 w-4 inline -mt-0.5 mr-2" />
                    Adjust Stock
                </button>
                <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('view', ['record' => $result['id']]) }}" target="_blank"
                    class="flex-1 rounded-lg bg-gray-600 px-4 py-3 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4 inline -mt-0.5 mr-2" />
                    Full Details
                </a>
            </div>
            @else
            <div class="rounded-xl border border-violet-300 dark:border-violet-700 bg-violet-50 dark:bg-violet-950 p-6 space-y-4">
                <h3 class="font-semibold text-violet-900 dark:text-violet-100">Manual Stock Adjustment</h3>
                <div>
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300">Location</label>
                    <select wire:model="adjustLocationId" class="w-full rounded-lg border border-violet-300 dark:border-violet-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm mt-1">
                        <option value="">Select location…</option>
                        @foreach($this->getLocationsProperty() as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                    <input type="number" wire:model="adjustQty" step="0.01" class="w-full rounded-lg border border-violet-300 dark:border-violet-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm mt-1" />
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300">Reason</label>
                    <input type="text" wire:model="adjustReason" placeholder="e.g., Inventory count correction" class="w-full rounded-lg border border-violet-300 dark:border-violet-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm mt-1" />
                </div>
                <div class="flex gap-2">
                    <button wire:click="applyAdjust" type="button" class="flex-1 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-700">Apply</button>
                    <button wire:click="$set('adjustMode', false)" type="button" class="flex-1 rounded-lg bg-gray-300 dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-900 dark:text-gray-100">Cancel</button>
                </div>
            </div>
            @endif
        </div>

        @elseif($errorMessage)
        <div class="rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 px-6 py-4 flex items-center gap-3">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0" />
            <div class="text-sm text-red-800 dark:text-red-200">{{ $errorMessage }}</div>
        </div>
        @endif

        @endif

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- QUICK ADD MODE                                                      --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @if($mode === 'quickadd')

        <div class="rounded-xl border-2 border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950 px-6 py-5 space-y-3">
            <div class="flex items-center gap-3">
                <x-heroicon-o-plus-circle class="h-5 w-5 text-emerald-600" />
                <h2 class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">Quick Add Stock</h2>
                <span class="text-xs text-emerald-600 dark:text-emerald-400">Scan items to quickly add to inventory as you receive them</span>
            </div>

            {{-- Location Selector --}}
            <div>
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300">Add to Location</label>
                <select wire:model="qaLocationId" class="w-full mt-1 rounded-lg border border-emerald-300 dark:border-emerald-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm">
                    <option value="">Select location…</option>
                    @foreach($this->getLocationsProperty() as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Quantity --}}
            <div class="flex gap-3 items-center">
                <div class="flex-1">
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300 block mb-1">Quantity per Scan</label>
                    <input type="number" wire:model="qaQty" min="0.01" step="0.01" class="w-full rounded-lg border border-emerald-300 dark:border-emerald-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm" />
                </div>
            </div>

            {{-- Scan Input --}}
            <div class="flex gap-3 items-center">
                <input
                    id="quickadd-barcode"
                    wire:model="scanInput"
                    wire:keydown.enter="submitScan"
                    type="text"
                    placeholder="Scan barcode…"
                    autofocus
                    autocomplete="off"
                    class="flex-1 rounded-lg border border-emerald-300 dark:border-emerald-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500 font-mono"
                />
                <button type="button"
                    onclick="window.dispatchEvent(new Event('open-camera-scanner'))"
                    class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 whitespace-nowrap">
                    <x-heroicon-o-video-camera class="h-4 w-4 inline -mt-0.5" />
                </button>
                <button wire:click="submitScan" type="button"
                    class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
                    Add
                </button>
            </div>
        </div>

        {{-- Flash Feedback --}}
        @if($qaFlash)
            @if(isset($qaFlash['error']))
            <div class="rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 px-6 py-4 text-sm text-red-800 dark:text-red-200">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 inline -mt-0.5 mr-2" />
                {{ $qaFlash['error'] }}
            </div>
            @else
            <div class="rounded-lg bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 dark:border-emerald-800 px-6 py-4 space-y-2">
                <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">✓ Added to {{ $qaFlash['location'] }}</p>
                <p class="text-sm text-emerald-700 dark:text-emerald-300"><strong>{{ $qaFlash['name'] }}</strong> • {{ $qaFlash['qty'] }} units</p>
            </div>
            @endif
        @endif

        @endif

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- RECEIVE PALLET MODE                                                 --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @if($mode === 'receive')

        {{-- Pallet Selection --}}
        <div class="rounded-xl border-2 border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-950 px-6 py-5 space-y-4">
            <div class="flex items-center gap-3">
                <x-heroicon-o-truck class="h-5 w-5 text-blue-600" />
                <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-100">Receive Pallet</h2>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300">Select Pallet</label>
                <select wire:model.live="rcvPalletId" class="w-full mt-1 rounded-lg border border-blue-300 dark:border-blue-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm">
                    <option value="">Choose pallet to receive…</option>
                    @foreach($this->getPalletsProperty() as $p)
                    <option value="{{ $p->id }}">{{ $p->reference ?: "Pallet #$p->id" }} ({{ ucfirst($p->status) }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Scan Mode Toggle --}}
            @if($rcvPalletId)
            <div class="flex gap-2">
                <button wire:click="$set('scanMode', 'barcode')" type="button"
                    class="flex-1 rounded-lg px-3 py-2 text-xs font-medium transition-colors {{ $scanMode === 'barcode' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 hover:bg-gray-300' }}">
                    <x-heroicon-o-qr-code class="h-4 w-4 inline -mt-0.5 mr-1" />
                    Barcode Scanner
                </button>
                <button wire:click="$set('scanMode', 'camera')" type="button"
                    class="flex-1 rounded-lg px-3 py-2 text-xs font-medium transition-colors {{ $scanMode === 'camera' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 hover:bg-gray-300' }}">
                    <x-heroicon-o-video-camera class="h-4 w-4 inline -mt-0.5 mr-1" />
                    Phone Camera
                </button>
            </div>

            {{-- Bulk Receive Button --}}
            <button wire:click="bulkReceivePallet" type="button"
                class="w-full rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                <x-heroicon-o-check-circle class="h-4 w-4 inline -mt-0.5 mr-2" />
                Receive All Items at Once
            </button>
            @endif
        </div>

        {{-- Scan Input --}}
        @if($rcvPalletId)
        <div class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950 px-6 py-4">
            <div class="flex gap-3 items-center">
                <input
                    wire:model="scanInput"
                    wire:keydown.enter="submitScan"
                    type="text"
                    placeholder="Scan item barcode…"
                    autofocus
                    autocomplete="off"
                    class="flex-1 rounded-lg border border-blue-300 dark:border-blue-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm font-mono"
                />
                <button wire:click="submitScan" type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
                    Receive
                </button>
            </div>
        </div>

        {{-- Flash/Error Messages --}}
        @if($rcvFlash)
        <div class="rounded-lg bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800 px-6 py-4 text-sm text-green-800 dark:text-green-200">
            <x-heroicon-o-check-circle class="h-5 w-5 inline -mt-0.5 mr-2" />
            {{ $rcvFlash }}
        </div>
        @endif

        @if($rcvError)
        <div class="rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 px-6 py-4 text-sm text-red-800 dark:text-red-200">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 inline -mt-0.5 mr-2" />
            {{ $rcvError }}
        </div>
        @endif

        {{-- Receiving Progress --}}
        @if($rcvProgress)
        <div class="space-y-4">
            {{-- Pallet Info --}}
            <div class="rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 px-6 py-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Pallet</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $rcvProgress['reference'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Vendor</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $rcvProgress['vendor'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Progress</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $rcvProgress['done_lines'] }}/{{ $rcvProgress['total_lines'] }} lines</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Status</p>
                        <p class="font-semibold text-gray-900 dark:text-white">
                            @if($rcvProgress['done_lines'] === $rcvProgress['total_lines'])
                                <span class="text-green-600">✓ Complete</span>
                            @else
                                <span class="text-blue-600">Receiving…</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- Line Items Progress --}}
            <div class="space-y-2">
                @foreach($rcvProgress['lines'] as $line)
                <div class="rounded-lg border {{ $line['done'] ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950' : 'border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950' }} px-4 py-3">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                @if($line['done'])
                                <x-heroicon-o-check-circle class="h-4 w-4 text-green-600" />
                                @elseif(!$line['is_mapped'])
                                <x-heroicon-o-exclamation-circle class="h-4 w-4 text-red-600" />
                                @else
                                <x-heroicon-o-clock class="h-4 w-4 text-yellow-600" />
                                @endif
                                <span class="font-medium text-sm">{{ $line['item_name'] }}</span>
                                @if($line['sku'])
                                <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $line['sku'] }}</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                {{ $line['received_cases'] }}/{{ $line['total_cases'] }} cases • ${{ number_format($line['unit_cost'], 2) }}/unit
                                @if(!$line['is_mapped'])
                                <span class="text-red-600">• ⚠ Unmapped</span>
                                @endif
                            </p>
                        </div>
                        <div class="text-right flex items-center gap-2">
                            <button wire:click="openCostAdjust({{ $line['line_id'] ?? 0 }})" type="button" class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300">
                                <x-heroicon-o-pencil-square class="h-4 w-4" />
                            </button>
                            <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ $line['total_cases'] > 0 ? ($line['received_cases'] / $line['total_cases']) * 100 : 0 }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @elseif($rcvPalletId)
        <div class="rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 px-8 py-12 text-center">
            <x-heroicon-o-inbox-stack class="h-10 w-10 mx-auto text-gray-400 mb-2" />
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Loading pallet details…</p>
        </div>
        @else
        <div class="rounded-lg border-2 border-dashed border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-950 px-8 py-12 text-center">
            <x-heroicon-o-truck class="h-10 w-10 mx-auto text-blue-400 mb-2" />
            <p class="text-sm font-medium text-blue-600 dark:text-blue-400">Select a pallet above to start receiving</p>
        </div>
        @endif

        @endif

        @endif

        {{-- Cost Adjustment Modal --}}
        @if($showCostAdjust && $costAdjustLineId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 max-w-md w-full mx-4">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-pencil class="h-5 w-5" />
                        Adjust Unit Cost
                    </h3>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">New Unit Cost ($)</label>
                        <input
                            type="number"
                            wire:model="costAdjustNewCost"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">Reason (Optional)</label>
                        <input
                            type="text"
                            wire:model="costAdjustReason"
                            placeholder="e.g., Price correction, bulk discount applied"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex gap-2">
                    <button
                        wire:click="applyCostAdjust"
                        type="button"
                        class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <x-heroicon-o-check class="h-4 w-4 inline -mt-0.5 mr-1" />
                        Apply
                    </button>
                    <button
                        wire:click="$set('showCostAdjust', false)"
                        type="button"
                        class="flex-1 rounded-lg bg-gray-200 dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-900 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
        @endif

    </div>

    {{-- Camera scanning scripts --}}
    @if($mode === 'lookup' || $mode === 'receive')
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite('resources/js/barcode-scanner.js')
    @endif
    <script type="module">
    (function () {
        async function setup() {
            for (let i = 0; i < 10 && !('BarcodeDetector' in window); i++) {
                await new Promise(r => setTimeout(r, 50));
            }
            if (!('BarcodeDetector' in window)) return;

            const btn       = document.getElementById('camera-scan-btn');
            const container = document.getElementById('camera-container');
            const video     = document.getElementById('camera-video');
            const stopBtn   = document.getElementById('camera-stop-btn');
            const input     = document.querySelector('input[wire\\:model="scanInput"]');

            if (!btn || btn.dataset.scannerBound === '1') return;
            btn.dataset.scannerBound = '1';
            btn.classList.remove('hidden');

            let stream    = null;
            let detector  = null;
            let scanning  = false;
            let rafHandle = null;
            let lastDetectionTime = 0;

            const canvas = document.createElement('canvas');
            const ctx    = canvas.getContext('2d', { willReadFrequently: true });

            btn.addEventListener('click', async () => {
                try {
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

                    let formats = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'qr_code'];
                    try {
                        const supported = await window.BarcodeDetector.getSupportedFormats();
                        if (supported && supported.length > 0) {
                            formats = supported;
                        }
                    } catch (e) {}

                    detector = new window.BarcodeDetector({ formats });
                    scanning = true;

                    const scanFrame = async () => {
                        if (!scanning) return;

                        try {
                            canvas.width = video.videoWidth;
                            canvas.height = video.videoHeight;
                            ctx.drawImage(video, 0, 0);

                            const barcodes = await detector.detect(canvas);

                            if (barcodes.length > 0) {
                                const now = Date.now();
                                if (now - lastDetectionTime > 1000) {
                                    const code = barcodes[0].rawValue;
                                    input.value = code;
                                    input.dispatchEvent(new Event('input', { bubbles: true }));
                                    input.focus();
                                    lastDetectionTime = now;
                                }
                            }
                        } catch (e) {
                            console.error('Detection error:', e);
                        }

                        if (scanning) {
                            rafHandle = requestAnimationFrame(scanFrame);
                        }
                    };

                    scanFrame();
                } catch (error) {
                    console.error('Camera error:', error);
                }
            });

            stopBtn.addEventListener('click', () => {
                scanning = false;
                if (rafHandle) cancelAnimationFrame(rafHandle);
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                }
                container.classList.add('hidden');
                btn.classList.remove('hidden');
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setup);
        } else {
            setup();
        }

        window.addEventListener('livewire:navigating', () => {
            const stopBtn = document.getElementById('camera-stop-btn');
            if (stopBtn && !stopBtn.closest('.hidden')) {
                stopBtn.click();
            }
        });
    })();
    </script>
    @endif

</x-filament-panels::page>
