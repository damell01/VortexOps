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
    @items-added.window="itemsAdded()"
>
    @if($show)
    <div class="mb-6">
        <!-- Show Header Card - Compact -->
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $show->title ?? 'Untitled Show' }}</h2>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">{{ $show->show_date?->format('M d, Y g:i A') ?? 'Date not set' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5">Channel</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $show->channel?->name ?? 'Unknown' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5">Revenue</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $show->gross_revenue ? '$' . number_format($show->gross_revenue, 2) : 'Not set' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5">Status</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white capitalize">{{ str_replace('_', ' ', $record->status) }}</p>
                    </div>
                </div>

                <!-- Status Badges -->
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
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
                        @switch($record->status)
                            @case('pending')
                                🔄 Pending Admin Review
                                @break
                            @case('streamer_reviewed')
                                👀 Awaiting Admin Review
                                @break
                            @case('admin_approved')
                                ✓ Admin Approved
                                @break
                            @default
                                {{ $record->status }}
                        @endswitch
                    </span>

                    @if($record->isSubmitted())
                        @if($record->isLocked())
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">
                                🔒 Locked
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300">
                                🔓 Open for Edits
                            </span>
                        @endif

                        @if($record->canStreamerEdit())
                            @php
                                $minutesLeft = $record->getMinutesUntilEditWindowCloses();
                                $hoursLeft = floor($minutesLeft / 60);
                                $minsLeft = $minutesLeft % 60;
                            @endphp
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">
                                ⏱️ {{ $hoursLeft }}h {{ $minsLeft }}m left to edit
                            </span>
                        @elseif($record->submitted_at)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300">
                                ❌ Edit window closed
                            </span>
                        @endif
                    @endif

                    @if($record->approval_status === 'approved')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300">
                            ✅ Approved by Admin
                        </span>
                    @elseif($record->approval_status === 'rejected')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300">
                            ⛔ Rejected - Needs Revision
                        </span>
                    @elseif($record->approval_status === 'pending_approval')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300">
                            👤 Awaiting Admin Approval
                        </span>
                    @endif
                </div>

                @if($record->isSubmitted())
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Submitted</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $record->submitted_at?->format('M d, Y g:i A') ?? '—' }}</p>
                        </div>
                        @if($record->locked_at)
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Locked</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $record->locked_at->format('M d, Y g:i A') }}</p>
                        </div>
                        @endif
                        @if($record->approval_notes)
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Notes</p>
                            <p class="font-medium text-gray-900 dark:text-white italic">{{ \Str::limit($record->approval_notes, 50) }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Workflow Status -->
    <div class="mb-6">
        @livewire('end-of-stream-form', [
            'log' => $record,
        ], key('end-of-stream-' . $record->id))
    </div>

    <!-- Fulfillment Dashboard -->
    @if(auth()->user()?->isFulfillment() || auth()->user()?->isFulfillmentAdmin() || auth()->user()?->isOwner())
    <div class="mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">📦 Fulfillment Center</h3>
            @livewire('fulfillment-dashboard', [
                'show' => $show,
            ], key('fulfillment-' . $record->id))
        </div>
    </div>
    @endif

    <!-- Tabbed Interface -->
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

                    <!-- Inline Items Catalog -->
                    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <h5 class="font-semibold text-gray-900 dark:text-white mb-4">
                            {{ $totalItems > 0 ? 'Add More Items' : 'Add Items' }}
                        </h5>
                        @livewire('streamer-log-items-modal', [
                            'recordId' => $record->id,
                            'title' => 'Select Items Sold',
                            'description' => 'Search and select inventory items for this show',
                            'multiSelect' => true,
                            'allowQuantityInput' => true,
                            'allowCostInput' => true,
                            'allowCreateItem' => true,
                            'successEvent' => 'items-added',
                        ], key('items-inline-' . $record->id))
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

    <script>
        function wizardData() {
            return {
                activeTab: 'items',
                itemsAdded() {
                    window.location.reload();
                }
            }
        }
    </script>
</x-filament-panels::page>
