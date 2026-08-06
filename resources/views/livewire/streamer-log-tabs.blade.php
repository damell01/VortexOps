<div class="space-y-6">
    <!-- Workflow Progress -->
    <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <!-- Workflow Steps -->
            <div class="flex flex-col md:flex-row items-center justify-between flex-1 gap-4">
                <!-- Streamer Step -->
                <div class="flex flex-col items-center">
                    <div class="w-12 h-12 rounded-full {{ $record->status === 'pending' ? 'bg-blue-500' : 'bg-green-500' }} flex items-center justify-center text-white font-bold mb-2">
                        1
                    </div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Streamer</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Logging Items</p>
                </div>

                <!-- Arrow -->
                <div class="hidden md:block flex-1 h-1 {{ $record->status !== 'pending' ? 'bg-green-500' : 'bg-gray-300' }} mx-2"></div>

                <!-- Admin Step -->
                <div class="flex flex-col items-center">
                    <div class="w-12 h-12 rounded-full {{ in_array($record->status, ['streamer_reviewed', 'admin_approved']) ? 'bg-blue-500' : 'bg-gray-300' }} flex items-center justify-center text-white font-bold mb-2">
                        2
                    </div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Admin</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Review & Approve</p>
                </div>

                <!-- Arrow -->
                <div class="hidden md:block flex-1 h-1 {{ $record->status === 'admin_approved' ? 'bg-green-500' : 'bg-gray-300' }} mx-2"></div>

                <!-- Fulfillment Step -->
                <div class="flex flex-col items-center">
                    <div class="w-12 h-12 rounded-full {{ $record->status === 'admin_approved' ? 'bg-blue-500' : 'bg-gray-300' }} flex items-center justify-center text-white font-bold mb-2">
                        3
                    </div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Fulfillment</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Ship & Track</p>
                </div>
            </div>

            <!-- Status Badge -->
            <div class="ml-4">
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold
                    @switch($record->status)
                        @case('pending')
                            bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300
                            @break
                        @case('streamer_reviewed')
                            bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300
                            @break
                        @case('admin_approved')
                            bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300
                            @break
                        @default
                            bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300
                    @endswitch
                ">
                    {{ ucwords(str_replace('_', ' ', $record->status)) }}
                </span>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 border-b-0">
        <div class="flex flex-wrap border-b border-gray-200 dark:border-gray-700">
            <!-- Overview Tab -->
            <button wire:click="setTab('overview')"
                    class="flex-1 md:flex-none px-6 py-4 font-medium text-center border-b-2 transition
                    {{ $activeTab === 'overview'
                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                        : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200' }}">
                📊 Overview
            </button>

            <!-- Items Tab -->
            <button wire:click="setTab('items')"
                    class="flex-1 md:flex-none px-6 py-4 font-medium text-center border-b-2 transition
                    {{ $activeTab === 'items'
                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                        : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200' }}">
                📦 Items ({{ $orders->count() }})
            </button>

            <!-- Costs Tab -->
            <button wire:click="setTab('costs')"
                    class="flex-1 md:flex-none px-6 py-4 font-medium text-center border-b-2 transition
                    {{ $activeTab === 'costs'
                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                        : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200' }}">
                💰 Costs & Profit
            </button>

            <!-- Fulfillment Tab -->
            <button wire:click="setTab('fulfillment')"
                    class="flex-1 md:flex-none px-6 py-4 font-medium text-center border-b-2 transition
                    {{ $activeTab === 'fulfillment'
                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                        : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200' }}">
                🚚 Fulfillment
            </button>

            <!-- Activity Tab -->
            <button wire:click="setTab('activity')"
                    class="flex-1 md:flex-none px-6 py-4 font-medium text-center border-b-2 transition
                    {{ $activeTab === 'activity'
                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                        : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200' }}">
                📝 Activity
            </button>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- Overview Tab -->
            @if($activeTab === 'overview')
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Revenue Card -->
                        <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/20 dark:to-emerald-900/10 rounded-lg p-4 border border-emerald-200 dark:border-emerald-800">
                            <p class="text-sm text-emerald-800 dark:text-emerald-300 font-medium">Gross Revenue</p>
                            <p class="text-3xl font-bold text-emerald-900 dark:text-emerald-100 mt-2">${{ number_format($show?->gross_revenue ?? 0, 2) }}</p>
                        </div>

                        <!-- Product Cost Card -->
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-900/10 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                            <p class="text-sm text-blue-800 dark:text-blue-300 font-medium">Product Cost</p>
                            <p class="text-3xl font-bold text-blue-900 dark:text-blue-100 mt-2">${{ number_format($record->product_cost ?? 0, 2) }}</p>
                        </div>

                        <!-- Profit Share Card -->
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-900/10 rounded-lg p-4 border border-purple-200 dark:border-purple-800">
                            <p class="text-sm text-purple-800 dark:text-purple-300 font-medium">Profit Share</p>
                            <p class="text-3xl font-bold text-purple-900 dark:text-purple-100 mt-2">${{ number_format($record->profit_share_amount ?? 0, 2) }}</p>
                        </div>

                        <!-- Items Count Card -->
                        <div class="bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900/20 dark:to-orange-900/10 rounded-lg p-4 border border-orange-200 dark:border-orange-800">
                            <p class="text-sm text-orange-800 dark:text-orange-300 font-medium">Items Sold</p>
                            <p class="text-3xl font-bold text-orange-900 dark:text-orange-100 mt-2">{{ $orders->count() }}</p>
                        </div>
                    </div>

                    <!-- Next Action -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 rounded p-4">
                        <p class="font-semibold text-blue-900 dark:text-blue-100 mb-2">Next Action Required</p>
                        @if($record->status === 'pending')
                            <p class="text-sm text-blue-800 dark:text-blue-300">📋 Add items sold and complete the form, then submit for admin review.</p>
                        @elseif($record->status === 'streamer_reviewed')
                            <p class="text-sm text-blue-800 dark:text-blue-300">👤 Awaiting admin review and approval. This usually takes 1-2 hours.</p>
                        @elseif($record->status === 'admin_approved')
                            <p class="text-sm text-blue-800 dark:text-blue-300">🚚 Ready for fulfillment! The fulfillment team will process shipments.</p>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Items Tab -->
            @if($activeTab === 'items')
                <div class="space-y-4">
                    @forelse($orders as $order)
                        <div class="flex items-start gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 dark:text-white">{{ $order->inventoryItem?->name ?? 'Unknown Item' }}</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">SKU: {{ $order->inventoryItem?->sku ?? 'N/A' }}</p>
                                @if($order->inventoryItem?->category)
                                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-2">{{ $order->inventoryItem->category }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-600 dark:text-gray-400">Quantity</p>
                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $order->quantity }}</p>
                            </div>
                            <div class="text-right border-l border-gray-300 dark:border-gray-600 pl-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400">Unit Cost</p>
                                <p class="text-lg font-bold text-gray-900 dark:text-white">${{ number_format($order->unit_cost, 2) }}</p>
                            </div>
                            <div class="text-right border-l border-gray-300 dark:border-gray-600 pl-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400">Total</p>
                                <p class="text-lg font-bold text-blue-600 dark:text-blue-400">${{ number_format($order->total_cost, 2) }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                            <p>No items logged yet</p>
                        </div>
                    @endforelse
                </div>
            @endif

            <!-- Costs Tab -->
            @if($activeTab === 'costs')
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-6 border border-emerald-200 dark:border-emerald-800">
                            <h4 class="font-semibold text-emerald-900 dark:text-emerald-100 mb-4">Revenue</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-emerald-800 dark:text-emerald-300">Gross Revenue</span>
                                    <span class="font-semibold text-emerald-900 dark:text-emerald-100">${{ number_format($show?->gross_revenue ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-6 border border-red-200 dark:border-red-800">
                            <h4 class="font-semibold text-red-900 dark:text-red-100 mb-4">Costs</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-red-800 dark:text-red-300">Product Cost</span>
                                    <span class="font-semibold text-red-900 dark:text-red-100">${{ number_format($record->product_cost ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-6 border border-purple-200 dark:border-purple-800">
                        <h4 class="font-semibold text-purple-900 dark:text-purple-100 mb-4">Profit Distribution</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center pb-3 border-b border-purple-200 dark:border-purple-800">
                                <span class="text-purple-800 dark:text-purple-300">Profit Share Amount</span>
                                <span class="text-2xl font-bold text-purple-900 dark:text-purple-100">${{ number_format($record->profit_share_amount ?? 0, 2) }}</span>
                            </div>
                            <p class="text-sm text-purple-700 dark:text-purple-400">Calculated based on gross revenue and product costs</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Fulfillment Tab -->
            @if($activeTab === 'fulfillment')
                <div class="space-y-6">
                    @if($shipments->count() > 0)
                        <div class="space-y-4">
                            @foreach($shipments as $shipment)
                                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <div class="flex items-start justify-between mb-3">
                                        <div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white">{{ $shipment->buyer_username }}</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $shipment->item_count }} items</p>
                                        </div>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold
                                            @if($shipment->status === 'shipped')
                                                bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300
                                            @else
                                                bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300
                                            @endif
                                        ">
                                            {{ ucfirst($shipment->status) }}
                                        </span>
                                    </div>
                                    @if($shipment->tracking_number)
                                        <div class="bg-white dark:bg-gray-700 p-3 rounded border border-gray-300 dark:border-gray-600">
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Tracking Number</p>
                                            <p class="font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ $shipment->tracking_number }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ ucfirst($shipment->carrier) }}</p>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12 bg-gray-50 dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700">
                            <svg class="w-16 h-16 mx-auto mb-3 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                            </svg>
                            <p class="text-gray-600 dark:text-gray-400 font-medium">No shipments yet</p>
                            <p class="text-sm text-gray-500 dark:text-gray-500 mt-1">Fulfillment team will create shipments after approval</p>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Activity Tab -->
            @if($activeTab === 'activity')
                <div class="space-y-4">
                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <div class="w-4 h-4 rounded-full bg-blue-500 mt-2"></div>
                            <div class="w-1 bg-gray-300 dark:bg-gray-700" style="height: 60px;"></div>
                        </div>
                        <div class="pb-4">
                            <p class="font-semibold text-gray-900 dark:text-white">Created</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $show?->created_at?->format('M d, Y g:i A') ?? 'N/A' }}</p>
                        </div>
                    </div>

                    @if($record->submitted_at)
                        <div class="flex gap-4">
                            <div class="flex flex-col items-center">
                                <div class="w-4 h-4 rounded-full bg-green-500 mt-2"></div>
                                <div class="w-1 bg-gray-300 dark:bg-gray-700" style="height: 60px;"></div>
                            </div>
                            <div class="pb-4">
                                <p class="font-semibold text-gray-900 dark:text-white">Submitted</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $record->submitted_at->format('M d, Y g:i A') }}</p>
                            </div>
                        </div>
                    @endif

                    @if($record->locked_at)
                        <div class="flex gap-4">
                            <div class="flex flex-col items-center">
                                <div class="w-4 h-4 rounded-full bg-purple-500 mt-2"></div>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Locked for Review</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $record->locked_at->format('M d, Y g:i A') }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
