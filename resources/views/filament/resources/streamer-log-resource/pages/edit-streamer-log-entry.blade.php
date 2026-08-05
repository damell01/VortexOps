@php
    use App\Models\StreamerLogEntry;

    /** @var StreamerLogEntry $record */
    $record = $this->record;
    $show = $record->show;
    $isStreamer = auth()->user()?->isStreamer() && !auth()->user()?->isAdmin();
@endphp

<x-filament-panels::page
    x-data="itemsModal()"
    @open-items-modal.window="openModal()"
    @items-added.window="itemsAdded()"
    @closeModal.window="closeModal()"
>
    @if($show)
    <div class="mb-8">
        <!-- Show Header Card -->
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">{{ $show->title ?? 'Untitled Show' }}</h2>
                        <p class="text-gray-600 dark:text-gray-400">{{ $show->show_date?->format('M d, Y - g:i A') ?? 'Date not set' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Channel</p>
                        <p class="text-lg font-medium text-gray-900 dark:text-white">{{ $show->channel ?? 'Unknown' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Gross Revenue</p>
                        <p class="text-lg font-medium text-gray-900 dark:text-white">{{ $show->gross_revenue ? '$' . number_format($show->gross_revenue, 2) : 'Not set' }}</p>
                    </div>
                </div>

                <!-- Status Badges -->
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Main Status Badge -->
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
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

                    <!-- Submission Status -->
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

                        <!-- Edit Window Countdown -->
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

                    <!-- Approval Status -->
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

                <!-- Submission Timeline -->
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

    <!-- Items Sold Section -->
    @php
        $orders = $record->show?->orders()
            ->with(['inventoryItem'])
            ->whereNotNull('inventory_item_id')
            ->get() ?? collect();
        $totalItems = $orders->count();
        $totalQuantity = $orders->sum('quantity');
        $totalCost = $orders->sum('total_cost');
    @endphp

    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Items Sold</h3>
                @if($totalItems > 0)
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Total cost: <span class="font-semibold">${{ number_format($totalCost, 2) }}</span></p>
                @endif
            </div>
            <div class="flex gap-3 items-center">
                @if($totalItems > 0)
                    <div class="flex gap-4 text-sm">
                        <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded-full font-medium">
                            {{ $totalItems }} {{ Str::plural('item', $totalItems) }}
                        </span>
                        <span class="px-3 py-1 bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300 rounded-full font-medium">
                            {{ $totalQuantity }} {{ Str::plural('unit', $totalQuantity) }}
                        </span>
                    </div>
                @endif
                @if(!$isStreamer || $record->canStreamerEdit())
                <button
                    @click="$dispatch('open-items-modal')"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-medium inline-flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd" />
                    </svg>
                    Add Items
                </button>
                @endif
            </div>
        </div>

        @if($totalItems > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($orders as $order)
                    @php
                        $item = $order->inventoryItem;
                    @endphp
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:shadow-md transition-shadow group">
                        <div class="mb-3 flex justify-between items-start">
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 dark:text-white text-sm">{{ $item?->name ?? 'Unknown Item' }}</h4>
                                @if($item?->sku)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $item->sku }}</p>
                                @endif
                            </div>
                            @if(!$isStreamer)
                            <button
                                type="button"
                                wire:click="removeItem({{ $order->id }})"
                                class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 opacity-0 group-hover:opacity-100 transition"
                                title="Remove item"
                            >
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            @endif
                        </div>

                        @if($item?->brand)
                            <p class="text-xs text-gray-600 dark:text-gray-300 mb-2">
                                <span class="font-medium">Brand:</span> {{ $item->brand }}
                            </p>
                        @endif

                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Quantity</p>
                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $order->quantity }}</p>
                            </div>
                            @if($order->unit_cost)
                                <div class="text-right">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Unit Cost</p>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">${{ number_format($order->unit_cost, 2) }}</p>
                                </div>
                            @endif
                        </div>

                        <div class="pt-3 border-t border-gray-200 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/10 -mx-4 px-4 py-3 rounded-b flex justify-between items-center">
                            @if($item?->category)
                                <span class="inline-block px-2 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded">
                                    {{ $item->category }}
                                </span>
                            @endif
                            <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">
                                Total: ${{ number_format($order->total_cost ?? 0, 2) }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12 bg-gray-50 dark:bg-gray-800/50 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600">
                <svg class="w-16 h-16 mx-auto mb-3 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <p class="text-gray-600 dark:text-gray-400 font-medium">No items logged yet</p>
                <p class="text-sm text-gray-500 dark:text-gray-500 mt-1">Click "Add Items" to start logging items sold</p>
            </div>
        @endif
    </div>

    <!-- Items Modal (Livewire) -->
    <div wire:key="items-modal-{{ $record->id }}" class="bg-black/50 justify-center items-start lg:items-center" id="items-modal" @keydown.escape="$dispatch('closeModal')" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 50; padding: 1rem; overflow-y: auto;">
        <div style="width: 100%; max-width: 56rem;">
            @livewire('streamer-log-items-modal', [
                'recordId' => $record->id,
                'title' => 'Add Items to Show: ' . ($record->show?->title ?? 'Untitled'),
                'description' => 'Select inventory items and set quantities for this show',
                'multiSelect' => true,
                'allowQuantityInput' => true,
                'allowCostInput' => true,
                'allowCreateItem' => true,
                'successEvent' => 'items-added',
            ], key('items-modal-' . $record->id))
        </div>
    </div>

    <!-- Edit Form Section (visible to admins only or when unlocked) -->
    @if(!$isStreamer || !$this->getRecord())
    <div class="mb-8 border-t border-gray-200 dark:border-gray-700 pt-8">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Log Details</h3>
        {{ $this->form }}
    </div>
    @elseif($isStreamer && StreamerLogResource::isLockedForCurrentUser($record))
    <div class="mt-8 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
        <p class="text-sm text-blue-800 dark:text-blue-300">
            <strong>View Only Mode:</strong> This log entry is locked for editing. Use the "End of Stream" form to add new items, or request edit permission if you need to make changes.
        </p>
    </div>
    @endif

    <!-- Form Actions -->
    <div class="mt-8 flex gap-3 justify-end">
        @foreach($this->getFormActions() as $action)
            {{ $action }}
        @endforeach
    </div>

    <script>
        function itemsModal() {
            return {
                modalOpen: false,
                openModal() {
                    this.modalOpen = true;
                    const modal = document.getElementById('items-modal');
                    if (modal) {
                        modal.style.display = 'flex';
                        modal.style.flexDirection = 'column';
                        document.body.style.overflow = 'hidden';
                    }
                },
                closeModal() {
                    this.modalOpen = false;
                    const modal = document.getElementById('items-modal');
                    if (modal) {
                        modal.style.display = 'none';
                        document.body.style.overflow = '';
                    }
                },
                itemsAdded() {
                    this.closeModal();
                    // Reload to refresh the items display
                    window.location.reload();
                }
            }
        }
    </script>
</x-filament-panels::page>
