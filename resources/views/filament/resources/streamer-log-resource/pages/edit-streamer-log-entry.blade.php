@php
    use App\Models\StreamerLogEntry;
    use App\Filament\Resources\StreamerLogResource;

    /** @var StreamerLogEntry $record */
    $record = $this->record;
    $show = $record->show;
    $isStreamer = auth()->user()?->isStreamer() && !auth()->user()?->isAdmin();

    $orders = $record->show?->orders()
        ->with(['inventoryItem'])
        ->whereNotNull('inventory_item_id')
        ->get() ?? collect();
    $totalItems = $orders->count();
    $totalQuantity = $orders->sum('quantity');
    $totalCost = $orders->sum('total_cost');
    $canEdit = !$isStreamer || !StreamerLogResource::isLockedForCurrentUser($record);
@endphp

<x-filament-panels::page
    x-data="wizardData()"
    @items-added.window="itemsAdded(); showItemsModal = false"
>
    @if($show)
    <!-- Compact Header Row -->
    <div class="mb-4 bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-4 py-3">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex-1 min-w-0">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white truncate">{{ $show->title ?? 'Untitled Show' }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $show->show_date?->format('M d, Y g:i A') ?? 'Date not set' }} • {{ $show->channel?->name ?? 'Unknown' }}</p>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <div class="text-right">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Revenue</p>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $show->gross_revenue ? '$' . number_format($show->gross_revenue, 2) : 'N/A' }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Status</p>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
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
                    ">{{ str_replace('_', ' ', $record->status) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabbed Interface - Items First -->
    @if($canEdit)
    <div class="mb-6">
        <!-- Tabs -->
        <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 mb-0">
            <div class="flex gap-1 px-6">
                <button
                    @click="activeTab = 'items'"
                    :class="{
                        'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400': activeTab === 'items',
                        'border-b-2 border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300': activeTab !== 'items',
                    }"
                    class="py-3 px-3 font-medium text-sm transition"
                >
                    📦 Items
                </button>
                <button
                    @click="activeTab = 'details'"
                    :class="{
                        'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400': activeTab === 'details',
                        'border-b-2 border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300': activeTab !== 'details',
                    }"
                    class="py-3 px-3 font-medium text-sm transition"
                >
                    📋 Details
                </button>
                <button
                    @click="activeTab = 'review'"
                    :class="{
                        'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400': activeTab === 'review',
                        'border-b-2 border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300': activeTab !== 'review',
                    }"
                    class="py-3 px-3 font-medium text-sm transition"
                >
                    ✓ Review
                </button>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="bg-white dark:bg-gray-800 rounded-b-lg border-x border-b border-gray-200 dark:border-gray-700 p-4">
            <!-- Tab 1: Items & Summary -->
            <div x-show="activeTab === 'items'" x-transition>
                <!-- Quick Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 border border-blue-200 dark:border-blue-800">
                        <p class="text-xs text-gray-600 dark:text-gray-400">Revenue</p>
                        <p class="text-lg font-bold text-blue-600 dark:text-blue-400 mt-0.5">${{ number_format((float) $record->gross_revenue, 2) }}</p>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-3 border border-purple-200 dark:border-purple-800">
                        <p class="text-xs text-gray-600 dark:text-gray-400">Product Cost</p>
                        <p class="text-lg font-bold text-purple-600 dark:text-purple-400 mt-0.5">${{ number_format((float) $record->product_cost, 2) }}</p>
                    </div>
                    <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-3 border border-amber-200 dark:border-amber-800">
                        <p class="text-xs text-gray-600 dark:text-gray-400">Items Logged</p>
                        <p class="text-lg font-bold text-amber-600 dark:text-amber-400 mt-0.5">{{ $totalItems }}</p>
                    </div>
                </div>

                <!-- Items Section -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Items Sold</h4>
                        @if($totalItems > 0)
                            <div class="flex gap-3">
                                <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded-full text-sm font-medium">
                                    {{ $totalItems }} item{{ $totalItems !== 1 ? 's' : '' }}
                                </span>
                                <span class="px-3 py-1 bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300 rounded-full text-sm font-medium">
                                    {{ $totalQuantity }} unit{{ $totalQuantity !== 1 ? 's' : '' }}
                                </span>
                            </div>
                        @endif
                    </div>

                    @if($totalItems > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                            @foreach($orders as $order)
                                @php $item = $order->inventoryItem; @endphp
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                                    <h5 class="font-semibold text-gray-900 dark:text-white text-sm mb-2">{{ $item?->name ?? 'Unknown' }}</h5>
                                    @if($item?->sku)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 font-mono mb-2">{{ $item->sku }}</p>
                                    @endif
                                    <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400">
                                        <span>Qty: <span class="font-bold">{{ $order->quantity }}</span></span>
                                        <span>Total: <span class="font-bold">${{ number_format($order->total_cost, 2) }}</span></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 bg-gray-50 dark:bg-gray-700/50 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600">
                            <p class="text-gray-600 dark:text-gray-400">No items logged yet</p>
                        </div>
                    @endif

                    <!-- Add Items Button -->
                    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <button
                            @click="showItemsModal = true"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ $totalItems > 0 ? 'Add More Items' : 'Add Items' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Details -->
            <div x-show="activeTab === 'details'" x-transition>
                <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                    <p class="text-xs text-blue-800 dark:text-blue-200">
                        Fill in the details about your stream. All required fields must be completed before you can submit.
                    </p>
                </div>

                {{ $this->form }}
            </div>

            <!-- Tab 3: Review -->
            <div x-show="activeTab === 'review'" x-transition>

                <!-- Summary -->
                <div class="space-y-3 mb-4">
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-900/10 rounded-lg p-3 border border-blue-200 dark:border-blue-800">
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-0.5">Show</p>
                        <p class="text-base font-bold text-gray-900 dark:text-white">{{ $show->title }}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="bg-gradient-to-r from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-900/10 rounded-lg p-3 border border-purple-200 dark:border-purple-800">
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-0.5">Items Logged</p>
                            <p class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ $totalItems }}</p>
                        </div>
                        <div class="bg-gradient-to-r from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-900/10 rounded-lg p-3 border border-green-200 dark:border-green-800">
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-0.5">Total Item Cost</p>
                            <p class="text-lg font-bold text-green-600 dark:text-green-400">${{ number_format($totalCost, 2) }}</p>
                        </div>
                        <div class="bg-gradient-to-r from-amber-50 to-amber-100 dark:from-amber-900/20 dark:to-amber-900/10 rounded-lg p-3 border border-amber-200 dark:border-amber-800">
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-0.5">Hours Streamed</p>
                            <p class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ number_format((float) $record->hours_streamed, 2) }} hrs</p>
                        </div>
                    </div>

                    <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                        <p class="text-xs font-semibold text-green-800 dark:text-green-200 mb-0.5">✓ Ready to Submit</p>
                        <p class="text-xs text-green-700 dark:text-green-300">All information looks correct. Click submit to send for admin review.</p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex justify-end gap-3">
                        @foreach($this->getFormActions() as $action)
                            {{ $action }}
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    @else
    <!-- View Only Mode -->
    <div class="mb-8">
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <strong>View Only Mode:</strong> This log entry is locked for editing. Use the "Add Items" button to add new items, or request edit permission if you need to make changes.
            </p>
        </div>
    </div>
    @endif
    @endif

    <!-- Full-Screen Items Modal Overlay -->
    <div x-show="showItemsModal" class="fixed inset-0 z-50 overflow-hidden" style="display: none;">
        <!-- Backdrop -->
        <div x-show="showItemsModal" @click="showItemsModal = false" class="absolute inset-0 bg-black/50"></div>

        <!-- Modal Content -->
        <div class="relative h-full flex flex-col bg-white dark:bg-slate-900">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Select Items Sold</h1>
                        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">Search and select inventory items for this show</p>
                    </div>
                    <button @click="showItemsModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 flex-shrink-0 ml-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Livewire Component Content Area -->
            <div class="flex-1 overflow-hidden flex flex-col">
                @livewire('streamer-log-items-modal', [
                    'recordId' => $record->id,
                    'title' => 'Select Items Sold',
                    'description' => 'Search and select inventory items for this show',
                    'multiSelect' => true,
                    'allowQuantityInput' => true,
                    'allowCostInput' => true,
                    'allowCreateItem' => true,
                    'successEvent' => 'items-added',
                ], key('items-modal-' . $record->id))
            </div>
        </div>
    </div>

    <script>
        function wizardData() {
            return {
                activeTab: 'items',
                showItemsModal: false,
                itemsAdded() {
                    window.location.reload();
                }
            }
        }
    </script>
</x-filament-panels::page>
