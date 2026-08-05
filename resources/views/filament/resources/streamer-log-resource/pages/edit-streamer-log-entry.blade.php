@php
    use App\Models\StreamerLogEntry;

    /** @var StreamerLogEntry $record */
    $record = $this->record;
    $show = $record->show;
    $isStreamer = auth()->user()?->isStreamer() && !auth()->user()?->isAdmin();
@endphp

<x-filament-panels::page>
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
    @endphp

    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Items Sold</h3>
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
        </div>

        @if($totalItems > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($orders as $order)
                    @php
                        $item = $order->inventoryItem;
                    @endphp
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:shadow-md transition-shadow">
                        <div class="mb-3">
                            <h4 class="font-semibold text-gray-900 dark:text-white text-sm">{{ $item?->name ?? 'Unknown Item' }}</h4>
                            @if($item?->sku)
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $item->sku }}</p>
                            @endif
                        </div>

                        @if($item?->brand)
                            <p class="text-xs text-gray-600 dark:text-gray-300 mb-2">
                                <span class="font-medium">Brand:</span> {{ $item->brand }}
                            </p>
                        @endif

                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Quantity</p>
                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $order->quantity }}</p>
                            </div>
                            @if($item && $item->lastCost)
                                <div class="text-right">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Unit Cost</p>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">${{ number_format($item->lastCost, 2) }}</p>
                                </div>
                            @endif
                        </div>

                        @if($item?->category)
                            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                <span class="inline-block px-2 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded">
                                    {{ $item->category }}
                                </span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-8 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
                <p class="text-gray-500 dark:text-gray-400">No items logged yet.</p>
            </div>
        @endif
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
        {{ $this->getFormActionsView() }}
    </div>
</x-filament-panels::page>
