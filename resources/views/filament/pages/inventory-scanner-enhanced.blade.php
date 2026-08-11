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

        {{-- Workflow Guide --}}
        <div class="rounded-lg bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 px-6 py-4 mb-6">
            <div class="flex items-start gap-3">
                <x-heroicon-o-light-bulb class="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" />
                <div class="text-sm text-blue-900 dark:text-blue-100">
                    <p class="font-semibold mb-2">📦 Pre-Receiving Workflow</p>
                    <ol class="space-y-1 text-xs ml-4 list-decimal">
                        <li><strong>Stage pallet:</strong> Enter vendor & PO info, optionally upload packing slip</li>
                        <li><strong>Add items:</strong> Upload packing slip (AI-parsed) or manually add items one-by-one</li>
                        <li><strong>Preflight costs:</strong> Optional cost estimates from vendor docs (doesn't affect real cost yet)</li>
                        <li><strong>Pending status:</strong> Items sit in "pending" state, doesn't count toward actual inventory</li>
                        <li><strong>Receive items:</strong> Switch to Receive mode, scan items to confirm arrival & add to inventory</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="rounded-xl border-2 border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950 px-6 py-5 space-y-4">
            <div class="flex items-center gap-3 mb-4">
                <x-heroicon-o-inbox-stack class="h-5 w-5 text-amber-600" />
                <h2 class="text-sm font-semibold text-amber-900 dark:text-amber-100">1️⃣ Stage New Pallet for Receiving</h2>
                <span class="text-xs text-amber-700 dark:text-amber-300 ml-auto">Step 1 of 3</span>
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
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300 block mb-2">
                    📄 Packing Slip (Optional)
                    <span class="text-amber-600 dark:text-amber-400">— Upload PDF or image for AI parsing</span>
                </label>
                <p class="text-xs text-gray-600 dark:text-gray-400 mb-3">Upload vendor packing slip (PDF/image) and we'll AI-extract items, quantities, and costs. Or skip this and manually add items.</p>
                <div class="text-center">
                    @if($stagingPackingSlipFile)
                        <div class="space-y-2">
                            <x-heroicon-o-check-circle class="h-8 w-8 text-green-500 mx-auto" />
                            <p class="text-sm font-medium text-green-700 dark:text-green-300">{{ $stagingPackingSlipFile->getClientOriginalName() }}</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Ready to analyze when you stage the pallet</p>
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

        {{-- Scanner Input (Mobile Optimized) --}}
        <div class="rounded-xl border-2 border-violet-300 dark:border-violet-700 bg-violet-50 dark:bg-violet-950 px-4 sm:px-6 py-5 space-y-3">
            <div class="flex items-center gap-3 flex-wrap">
                <x-heroicon-o-qr-code class="h-5 w-5 text-violet-500 flex-shrink-0" />
                <h2 class="text-sm font-semibold text-violet-900 dark:text-violet-100">Look Up Item</h2>
                <span class="text-xs text-violet-600 dark:text-violet-400 w-full sm:w-auto">Scan or type to find inventory and check pricing</span>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                <input
                    wire:model.live="scanInput"
                    wire:keydown.enter="submitScan"
                    id="scanner-input"
                    type="text"
                    placeholder="Scan barcode or type SKU…"
                    inputmode="text"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    autofocus
                    class="flex-1 rounded-lg border border-violet-300 dark:border-violet-600 bg-white dark:bg-gray-900 px-4 py-3 sm:py-2.5 text-base sm:text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none font-mono touch-manipulation"
                />
                <div class="flex gap-2 items-stretch sm:items-center w-full sm:w-auto">
                    @if($scanInput)
                    <button wire:click="clearScanInput" type="button"
                        class="flex-1 sm:flex-none px-3 py-3 sm:py-2.5 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-600 transition text-sm font-medium">
                        <x-heroicon-o-x-mark class="h-5 w-5 mx-auto" />
                    </button>
                    @endif
                    <button class="camera-scan-btn flex-1 px-3 py-3 rounded-lg bg-violet-500 text-white hover:bg-violet-600 active:bg-violet-700 transition font-medium text-sm" title="Scan with camera" type="button">
                        <x-heroicon-o-video-camera class="h-5 w-5 mx-auto" />
                        Camera
                    </button>
                    <button wire:click="submitScan" type="button"
                        class="flex-1 sm:flex-none px-4 py-3 sm:py-2.5 rounded-lg bg-violet-600 text-base sm:text-sm font-medium text-white hover:bg-violet-700 active:bg-violet-800 transition focus:outline-none focus:ring-2 focus:ring-violet-500">
                        Look Up
                    </button>
                </div>
            </div>

            {{-- Mobile Scanner Tips --}}
            <div class="text-xs text-violet-700 dark:text-violet-300 bg-white/50 dark:bg-gray-800/50 rounded px-3 py-2">
                <p class="font-medium mb-1">💡 Scanner Tips:</p>
                <ul class="space-y-0.5 list-disc list-inside">
                    <li>Press Enter or tap "Look Up" after scanning</li>
                    <li>Paste events from mobile scanners work automatically</li>
                    <li>Use camera button for barcode detection</li>
                </ul>
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

            {{-- Item Header with Quick Actions --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-white to-gray-50 dark:from-gray-900 dark:to-gray-950 p-6">
                {{-- Header Row --}}
                <div class="mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-start justify-between gap-4 mb-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white truncate">{{ $result['name'] }}</h3>
                                @if($result['is_low'])
                                <span class="px-2 py-1 rounded-full bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-xs font-semibold">Low Stock</span>
                                @else
                                <span class="px-2 py-1 rounded-full {{ $result['total_qty'] > $result['reorder'] * 1.5 ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300' }} text-xs font-semibold">
                                    {{ $result['total_qty'] > $result['reorder'] * 1.5 ? 'Well Stocked' : 'Adequate Stock' }}
                                </span>
                                @endif
                            </div>
                            <p class="text-sm font-mono text-gray-600 dark:text-gray-400">SKU: {{ $result['sku'] ?? '—' }}</p>
                            @if($result['barcode'])
                            <p class="text-xs text-gray-500 dark:text-gray-500 font-mono mt-1">{{ $result['barcode'] }}</p>
                            @endif
                        </div>
                        {{-- Quick Copy Buttons --}}
                        <div class="flex gap-2 flex-shrink-0">
                            @if($result['sku'])
                            <button onclick="navigator.clipboard.writeText('{{ $result['sku'] }}'); alert('SKU copied!')" type="button" title="Copy SKU" class="p-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                                <x-heroicon-o-clipboard class="h-4 w-4" />
                            </button>
                            @endif
                            @if($result['barcode'])
                            <button onclick="navigator.clipboard.writeText('{{ $result['barcode'] }}'); alert('Barcode copied!')" type="button" title="Copy Barcode" class="p-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                                <x-heroicon-o-qr-code class="h-4 w-4" />
                            </button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Cost & Stock Metrics --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                    <div class="bg-blue-50 dark:bg-blue-950 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                        <p class="text-xs font-medium text-blue-600 dark:text-blue-400 mb-1">Average Cost</p>
                        <p class="text-lg font-bold text-blue-900 dark:text-blue-100">${{ number_format($result['avg_cost'], 2) }}</p>
                    </div>
                    <div class="bg-green-50 dark:bg-green-950 rounded-lg p-4 border {{ $result['is_low'] ? 'border-red-300 dark:border-red-800' : 'border-green-200 dark:border-green-800' }}">
                        <p class="text-xs font-medium {{ $result['is_low'] ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }} mb-1">Total Stock</p>
                        <p class="text-lg font-bold {{ $result['is_low'] ? 'text-red-900 dark:text-red-100' : 'text-green-900 dark:text-green-100' }}">{{ $result['total_qty'] }} units</p>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-950 rounded-lg p-4 border border-purple-200 dark:border-purple-800">
                        <p class="text-xs font-medium text-purple-600 dark:text-purple-400 mb-1">Inventory Value</p>
                        <p class="text-lg font-bold text-purple-900 dark:text-purple-100">${{ number_format($result['inventory_value'], 0) }}</p>
                    </div>
                    <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4 border border-gray-300 dark:border-gray-600">
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Reorder Point</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $result['reorder'] }} units</p>
                    </div>
                </div>

                {{-- Quick Action Buttons --}}
                <div class="flex flex-wrap gap-2">
                    <a href="/admin/inventory-items/{{ $result['id'] }}" target="_blank" class="inline-flex items-center gap-1 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition">
                        <x-heroicon-o-eye class="h-4 w-4" />
                        View Item
                    </a>
                </div>
            </div>

            {{-- Cost Analysis Section --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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

        {{-- Camera Feed (Mobile-Optimized, Fullscreen on mobile) --}}
        <div id="camera-container" class="hidden fixed inset-0 sm:relative sm:rounded-lg sm:overflow-hidden bg-black z-50 flex flex-col">
            {{-- Camera Video Feed --}}
            <div class="flex-1 relative overflow-hidden">
                <video id="camera-video" class="w-full h-full object-cover" playsinline></video>

                {{-- Loading Overlay (shown while camera initializing) --}}
                <div id="camera-loading" class="absolute inset-0 bg-black/70 flex flex-col items-center justify-center gap-4">
                    <div class="space-y-4">
                        <div class="flex gap-2 justify-center">
                            <div class="w-3 h-3 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0s;"></div>
                            <div class="w-3 h-3 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0.15s;"></div>
                            <div class="w-3 h-3 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0.3s;"></div>
                        </div>
                        <div class="text-center">
                            <p class="text-white font-medium text-sm">Opening camera...</p>
                            <p class="text-gray-300 text-xs mt-2">Requesting access to your device's camera</p>
                        </div>
                    </div>
                </div>

                {{-- Crosshair Overlay (Mobile scanner hint) --}}
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="relative w-48 h-32 border-2 border-green-500/40">
                        {{-- Corner brackets --}}
                        <div class="absolute top-0 left-0 w-6 h-6 border-t-2 border-l-2 border-green-500"></div>
                        <div class="absolute top-0 right-0 w-6 h-6 border-t-2 border-r-2 border-green-500"></div>
                        <div class="absolute bottom-0 left-0 w-6 h-6 border-b-2 border-l-2 border-green-500"></div>
                        <div class="absolute bottom-0 right-0 w-6 h-6 border-b-2 border-r-2 border-green-500"></div>

                        {{-- Center dot --}}
                        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-1 h-1 bg-green-500 rounded-full"></div>

                        {{-- Scan line animation --}}
                        <div class="absolute inset-x-0 top-1/3 h-0.5 bg-gradient-to-r from-transparent via-green-500 to-transparent animate-pulse"></div>
                    </div>

                    {{-- Guide text --}}
                    <div class="absolute bottom-16 left-0 right-0 text-center">
                        <p class="text-green-400 text-sm font-medium">Point camera at barcode</p>
                    </div>
                </div>

                {{-- Scanning Indicator --}}
                <div class="absolute top-4 left-4 sm:left-auto sm:right-4 bg-black/70 backdrop-blur px-3 py-2 rounded-lg flex items-center gap-2">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-xs font-medium text-white">Scanning for barcode...</span>
                </div>
            </div>

            {{-- Mobile-Friendly Controls (Bottom sheet style) --}}
            <div class="bg-gradient-to-t from-gray-900 via-gray-900 to-transparent px-4 py-4 sm:px-4 sm:py-3 flex gap-3 items-center">
                <button id="camera-stop-btn" type="button" class="flex-1 sm:flex-none rounded-lg px-4 py-3 sm:py-2 text-sm font-medium bg-red-600 text-white hover:bg-red-700 active:bg-red-800 transition">
                    <x-heroicon-o-x-mark class="h-5 w-5 inline -mt-0.5 mr-2" />
                    <span class="hidden sm:inline">Close Camera</span>
                    <span class="sm:hidden">Close</span>
                </button>
                <div class="flex-1 text-center">
                    <p class="text-xs text-gray-400">Hold camera steady over barcode</p>
                </div>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- QUICK ADD MODE                                                      --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @if($mode === 'quickadd')

        <div class="rounded-xl border-2 border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950 px-4 sm:px-6 py-5 space-y-3">
            <div class="flex items-center gap-3 flex-wrap">
                <x-heroicon-o-plus-circle class="h-5 w-5 text-emerald-600 flex-shrink-0" />
                <h2 class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">⚡ Quick Add Stock</h2>
                <span class="text-xs text-emerald-600 dark:text-emerald-400 w-full sm:w-auto">Fast path: Scan items to directly add to inventory without pallet staging</span>
            </div>

            {{-- Location Selector --}}
            <div>
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300">Add to Location</label>
                <select wire:model="qaLocationId" class="w-full mt-1 rounded-lg border border-emerald-300 dark:border-emerald-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-base sm:text-sm">
                    <option value="">Select location…</option>
                    @foreach($this->getLocationsProperty() as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Quantity --}}
            <div class="flex gap-3 items-stretch sm:items-center">
                <div class="flex-1">
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300 block mb-1">Quantity per Scan</label>
                    <input type="number" wire:model="qaQty" min="0.01" step="0.01" inputmode="decimal" class="w-full rounded-lg border border-emerald-300 dark:border-emerald-600 bg-white dark:bg-gray-900 px-4 py-3 sm:py-2.5 text-base sm:text-sm" />
                </div>
            </div>

            {{-- Scan Input (Mobile Optimized) --}}
            <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                <input
                    id="quickadd-barcode"
                    wire:model.live="scanInput"
                    wire:keydown.enter="submitScan"
                    type="text"
                    placeholder="Scan barcode…"
                    inputmode="text"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    autofocus
                    class="flex-1 rounded-lg border border-emerald-300 dark:border-emerald-600 bg-white dark:bg-gray-900 px-4 py-3 sm:py-2.5 text-base sm:text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500 font-mono touch-manipulation"
                />
                <div class="flex gap-2 items-stretch sm:items-center w-full sm:w-auto">
                    @if($scanInput)
                    <button wire:click="clearScanInput" type="button"
                        class="flex-1 sm:flex-none px-3 py-3 sm:py-2.5 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-600 transition text-sm">
                        <x-heroicon-o-x-mark class="h-5 w-5 mx-auto" />
                    </button>
                    @endif
                    <button class="camera-scan-btn flex-1 px-3 py-3 rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 active:bg-emerald-700 transition font-medium text-sm" title="Scan with camera" type="button">
                        <x-heroicon-o-video-camera class="h-5 w-5 mx-auto" />
                    </button>
                    <button wire:click="submitScan" type="button"
                        class="flex-1 sm:flex-none px-4 py-3 sm:py-2.5 rounded-lg bg-emerald-600 text-base sm:text-sm font-medium text-white hover:bg-emerald-700 active:bg-emerald-800 transition">
                        Add
                    </button>
                </div>
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

            {{-- Manual Item Entry (for staged pallets without packing slip) --}}
            @if($stagingPalletId && !$showPackingSlipAnalysis)
            <button wire:click="openManualLineEntry" type="button"
                class="w-full rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500">
                <x-heroicon-o-plus class="h-4 w-4 inline -mt-0.5 mr-2" />
                Add Item Manually
            </button>
            @endif
        </div>

        {{-- Pending Items Summary --}}
        @if($rcvPalletId && !empty($this->getPendingItemsProperty()))
        <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950 px-6 py-4">
            <div class="flex items-center gap-2 mb-3">
                <x-heroicon-o-clock class="h-5 w-5 text-amber-600" />
                <h4 class="font-semibold text-amber-900 dark:text-amber-100">2️⃣ Pending Items Ready to Receive</h4>
                <span class="text-xs font-medium bg-amber-200 dark:bg-amber-800 text-amber-900 dark:text-amber-100 px-2 py-1 rounded">
                    {{ count($this->getPendingItemsProperty()) }} item(s) staged
                </span>
            </div>
            <p class="text-xs text-amber-700 dark:text-amber-300 mb-3">These items were staged but not yet in inventory. Scan barcodes below to confirm receipt and add to stock.</p>
            <div class="space-y-2">
                @foreach($this->getPendingItemsProperty() as $item)
                <div class="bg-white dark:bg-gray-800 rounded p-3 text-sm">
                    <div class="flex items-start justify-between mb-1">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">
                                {{ $item['item_name'] }}
                                @if($item['sku'])
                                <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $item['sku'] }}</span>
                                @endif
                            </p>
                            @if($item['vendor_description'])
                            <p class="text-xs text-gray-600 dark:text-gray-400">{{ $item['vendor_description'] }}</p>
                            @endif
                        </div>
                        @if($item['preflight_cost'] > 0)
                        <div class="text-right">
                            <p class="text-xs text-gray-600 dark:text-gray-400">Preflight Cost</p>
                            <p class="font-semibold text-amber-700 dark:text-amber-300">${{ number_format($item['preflight_cost'], 2) }}</p>
                        </div>
                        @endif
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-400">
                        {{ $item['case_count'] }} case(s) × {{ $item['quantity_per_case'] }} units
                    </p>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Scan Input (Mobile Optimized) --}}
        @if($rcvPalletId)
        <div class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950 px-4 sm:px-6 py-4 space-y-3">
            <p class="text-xs text-blue-700 dark:text-blue-300">
                <strong>3️⃣ Scan Items to Confirm Receipt</strong> — Scan item barcodes to mark pending items as received and add to inventory.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                <input
                    wire:model.live="scanInput"
                    wire:keydown.enter="submitScan"
                    type="text"
                    placeholder="Scan barcode or UPC and press Enter…"
                    inputmode="text"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    autofocus
                    class="flex-1 rounded-lg border border-blue-300 dark:border-blue-600 bg-white dark:bg-gray-900 px-4 py-3 sm:py-2.5 text-base sm:text-sm font-mono touch-manipulation"
                />
                <div class="flex gap-2 items-stretch sm:items-center w-full sm:w-auto">
                    @if($scanInput)
                    <button wire:click="clearScanInput" type="button"
                        class="flex-1 sm:flex-none px-3 py-3 sm:py-2.5 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-600 transition text-sm">
                        <x-heroicon-o-x-mark class="h-5 w-5 mx-auto" />
                    </button>
                    @endif
                    <button wire:click="submitScan" type="button"
                        class="flex-1 sm:flex-none px-4 py-3 sm:py-2.5 rounded-lg bg-blue-600 text-base sm:text-sm font-medium text-white hover:bg-blue-700 active:bg-blue-800 transition whitespace-nowrap">
                        <x-heroicon-o-check-circle class="h-5 w-5 inline -mt-0.5 mr-1" />
                        Receive
                    </button>
                </div>
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
                <h4 class="font-semibold text-gray-900 dark:text-white mb-4">Receiving Progress</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Pallet #</p>
                        <p class="font-semibold text-gray-900 dark:text-white text-sm">{{ $rcvProgress['reference'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Vendor</p>
                        <p class="font-semibold text-gray-900 dark:text-white text-sm">{{ $rcvProgress['vendor'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Items Received</p>
                        <p class="font-semibold text-gray-900 dark:text-white text-sm">{{ $rcvProgress['done_lines'] }}/{{ $rcvProgress['total_lines'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Overall Status</p>
                        <p class="font-semibold text-sm">
                            @if($rcvProgress['done_lines'] === $rcvProgress['total_lines'])
                                <span class="text-green-600">✓ Complete</span>
                            @else
                                <span class="text-blue-600">In Progress</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- Line Items Progress --}}
            <div>
                <h4 class="font-semibold text-gray-900 dark:text-white text-sm mb-3">Item Status (Scan barcodes to update)</h4>
                <div class="space-y-2">
                @foreach($rcvProgress['lines'] as $line)
                <div class="rounded-lg border transition-all duration-300 {{ $lastScannedLineId === $line['line_id'] ? 'border-blue-500 dark:border-blue-400 bg-blue-100 dark:bg-blue-900 ring-2 ring-blue-400 shadow-lg' : ($line['done'] ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950' : ($line['line_status'] === 'pending' ? 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950' : 'border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950')) }} px-4 py-3"
                    @if($lastScannedLineId === $line['line_id']) wire:key="scanned-{{ $line['line_id'] }}-{{ now()->timestamp }}" @endif
                >
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                @if($line['done'])
                                <x-heroicon-o-check-circle class="h-4 w-4 text-green-600" />
                                @elseif($line['line_status'] === 'pending')
                                <x-heroicon-o-clock class="h-4 w-4 text-amber-600" />
                                @elseif(!$line['is_mapped'])
                                <x-heroicon-o-exclamation-circle class="h-4 w-4 text-red-600" />
                                @else
                                <x-heroicon-o-clock class="h-4 w-4 text-yellow-600" />
                                @endif
                                <span class="font-medium text-sm">{{ $line['item_name'] }}</span>
                                @if($line['sku'])
                                <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $line['sku'] }}</span>
                                @endif
                                @if($line['line_status'] === 'pending')
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-200 dark:bg-amber-800 text-amber-900 dark:text-amber-100">Pending</span>
                                @elseif($line['done'])
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-green-200 dark:bg-green-800 text-green-900 dark:text-green-100">Received</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                {{ $line['received_cases'] }}/{{ $line['total_cases'] }} cases • ${{ number_format($line['unit_cost'], 2) }}/unit
                                @if(!$line['is_mapped'])
                                <span class="text-red-600">• ⚠ Unmapped</span>
                                @endif
                            </p>
                        </div>
                        <div class="text-right flex items-center gap-3">
                            @if($line['done'])
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-green-200 dark:bg-green-800 text-green-900 dark:text-green-100 text-xs font-medium">
                                <x-heroicon-o-check class="h-3 w-3" />
                                Done
                            </span>
                            @else
                            <button wire:click="openCostAdjust({{ $line['line_id'] ?? 0 }})" type="button" class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-800 rounded px-2 py-1">
                                <x-heroicon-o-pencil-square class="h-4 w-4" />
                            </button>
                            @endif
                            <div class="w-20 bg-gray-300 dark:bg-gray-600 rounded-full h-2 flex-shrink-0">
                                <div class="bg-gradient-to-r from-blue-500 to-green-500 h-2 rounded-full transition-all duration-500" style="width: {{ $line['total_cases'] > 0 ? ($line['received_cases'] / $line['total_cases']) * 100 : 0 }}%"></div>
                            </div>
                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 w-12 text-right">{{ round(($line['total_cases'] > 0 ? ($line['received_cases'] / $line['total_cases']) * 100 : 0), 0) }}%</span>
                        </div>
                    </div>
                </div>
                @endforeach
                </div>
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

        {{-- Manual Line Entry Modal --}}
        @if($showManualLineEntry && $stagingPalletId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-900">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2 mb-2">
                        <x-heroicon-o-plus-circle class="h-5 w-5 text-amber-600" />
                        2️⃣ Add Item to Pallet
                    </h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Manually add items when no packing slip is available. Fill in item details, location, and quantity. Items will be marked as "pending" until physically received and scanned.</p>
                </div>

                <div class="px-6 py-4 space-y-4">
                    {{-- Item Search/Selection --}}
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">
                            🔍 Item to Receive <span class="text-red-600">*</span>
                        </label>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">Search for existing item by name, SKU, or barcode. Can add new items later if needed.</p>
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.debounce-300ms="manualItemSearch"
                                wire:keyup="searchManualItems"
                                placeholder="e.g., 'Pokemon Box', 'SKU123', or barcode…"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:ring-2 focus:ring-amber-500"
                            />
                            @if($manualItemOptions && !empty($manualItemOptions))
                            <div class="absolute top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg z-10">
                                @foreach($manualItemOptions as $opt)
                                <button
                                    type="button"
                                    wire:click="selectManualItem({{ $opt['id'] }})"
                                    class="w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 border-b border-gray-200 dark:border-gray-700 last:border-b-0"
                                >
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $opt['name'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        @if($opt['sku']){{ 'SKU: ' . $opt['sku'] }}@endif
                                        @if($opt['barcode']) • {{ 'UPC: ' . $opt['barcode'] }}@endif
                                    </p>
                                </button>
                                @endforeach
                            </div>
                            @endif
                        </div>
                        @if($manualItemId)
                        <p class="text-xs text-green-600 dark:text-green-400 mt-1">✓ Item selected</p>
                        @endif
                    </div>

                    {{-- Location Selection --}}
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">
                            📍 Receive to Location <span class="text-red-600">*</span>
                        </label>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">Where items will be stored when received and added to inventory.</p>
                        <select
                            wire:model="manualLocationId"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:ring-2 focus:ring-amber-500"
                        >
                            <option value="">Select location…</option>
                            @foreach($this->getLocationsProperty() as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Case Count --}}
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">📦 Number of Cases</label>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">How many cases are expected in this shipment.</p>
                        <input
                            type="number"
                            wire:model="manualCaseCount"
                            min="1"
                            step="1"
                            placeholder="1"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:ring-2 focus:ring-amber-500"
                        />
                    </div>

                    {{-- Quantity per Case --}}
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">📊 Units per Case</label>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">Individual units/items in each case (e.g., 12 boxes per case).</p>
                        <input
                            type="number"
                            wire:model="manualQtyPerCase"
                            min="0.01"
                            step="0.01"
                            placeholder="1"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:ring-2 focus:ring-amber-500"
                        />
                    </div>

                    {{-- Unit Cost (Optional) --}}
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">💰 Actual Unit Cost ($) — Optional</label>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">Cost per unit once received. Used to calculate average cost and WAC. If blank, pulls from item's existing cost.</p>
                        <input
                            type="number"
                            wire:model="manualUnitCost"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:ring-2 focus:ring-amber-500"
                        />
                    </div>

                    {{-- Preflight Cost (Optional) --}}
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">📋 Preflight Cost ($) — Optional</label>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">Cost estimate from vendor packing slip or invoice. Reference only — doesn't affect inventory cost until item is actually received.</p>
                        <input
                            type="number"
                            wire:model="manualPreflightCost"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:ring-2 focus:ring-amber-500"
                        />
                    </div>

                    {{-- Description (Optional) --}}
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">📝 Notes/Description — Optional</label>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">Custom notes, vendor codes, packaging notes, etc.</p>
                        <input
                            type="text"
                            wire:model="manualDescription"
                            placeholder="e.g., 'Vendor packaging v2', 'Bulk order', etc."
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:ring-2 focus:ring-amber-500"
                        />
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex gap-2 sticky bottom-0 bg-white dark:bg-gray-900">
                    <button
                        wire:click="addManualLine"
                        type="button"
                        class="flex-1 rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500"
                    >
                        <x-heroicon-o-check class="h-4 w-4 inline -mt-0.5 mr-1" />
                        Add Item
                    </button>
                    <button
                        wire:click="cancelManualLineEntry"
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

    {{-- Auto-fade scanned item highlight after 2 seconds --}}
    <script>
    document.addEventListener('livewire:updated', function() {
        const highlighted = document.querySelector('[wire\\:key^="scanned-"]');
        if (highlighted) {
            setTimeout(function() {
                @this.dispatch('clearScannedHighlight');
            }, 2000);
        }
    });
    </script>

    {{-- Camera scanning scripts (uses global barcode scanner from app.js) --}}
    @if($mode === 'lookup' || $mode === 'receive' || $mode === 'quickadd')
    <script>
    (function () {
        let codeReader  = null;
        let stream      = null;
        let scanning    = false;
        let lastScanned = null;
        let boundButtons = new Set();

        function getInput(btn) {
            // Try to find input by ID first (quickadd-barcode)
            let input = document.getElementById('quickadd-barcode');
            if (input) return input;

            // Search from the button up through parents
            let parent = btn.parentElement;
            while (parent) {
                // Try multiple selectors for different wire:model variations
                input = parent.querySelector('input[wire\\:model="scanInput"]');
                if (input) return input;

                // Also check for wire:model.live (need to use getAttribute for this)
                const inputs = parent.querySelectorAll('input');
                for (let inp of inputs) {
                    if (inp.getAttribute('wire:model.live') === 'scanInput' ||
                        inp.getAttribute('wire:model') === 'scanInput') {
                        return inp;
                    }
                }
                parent = parent.parentElement;
            }

            // Fallback: search entire document
            const allInputs = Array.from(document.querySelectorAll('input'));
            return allInputs.find(el =>
                el.getAttribute('wire:model') === 'scanInput' ||
                el.getAttribute('wire:model.live') === 'scanInput' ||
                el.id === 'quickadd-barcode'
            );
        }

        async function handleCameraClick(btn) {
            try {
                console.log('[Camera] Button clicked');

                // Prevent opening camera if already scanning
                if (scanning) {
                    console.log('[Camera] Already scanning, ignoring');
                    return;
                }

                const input = getInput(btn);
                console.log('[Camera] Input found:', !!input, input?.id || input?.getAttribute('wire:model.live'));
                if (!input) {
                    console.error('[Camera] ERROR: Input field not found');
                    alert('Error: Input field not found');
                    return;
                }

                const container = document.getElementById('camera-container');
                const video = document.getElementById('camera-video');
                console.log('[Camera] Container found:', !!container, 'Video found:', !!video);

                if (!container || !video) {
                    console.error('[Camera] ERROR: Camera elements not found');
                    alert('Error: Camera elements not found');
                    return;
                }

                if (!window.barcodeScanner?.BrowserMultiFormatReader) {
                    console.error('[Camera] ERROR: Barcode scanner library not loaded');
                    alert('Barcode scanner library not loaded. Please refresh the page.');
                    return;
                }

                console.log('[Camera] Clearing previous streams...');
                // Reset video element and clear any previous stream
                video.srcObject = null;
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                    stream = null;
                }

                // Close old reader if it exists
                if (codeReader) {
                    try {
                        codeReader.reset();
                    } catch (e) {
                        console.log('[Camera] Previous reader cleanup:', e);
                    }
                }

                console.log('[Camera] Removing hidden class...');
                container.classList.remove('hidden');
                btn.classList.add('hidden');

                const loadingOverlay = document.getElementById('camera-loading');
                if (loadingOverlay) {
                    loadingOverlay.classList.remove('hidden');
                }

                scanning = true;
                console.log('[Camera] Creating BrowserMultiFormatReader...');
                codeReader = new window.barcodeScanner.BrowserMultiFormatReader();

                // Start scanning in background - don't wait for it
                console.log('[Camera] Starting decodeFromVideoDevice...');
                codeReader.decodeFromVideoDevice(
                    undefined,
                    video,
                    (result, err) => {
                        if (result && scanning) {
                            const barcode = result.getText();
                            console.log('[Camera] Barcode detected:', barcode);
                            if (barcode !== lastScanned) {
                                lastScanned = barcode;
                                input.value = barcode;
                                input.dispatchEvent(new Event('input', { bubbles: true }));
                                input.dispatchEvent(new Event('change', { bubbles: true }));
                                setTimeout(() => {
                                    input.dispatchEvent(new KeyboardEvent('keydown', {
                                        key: 'Enter',
                                        code: 'Enter',
                                        keyCode: 13,
                                        bubbles: true
                                    }));
                                }, 100);
                            }
                        }
                    }
                ).then(() => {
                    // Scanning completed (shouldn't happen until closed)
                    console.log('[Camera] Scanning completed');
                    if (loadingOverlay) {
                        loadingOverlay.classList.add('hidden');
                    }
                }).catch((error) => {
                    // Camera initialization error
                    console.error('[Camera] ERROR - Camera initialization failed:', error);
                    const container = document.getElementById('camera-container');
                    if (container) {
                        container.classList.add('hidden');
                    }
                    btn.classList.remove('hidden');
                    scanning = false;

                    if (codeReader) {
                        codeReader = null;
                    }

                    if (loadingOverlay) {
                        loadingOverlay.classList.add('hidden');
                    }

                    let message = 'Camera error: ';
                    if (error.name === 'NotAllowedError') {
                        message += 'Camera permission denied. Tap Settings > VortexOps > Camera > Allow.';
                    } else if (error.name === 'NotFoundError') {
                        message += 'No camera found on this device.';
                    } else {
                        message += (error.message || 'Unable to start camera');
                    }
                    alert(message);
                });

                // Hide loading overlay after reasonable time
                setTimeout(() => {
                    if (loadingOverlay && scanning) {
                        loadingOverlay.classList.add('hidden');
                    }
                }, 3000);

            } catch (error) {
                console.error('Camera click error:', error);
                const container = document.getElementById('camera-container');
                if (container) {
                    container.classList.add('hidden');
                }
                btn.classList.remove('hidden');
                scanning = false;

                if (codeReader) {
                    try {
                        codeReader.reset();
                    } catch (e) {
                        console.error('Error resetting reader:', e);
                    }
                    codeReader = null;
                }

                const video = document.getElementById('camera-video');
                if (video) {
                    video.srcObject = null;
                }

                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                    stream = null;
                }

                alert('Error opening camera: ' + (error.message || 'Unknown error'));
            }
        }

        function bindCameraButtons() {
            console.log('[Camera] bindCameraButtons() called');
            const buttons = document.querySelectorAll('.camera-scan-btn');
            console.log('[Camera] Found', buttons.length, 'camera buttons');

            buttons.forEach((btn) => {
                if (boundButtons.has(btn)) {
                    console.log('[Camera] Button already bound, skipping');
                    return;
                }

                boundButtons.add(btn);
                btn.classList.remove('hidden');
                console.log('[Camera] Button bound and made visible');

                btn.addEventListener('pointerdown', (e) => {
                    console.log('[Camera] pointerdown event');
                    e.preventDefault();
                    e.stopPropagation();
                    handleCameraClick(btn);
                });

                btn.addEventListener('click', (e) => {
                    console.log('[Camera] click event');
                    e.preventDefault();
                    e.stopPropagation();
                    handleCameraClick(btn);
                });
            });

            const stopBtn = document.getElementById('camera-stop-btn');
            if (stopBtn && !stopBtn.dataset.stopBound) {
                stopBtn.dataset.stopBound = '1';
                stopBtn.addEventListener('click', function stopCamera() {
                    scanning = false;
                    if (codeReader) {
                        try {
                            codeReader.reset();
                        } catch (e) {
                            console.error('Error resetting reader:', e);
                        }
                        codeReader = null;
                    }
                    if (stream) {
                        stream.getTracks().forEach(track => track.stop());
                        stream = null;
                    }
                    const video = document.getElementById('camera-video');
                    if (video) {
                        video.srcObject = null;
                    }
                    const container = document.getElementById('camera-container');
                    if (container) {
                        container.classList.add('hidden');
                    }

                    document.querySelectorAll('.camera-scan-btn').forEach(btn => {
                        btn.classList.remove('hidden');
                    });
                });
            }
        }

        console.log('[Camera] Script loaded, readyState:', document.readyState);

        if (document.readyState === 'loading') {
            console.log('[Camera] DOM still loading, waiting for DOMContentLoaded');
            document.addEventListener('DOMContentLoaded', () => {
                console.log('[Camera] DOMContentLoaded fired, calling bindCameraButtons');
                bindCameraButtons();
            });
        } else {
            console.log('[Camera] DOM already loaded, calling bindCameraButtons immediately');
            bindCameraButtons();
        }

        // Re-bind camera buttons after Livewire updates (mode switches, form changes, etc.)
        document.addEventListener('livewire:updated', () => {
            console.log('[Camera] livewire:updated fired');
            boundButtons.clear();
            console.log('[Camera] Cleared boundButtons, rebinding...');
            setTimeout(() => {
                console.log('[Camera] Calling bindCameraButtons after 100ms');
                const buttons = document.querySelectorAll('.camera-scan-btn');
                if (buttons.length > 0) {
                    bindCameraButtons();
                } else {
                    console.log('[Camera] No buttons found, skipping bind');
                }
            }, 100);
        });

        // Also handle navigation events from Livewire routing
        document.addEventListener('livewire:navigating', () => {
            const stopBtn = document.getElementById('camera-stop-btn');
            if (stopBtn && !stopBtn.closest('.hidden')) {
                stopBtn.click();
            }
        });

        // Stop camera when navigating away
        window.addEventListener('livewire:navigating', () => {
            scanning = false;
            if (codeReader) {
                try { codeReader.reset(); } catch (e) {}
                codeReader = null;
            }
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
        });
    })();

    // Mobile Scanner Enhancement: Handle paste events and haptic feedback
    (function () {
        const boundInputs = new WeakSet();

        function setupInputListeners() {
            const inputs = Array.from(document.querySelectorAll('input')).filter(input =>
                input.getAttribute('wire:model') === 'scanInput' ||
                input.getAttribute('wire:model.live') === 'scanInput'
            );

            inputs.forEach(input => {
                if (boundInputs.has(input)) {
                    return;
                }
                boundInputs.add(input);

                input.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const text = (e.clipboardData || window.clipboardData).getData('text');
                    if (text.trim()) {
                        input.value = text.trim();
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        input.dispatchEvent(new Event('change', { bubbles: true }));

                        if (navigator.vibrate) {
                            navigator.vibrate([50, 30, 50]);
                        }

                        setTimeout(() => {
                            const enterEvent = new KeyboardEvent('keydown', {
                                code: 'Enter',
                                key: 'Enter',
                                keyCode: 13,
                                which: 13,
                                bubbles: true,
                                cancelable: true
                            });
                            input.dispatchEvent(enterEvent);
                        }, 100);
                    }
                });

                input.addEventListener('input', (e) => {
                    e.target.focus();
                });
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupInputListeners);
        } else {
            setupInputListeners();
        }

        document.addEventListener('livewire:updated', setupInputListeners);

        document.addEventListener('livewire:updated', ({ detail }) => {
            if (detail.succeed && navigator.vibrate) {
                navigator.vibrate(150);
            }
        });
    })();

    // Register service worker for offline support
    (function () {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/inventory-scanner-sw.js', { scope: '/admin/inventory/scanner' })
                    .catch((error) => {
                        console.error('Scanner Service Worker registration failed:', error);
                    });
            });
        }
    })();

    // Mobile optimization: Prevent double-tap zoom on buttons
    (function () {
        document.addEventListener('touchend', (e) => {
            if (e.target.tagName === 'BUTTON' || e.target.closest('button')) {
                e.preventDefault();
            }
        }, { passive: false });
    })();
    </script>
    @endif

</x-filament-panels::page>
