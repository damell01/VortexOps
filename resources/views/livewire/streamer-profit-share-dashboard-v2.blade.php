<div class="space-y-4 md:space-y-6">
    <!-- Header -->
    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 rounded-lg border border-indigo-200 dark:border-indigo-700 p-4 md:p-6">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Profit Share Packet</h1>
                <p class="text-sm md:text-base text-gray-600 dark:text-gray-400 mt-1">{{ $currentPacket->getMonthLabel() }}</p>
            </div>
            <div class="text-right">
                <p class="text-2xl md:text-4xl font-bold text-indigo-600 dark:text-indigo-400">
                    ${{ number_format($currentPacket->profit_share_amount, 2) }}
                </p>
                <p class="text-xs md:text-sm text-gray-600 dark:text-gray-400">Your share this month</p>
            </div>
        </div>

        @if (session('success'))
            <div class="mt-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3 md:p-4">
                <p class="text-sm md:text-base text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        @endif
    </div>

    <!-- Month Progress -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 md:p-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg md:text-xl font-bold text-gray-900 dark:text-white">Month Progress</h2>
            <span class="text-2xl md:text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ round($monthProgress) }}%</span>
        </div>

        <!-- Progress Bar -->
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 md:h-4 overflow-hidden mb-4">
            <div class="bg-gradient-to-r from-indigo-500 to-purple-500 h-full transition-all duration-300"
                style="width: {{ round($monthProgress) }}%">
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 md:gap-4">
            <div>
                <p class="text-xs md:text-sm text-gray-600 dark:text-gray-400">Days Remaining</p>
                <p class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">{{ $daysUntilFinalization }}</p>
            </div>
            <div>
                <p class="text-xs md:text-sm text-gray-600 dark:text-gray-400">Shows This Month</p>
                <p class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">{{ $relatedLogs->count() }}</p>
            </div>
            @if ($isFinalized)
                <div class="col-span-2 md:col-span-1">
                    <p class="text-xs md:text-sm text-green-600 dark:text-green-400 font-semibold">Status</p>
                    <p class="text-lg md:text-xl font-bold text-green-600 dark:text-green-400">Finalized ✓</p>
                </div>
            @else
                <div class="col-span-2 md:col-span-1">
                    <p class="text-xs md:text-sm text-blue-600 dark:text-blue-400 font-semibold">Status</p>
                    <p class="text-lg md:text-xl font-bold text-blue-600 dark:text-blue-400">In Progress</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Current Month Packet -->
    @if ($currentPacket)
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg border border-blue-200 dark:border-blue-700 p-4 md:p-6">
            <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white mb-4">Calculated Breakdown</h2>

            <!-- Main Metrics -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 md:p-4 border border-blue-200 dark:border-blue-700">
                    <p class="text-xs md:text-sm text-gray-600 dark:text-gray-400 font-semibold uppercase">Gross Revenue</p>
                    <p class="text-lg md:text-2xl font-bold text-gray-900 dark:text-white mt-1">${{ number_format($currentPacket->gross_revenue, 2) }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 md:p-4 border border-red-200 dark:border-red-700">
                    <p class="text-xs md:text-sm text-gray-600 dark:text-gray-400 font-semibold uppercase">Total Costs</p>
                    <p class="text-lg md:text-2xl font-bold text-gray-900 dark:text-white mt-1">${{ number_format($currentPacket->calculateTotalCost(), 2) }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 md:p-4 border border-green-200 dark:border-green-700">
                    <p class="text-xs md:text-sm text-gray-600 dark:text-gray-400 font-semibold uppercase">Net Profit</p>
                    <p class="text-lg md:text-2xl font-bold text-green-600 dark:text-green-400 mt-1">${{ number_format($currentPacket->calculateNetProfit(), 2) }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 md:p-4 border border-indigo-200 dark:border-indigo-700">
                    <p class="text-xs md:text-sm text-gray-600 dark:text-gray-400 font-semibold uppercase">Your Share</p>
                    <p class="text-lg md:text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">${{ number_format($currentPacket->profit_share_amount, 2) }}</p>
                </div>
            </div>

            <!-- Cost Breakdown -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 md:p-4 border border-gray-200 dark:border-gray-700 mb-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Cost Details</h3>
                <div class="space-y-2 text-sm md:text-base">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 dark:text-gray-400">Product Cost:</span>
                        <span class="font-semibold text-gray-900 dark:text-white">${{ number_format($currentPacket->product_cost, 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 dark:text-gray-400">Shipping Cost:</span>
                        <span class="font-semibold text-gray-900 dark:text-white">${{ number_format($currentPacket->shipping_cost, 2) }}</span>
                    </div>
                    @if ($currentPacket->other_costs > 0)
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 dark:text-gray-400">Other Costs:</span>
                            <span class="font-semibold text-gray-900 dark:text-white">${{ number_format($currentPacket->other_costs, 2) }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Notes Section -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 md:p-4 border border-gray-200 dark:border-gray-700 mb-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Notes</h3>
                @if ($currentPacket->notes)
                    <p class="text-sm md:text-base text-gray-700 dark:text-gray-300">{{ $currentPacket->notes }}</p>
                @else
                    <p class="text-sm md:text-base text-gray-500 dark:text-gray-400">No notes yet. Add notes if you want to explain anything about this packet.</p>
                @endif

                @if ($isFinalized)
                    <div class="mt-3">
                        <input wire:model="notes" type="text" placeholder="Add notes..."
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <button wire:click="addNotes({{ $currentPacket->id }})"
                            class="mt-2 w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition font-medium text-sm">
                            💬 Save Notes
                        </button>
                    </div>
                @endif
            </div>

            <!-- Related Logs -->
            @if ($relatedLogs->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 md:p-4 border border-gray-200 dark:border-gray-700 mb-6">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Shows This Month ({{ $relatedLogs->count() }})</h3>
                    <div class="space-y-2 max-h-48 md:max-h-64 overflow-y-auto">
                        @foreach ($relatedLogs as $log)
                            <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-600">
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white text-sm">{{ $log->show?->title ?? 'Show #' . $log->id }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">Revenue: ${{ number_format($log->gross_revenue, 2) }}</p>
                                </div>
                                <span class="inline-block px-2 py-1 bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 rounded text-xs font-semibold">
                                    ✓ Logged
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Action Buttons -->
            <div class="flex flex-col gap-2 md:gap-3">
                @if ($isFinalized && $currentPacket->status === 'pending_review')
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-3 md:p-4">
                        <p class="text-sm md:text-base text-green-900 dark:text-green-200 font-semibold mb-3">
                            ✓ Month has ended. Your packet is ready for submission.
                        </p>
                        <button wire:click="submitPacket({{ $currentPacket->id }})"
                            class="w-full px-4 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg transition font-bold text-base">
                            📤 Submit to Manager for Final Review
                        </button>
                    </div>
                @elseif ($currentPacket->status === 'submitted')
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-3 md:p-4">
                        <p class="text-sm md:text-base text-blue-900 dark:text-blue-200 font-semibold">
                            ⏳ Submitted - Waiting for manager approval
                        </p>
                    </div>
                @elseif ($currentPacket->status === 'approved')
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-3 md:p-4">
                        <p class="text-sm md:text-base text-green-900 dark:text-green-200 font-semibold">
                            ✓ Approved by manager - Payment will be processed
                        </p>
                    </div>
                @elseif ($currentPacket->status === 'rejected')
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg p-3 md:p-4">
                        <p class="text-sm md:text-base text-red-900 dark:text-red-200 font-semibold mb-2">
                            Manager requested review:
                        </p>
                        <p class="text-sm text-red-800 dark:text-red-300">{{ $currentPacket->rejection_reason }}</p>
                    </div>
                @else
                    <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-lg p-3 md:p-4">
                        <p class="text-sm md:text-base text-indigo-900 dark:text-indigo-200 font-semibold">
                            📊 Live calculations updating as you log shows. Ready to submit when month ends.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Previous Months -->
    <div class="space-y-4">
        <!-- Pending/Review -->
        @if ($pendingPackets->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 md:p-6">
                <h2 class="text-lg md:text-xl font-bold text-gray-900 dark:text-white mb-4">Awaiting Review</h2>
                <div class="space-y-3">
                    @foreach ($pendingPackets as $packet)
                        @if ($packet->id !== $currentPacket?->id)
                            <button wire:click="selectPacket({{ $packet->id }})"
                                @class([
                                    'w-full p-4 rounded-lg border-2 text-left transition',
                                    'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-300 dark:border-yellow-700 hover:shadow-md' => $packet->status === 'pending_review',
                                    'bg-blue-50 dark:bg-blue-900/20 border-blue-300 dark:border-blue-700 hover:shadow-md' => $packet->status === 'submitted',
                                    'bg-red-50 dark:bg-red-900/20 border-red-300 dark:border-red-700 hover:shadow-md' => $packet->status === 'rejected',
                                ])>
                                <div class="flex flex-col md:flex-row md:items-center justify-between gap-2">
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ $packet->getMonthLabel() }}</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            Share: <span class="font-bold">${{ number_format($packet->profit_share_amount, 2) }}</span>
                                        </p>
                                    </div>
                                    <span @class([
                                        'inline-block px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap',
                                        'bg-yellow-200 text-yellow-900 dark:bg-yellow-900 dark:text-yellow-200' => $packet->status === 'pending_review',
                                        'bg-blue-200 text-blue-900 dark:bg-blue-900 dark:text-blue-200' => $packet->status === 'submitted',
                                        'bg-red-200 text-red-900 dark:bg-red-900 dark:text-red-200' => $packet->status === 'rejected',
                                    ])>
                                        @switch($packet->status)
                                            @case('pending_review')
                                                🔄 Ready to Submit
                                                @break
                                            @case('submitted')
                                                ⏳ Under Review
                                                @break
                                            @case('rejected')
                                                🔙 Needs Review
                                                @break
                                        @endswitch
                                    </span>
                                </div>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Approved History -->
        @if ($approvedPackets->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 md:p-6">
                <h2 class="text-lg md:text-xl font-bold text-gray-900 dark:text-white mb-4">Approved Packets (Past 12 Months)</h2>
                <div class="space-y-2">
                    @foreach ($approvedPackets as $packet)
                        <button wire:click="selectPacket({{ $packet->id }})"
                            class="w-full p-3 md:p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700 hover:shadow-md transition text-left">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $packet->getMonthLabel() }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Approved {{ $packet->approved_at?->diffForHumans() ?? 'recently' }}
                                    </p>
                                </div>
                                <span class="inline-block px-3 py-1 bg-green-200 text-green-900 dark:bg-green-900 dark:text-green-200 rounded text-xs font-semibold whitespace-nowrap">
                                    ✓ ${{ number_format($packet->profit_share_amount, 2) }}
                                </span>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- Detail Modal -->
    @if ($selectedPacket)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-end md:items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-lg md:rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 p-4 md:p-6 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex items-start justify-between">
                    <div>
                        <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">{{ $selectedPacket->getMonthLabel() }}</h2>
                    </div>
                    <button wire:click="$set('selectedPacket', null)"
                        class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 text-2xl md:text-3xl">&times;</button>
                </div>

                <div class="p-4 md:p-6 space-y-6">
                    <!-- Summary -->
                    <div class="grid grid-cols-2 gap-3 md:gap-4">
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 md:p-4 border border-blue-200 dark:border-blue-700">
                            <p class="text-xs md:text-sm text-blue-600 dark:text-blue-400 uppercase font-semibold">Gross Revenue</p>
                            <p class="text-xl md:text-2xl font-bold text-blue-900 dark:text-blue-100 mt-1">${{ number_format($selectedPacket->gross_revenue, 2) }}</p>
                        </div>

                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3 md:p-4 border border-red-200 dark:border-red-700">
                            <p class="text-xs md:text-sm text-red-600 dark:text-red-400 uppercase font-semibold">Total Costs</p>
                            <p class="text-xl md:text-2xl font-bold text-red-900 dark:text-red-100 mt-1">${{ number_format($selectedPacket->calculateTotalCost(), 2) }}</p>
                        </div>

                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 md:p-4 border border-green-200 dark:border-green-700">
                            <p class="text-xs md:text-sm text-green-600 dark:text-green-400 uppercase font-semibold">Net Profit</p>
                            <p class="text-xl md:text-2xl font-bold text-green-900 dark:text-green-100 mt-1">${{ number_format($selectedPacket->calculateNetProfit(), 2) }}</p>
                        </div>

                        <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-3 md:p-4 border border-indigo-200 dark:border-indigo-700">
                            <p class="text-xs md:text-sm text-indigo-600 dark:text-indigo-400 uppercase font-semibold">Your Share</p>
                            <p class="text-xl md:text-2xl font-bold text-indigo-900 dark:text-indigo-100 mt-1">${{ number_format($selectedPacket->profit_share_amount, 2) }}</p>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 md:p-4">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Breakdown</h3>
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
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 md:p-4 border border-blue-200 dark:border-blue-700">
                            <p class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-1">Notes:</p>
                            <p class="text-sm text-blue-800 dark:text-blue-200">{{ $selectedPacket->notes }}</p>
                        </div>
                    @endif

                    @if ($selectedPacket->rejection_reason)
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3 md:p-4 border border-red-200 dark:border-red-700">
                            <p class="text-sm font-semibold text-red-900 dark:text-red-100 mb-1">Manager Feedback:</p>
                            <p class="text-sm text-red-800 dark:text-red-200">{{ $selectedPacket->rejection_reason }}</p>
                        </div>
                    @endif

                    <button wire:click="$set('selectedPacket', null)"
                        class="w-full px-4 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-semibold text-base transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
