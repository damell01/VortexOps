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
                        <p class="text-lg font-medium text-gray-900 dark:text-white">{{ $show->channel?->name ?? 'Unknown' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Gross Revenue</p>
                        <p class="text-lg font-medium text-gray-900 dark:text-white">{{ $show->gross_revenue ? '$' . number_format($show->gross_revenue, 2) : 'Not set' }}</p>
                    </div>
                </div>

                <!-- Status Badges -->
                <div class="flex flex-wrap items-center gap-3">
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
    <div class="mb-8">
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

    <!-- Step Wizard -->
    @if($canEdit)
    <div class="mb-8">
        <!-- Step Indicator -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Step <span x-text="currentStep"></span> of 3</h3>
                <span class="px-3 py-1 rounded-full text-sm font-medium" :class="{
                    'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300': currentStep <= 2,
                    'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300': currentStep === 3,
                }">
                    <span x-show="currentStep === 1">📦 Items & Summary</span>
                    <span x-show="currentStep === 2">📋 Details</span>
                    <span x-show="currentStep === 3">✓ Review</span>
                </span>
            </div>

            <!-- Progress Bar -->
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full transition-all" :style="{ width: (currentStep / 3 * 100) + '%' }"></div>
            </div>

            <!-- Step Indicators -->
            <div class="flex justify-between mt-4">
                <button @click="currentStep = 1" :class="{
                    'bg-blue-600 text-white': currentStep >= 1,
                    'bg-gray-300 dark:bg-gray-600 text-gray-600 dark:text-gray-400': currentStep < 1,
                }" class="w-10 h-10 rounded-full font-bold text-sm transition">1</button>
                <button @click="currentStep = 2" :class="{
                    'bg-blue-600 text-white': currentStep >= 2,
                    'bg-gray-300 dark:bg-gray-600 text-gray-600 dark:text-gray-400': currentStep < 2,
                }" class="w-10 h-10 rounded-full font-bold text-sm transition">2</button>
                <button @click="currentStep = 3" :class="{
                    'bg-blue-600 text-white': currentStep >= 3,
                    'bg-gray-300 dark:bg-gray-600 text-gray-600 dark:text-gray-400': currentStep < 3,
                }" class="w-10 h-10 rounded-full font-bold text-sm transition">3</button>
            </div>
        </div>

        <!-- Step 1: Items & Summary -->
        <div x-show="currentStep === 1" x-transition class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Items & Summary</h3>

            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Revenue</p>
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">${{ number_format((float) $record->gross_revenue, 2) }}</p>
                </div>
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 border border-purple-200 dark:border-purple-800">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Product Cost</p>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">${{ number_format((float) $record->product_cost, 2) }}</p>
                </div>
                <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4 border border-amber-200 dark:border-amber-800">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Items Logged</p>
                    <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $totalItems }}</p>
                </div>
            </div>

            <!-- Items Section -->
            <div class="mb-6">
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

                <button
                    @click="$dispatch('open-items-modal')"
                    class="w-full px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-medium mt-4"
                >
                    <span class="inline-flex items-center gap-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd" />
                        </svg>
                        {{ $totalItems > 0 ? 'Add More Items' : 'Add Items' }}
                    </span>
                </button>
            </div>

            <!-- Next Button -->
            <div class="flex justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                <button @click="currentStep = 2" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                    Next →
                </button>
            </div>
        </div>

        <!-- Step 2: Log Details -->
        <div x-show="currentStep === 2" x-transition class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Log Details</h3>

            <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    Fill in the details about your stream. All required fields must be completed before you can submit.
                </p>
            </div>

            {{ $this->form }}

            <!-- Navigation Buttons -->
            <div class="flex justify-between gap-3 pt-6 border-t border-gray-200 dark:border-gray-700 mt-6">
                <button @click="currentStep = 1" class="px-6 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 font-medium transition">
                    ← Back
                </button>
                <button @click="currentStep = 3" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                    Next →
                </button>
            </div>
        </div>

        <!-- Step 3: Review & Submit -->
        <div x-show="currentStep === 3" x-transition class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Review & Submit</h3>

            <!-- Summary -->
            <div class="space-y-4 mb-6">
                <div class="bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-900/10 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Show</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $show->title }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-gradient-to-r from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-900/10 rounded-lg p-4 border border-purple-200 dark:border-purple-800">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Items Logged</p>
                        <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $totalItems }}</p>
                    </div>
                    <div class="bg-gradient-to-r from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-900/10 rounded-lg p-4 border border-green-200 dark:border-green-800">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Total Item Cost</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">${{ number_format($totalCost, 2) }}</p>
                    </div>
                    <div class="bg-gradient-to-r from-amber-50 to-amber-100 dark:from-amber-900/20 dark:to-amber-900/10 rounded-lg p-4 border border-amber-200 dark:border-amber-800">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Hours Streamed</p>
                        <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format((float) $record->hours_streamed, 2) }} hrs</p>
                    </div>
                </div>

                <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                    <p class="text-sm font-semibold text-green-800 dark:text-green-200 mb-1">✓ Ready to Submit</p>
                    <p class="text-sm text-green-700 dark:text-green-300">All information looks correct. Click submit to send for admin review.</p>
                </div>
            </div>

            <!-- Navigation & Form Actions -->
            <div class="flex flex-col gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex justify-between gap-3">
                    <button @click="currentStep = 2" class="px-6 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 font-medium transition">
                        ← Back
                    </button>
                    <div>
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

    <!-- Items Modal -->
    <div wire:key="items-modal-{{ $record->id }}" id="items-modal" @keydown.escape="closeModal()" @click="closeOnBackdropClick($event)" style="display: none !important; position: fixed !important; inset: 0 !important; z-index: 50 !important; background: rgba(0,0,0,0.5) !important; padding: 1rem !important; flex-direction: column !important; align-items: center !important; justify-content: center !important;">
        <div @click.stop class="w-full max-w-4xl max-h-[90vh] overflow-y-auto bg-white dark:bg-gray-900 rounded-lg shadow-2xl">
            @livewire('streamer-log-items-modal', [
                'recordId' => $record->id,
                'title' => 'STREAMER LOG INVENTORY CATALOG',
                'description' => 'Select inventory items and set quantities for this show',
                'multiSelect' => true,
                'allowQuantityInput' => true,
                'allowCostInput' => true,
                'allowCreateItem' => true,
                'successEvent' => 'items-added',
            ], key('items-modal-' . $record->id))
        </div>
    </div>

    <script>
        function wizardData() {
            return {
                currentStep: 1,
                modalOpen: false,
                openModal() {
                    this.modalOpen = true;
                    const modal = document.getElementById('items-modal');
                    if (modal) {
                        modal.style.setProperty('display', 'flex', 'important');
                        setTimeout(() => {
                            document.body.style.overflow = 'hidden';
                            document.documentElement.style.overflow = 'hidden';
                        }, 0);
                    }
                },
                closeModal() {
                    this.modalOpen = false;
                    const modal = document.getElementById('items-modal');
                    if (modal) {
                        modal.style.setProperty('display', 'none', 'important');
                        document.body.style.overflow = '';
                        document.documentElement.style.overflow = '';
                    }
                },
                closeOnBackdropClick(e) {
                    if (e.target.id === 'items-modal') {
                        this.closeModal();
                    }
                },
                itemsAdded() {
                    this.closeModal();
                    window.location.reload();
                }
            }
        }
    </script>
</x-filament-panels::page>
