<div class="space-y-6">
    <!-- Header & Stats -->
    <div class="bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 rounded-lg border border-purple-200 dark:border-purple-700 p-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Profit Share Approvals</h1>
        <p class="text-gray-600 dark:text-gray-400 mb-6">Review and approve streamer profit share packets for {{ auth()->user()->name }}</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-purple-200 dark:border-purple-700">
                <p class="text-sm text-gray-600 dark:text-gray-400 font-semibold">Pending Review</p>
                <p class="text-4xl font-bold text-yellow-600 dark:text-yellow-400">{{ $stats['pending'] }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-green-200 dark:border-green-700">
                <p class="text-sm text-gray-600 dark:text-gray-400 font-semibold">Approved</p>
                <p class="text-4xl font-bold text-green-600 dark:text-green-400">{{ $stats['approved'] }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-red-200 dark:border-red-700">
                <p class="text-sm text-gray-600 dark:text-gray-400 font-semibold">Rejected</p>
                <p class="text-4xl font-bold text-red-600 dark:text-red-400">{{ $stats['rejected'] }}</p>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex gap-3 flex-wrap">
            <button wire:click="$set('filterStatus', 'submitted')"
                @class([
                    'px-4 py-2 rounded-lg font-medium transition',
                    'bg-yellow-600 text-white' => $filterStatus === 'submitted',
                    'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-300' => $filterStatus !== 'submitted',
                ])>
                ⏳ Pending
            </button>
            <button wire:click="$set('filterStatus', 'approved')"
                @class([
                    'px-4 py-2 rounded-lg font-medium transition',
                    'bg-green-600 text-white' => $filterStatus === 'approved',
                    'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-300' => $filterStatus !== 'approved',
                ])>
                ✓ Approved
            </button>
            <button wire:click="$set('filterStatus', 'rejected')"
                @class([
                    'px-4 py-2 rounded-lg font-medium transition',
                    'bg-red-600 text-white' => $filterStatus === 'rejected',
                    'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-300' => $filterStatus !== 'rejected',
                ])>
                ✕ Rejected
            </button>
        </div>
    </div>

    <!-- Packets List -->
    @if ($packets->count() > 0)
        <div class="grid grid-cols-1 gap-4">
            @foreach ($packets as $packet)
                <div @class([
                    'bg-white dark:bg-gray-800 rounded-lg border p-6 cursor-pointer hover:shadow-lg transition',
                    'border-yellow-200 dark:border-yellow-700' => $packet->status === 'submitted',
                    'border-green-200 dark:border-green-700' => $packet->status === 'approved',
                    'border-red-200 dark:border-red-700' => $packet->status === 'rejected',
                    'border-gray-200 dark:border-gray-700' => true,
                ])
                    wire:click="selectPacket({{ $packet->id }})">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $packet->streamer->name }}</h3>
                            <p class="text-gray-600 dark:text-gray-400">{{ $packet->getMonthLabel() }}</p>
                        </div>
                        <span @class([
                            'inline-block px-3 py-1 rounded-full text-sm font-semibold',
                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300' => $packet->status === 'submitted',
                            'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' => $packet->status === 'approved',
                            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' => $packet->status === 'rejected',
                        ])>
                            @switch($packet->status)
                                @case('submitted')
                                    ⏳ Awaiting Review
                                    @break
                                @case('approved')
                                    ✓ Approved
                                    @break
                                @case('rejected')
                                    ✕ Rejected
                                    @break
                            @endswitch
                        </span>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Gross Revenue</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">${{ number_format($packet->gross_revenue, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Total Costs</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">${{ number_format($packet->calculateTotalCost(), 2) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Net Profit</p>
                            <p class="text-lg font-semibold text-green-600 dark:text-green-400">${{ number_format($packet->calculateNetProfit(), 2) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Their Share</p>
                            <p class="text-lg font-semibold text-indigo-600 dark:text-indigo-400">${{ number_format($packet->profit_share_amount, 2) }}</p>
                        </div>
                    </div>

                    @if ($packet->notes)
                        <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-700">
                            <p class="text-sm text-blue-900 dark:text-blue-200">
                                <span class="font-semibold">Notes:</span> {{ $packet->notes }}
                            </p>
                        </div>
                    @endif

                    <div class="mt-4 flex gap-2">
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            Submitted {{ $packet->submitted_at?->diffForHumans() ?? 'N/A' }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-12 text-center">
            <p class="text-gray-600 dark:text-gray-400 text-lg">No packets to review</p>
        </div>
    @endif

    <!-- Packet Detail Modal -->
    @if ($selectedPacket)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between sticky top-0 bg-white dark:bg-gray-800">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $selectedPacket->streamer->name }}</h2>
                        <p class="text-gray-600 dark:text-gray-400">{{ $selectedPacket->getMonthLabel() }}</p>
                    </div>
                    <button wire:click="$set('selectedPacket', null)"
                        class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 text-2xl">&times;</button>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Summary Cards -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-700">
                            <p class="text-xs text-blue-600 dark:text-blue-400 uppercase font-semibold">Gross Revenue</p>
                            <p class="text-2xl font-bold text-blue-900 dark:text-blue-100">${{ number_format($selectedPacket->gross_revenue, 2) }}</p>
                        </div>

                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border border-red-200 dark:border-red-700">
                            <p class="text-xs text-red-600 dark:text-red-400 uppercase font-semibold">Total Costs</p>
                            <p class="text-2xl font-bold text-red-900 dark:text-red-100">${{ number_format($selectedPacket->calculateTotalCost(), 2) }}</p>
                        </div>

                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-700">
                            <p class="text-xs text-green-600 dark:text-green-400 uppercase font-semibold">Net Profit</p>
                            <p class="text-2xl font-bold text-green-900 dark:text-green-100">${{ number_format($selectedPacket->calculateNetProfit(), 2) }}</p>
                        </div>

                        <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4 border border-indigo-200 dark:border-indigo-700">
                            <p class="text-xs text-indigo-600 dark:text-indigo-400 uppercase font-semibold">Streamer Share</p>
                            <p class="text-2xl font-bold text-indigo-900 dark:text-indigo-100">${{ number_format($selectedPacket->profit_share_amount, 2) }}</p>
                        </div>
                    </div>

                    <!-- Cost Breakdown -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Cost Breakdown</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Product Cost:</span>
                                <span class="font-semibold text-gray-900 dark:text-white">${{ number_format($selectedPacket->product_cost, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Shipping Cost:</span>
                                <span class="font-semibold text-gray-900 dark:text-white">${{ number_format($selectedPacket->shipping_cost, 2) }}</span>
                            </div>
                            @if ($selectedPacket->other_costs > 0)
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Other Costs:</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">${{ number_format($selectedPacket->other_costs, 2) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($selectedPacket->notes)
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-700">
                            <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">Streamer Notes</h3>
                            <p class="text-blue-900 dark:text-blue-200">{{ $selectedPacket->notes }}</p>
                        </div>
                    @endif

                    @if ($selectedPacket->status === 'rejected' && $selectedPacket->rejection_reason)
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border border-red-200 dark:border-red-700">
                            <h3 class="font-semibold text-red-900 dark:text-red-100 mb-2">Rejection Reason</h3>
                            <p class="text-red-900 dark:text-red-200">{{ $selectedPacket->rejection_reason }}</p>
                        </div>
                    @endif

                    <!-- Actions -->
                    @if ($selectedPacket->status === 'submitted')
                        <div class="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div>
                                <button wire:click="approvePacket()"
                                    class="w-full px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition">
                                    ✓ Approve Packet
                                </button>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    Rejection Reason (if rejecting)
                                </label>
                                <textarea wire:model="rejectionReason" rows="4"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-500"
                                    placeholder="Explain why this packet is being rejected..."></textarea>
                                @error('rejectionReason') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror

                                <button wire:click="rejectPacket()"
                                    class="w-full mt-2 px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition">
                                    ✕ Reject & Return to Streamer
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 text-center">
                            <p class="text-gray-600 dark:text-gray-300 font-semibold">
                                @switch($selectedPacket->status)
                                    @case('approved')
                                        ✓ This packet has been approved
                                        @break
                                    @case('rejected')
                                        ✕ This packet was rejected and returned to the streamer
                                        @break
                                @endswitch
                            </p>
                        </div>
                    @endif

                    <button wire:click="$set('selectedPacket', null)"
                        class="w-full px-6 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-semibold transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
