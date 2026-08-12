<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Profit Share Packets</h1>
                <p class="text-gray-600 dark:text-gray-400">{{ $streamer->name }}'s monthly profit share review</p>
            </div>
            <button wire:click="$set('showHistory', !$showHistory)"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-medium">
                📊 View History
            </button>
        </div>

        @if (session('success'))
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-4">
                <p class="text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
                <p class="text-red-800 dark:text-red-200">{{ session('error') }}</p>
            </div>
        @endif
    </div>

    <!-- Current Month Packet -->
    @if ($currentPacket)
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg border border-blue-200 dark:border-blue-700 p-6">
            <div class="mb-4">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Current Month: {{ $currentPacket->getMonthLabel() }}</h2>
                <p class="text-gray-600 dark:text-gray-400">Status: <span @class([
                    'font-semibold',
                    'text-yellow-600 dark:text-yellow-400' => $currentPacket->status === 'draft',
                    'text-blue-600 dark:text-blue-400' => $currentPacket->status === 'submitted',
                    'text-green-600 dark:text-green-400' => $currentPacket->status === 'approved',
                    'text-red-600 dark:text-red-400' => $currentPacket->status === 'rejected',
                ])>{{ ucfirst(str_replace('_', ' ', $currentPacket->status)) }}</span></p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded p-4 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-semibold">Gross Revenue</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">${{ number_format($currentPacket->gross_revenue, 2) }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded p-4 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-semibold">Total Costs</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">${{ number_format($currentPacket->calculateTotalCost(), 2) }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded p-4 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-semibold">Net Profit</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">${{ number_format($currentPacket->calculateNetProfit(), 2) }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded p-4 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-semibold">Your Share</p>
                    <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">${{ number_format($currentPacket->profit_share_amount, 2) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded p-4 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-semibold">Product Cost</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">${{ number_format($currentPacket->product_cost, 2) }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded p-4 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-semibold">Shipping Cost</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">${{ number_format($currentPacket->shipping_cost, 2) }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded p-4 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-semibold">Other Costs</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">${{ number_format($currentPacket->other_costs ?? 0, 2) }}</p>
                </div>
            </div>

            @if ($currentPacket->notes)
                <div class="bg-white dark:bg-gray-800 rounded p-4 border border-gray-200 dark:border-gray-700 mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-semibold mb-1">Notes</p>
                    <p class="text-gray-900 dark:text-white">{{ $currentPacket->notes }}</p>
                </div>
            @endif

            @if ($currentPacket->status === 'rejected' && $currentPacket->rejection_reason)
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded p-4 mb-4">
                    <p class="text-sm font-semibold text-red-800 dark:text-red-300 mb-1">Manager Feedback</p>
                    <p class="text-red-900 dark:text-red-200">{{ $currentPacket->rejection_reason }}</p>
                </div>
            @endif

            <div class="flex gap-3">
                @if ($currentPacket->status === 'draft' || $currentPacket->status === 'rejected')
                    <button wire:click="editPacket({{ $currentPacket->id }})"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-medium">
                        ✏️ Edit
                    </button>
                    <button wire:click="submitPacket({{ $currentPacket->id }})"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition font-medium">
                        📤 Submit for Review
                    </button>
                @elseif ($currentPacket->status === 'submitted')
                    <div class="px-4 py-2 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded-lg font-medium">
                        ⏳ Awaiting manager review...
                    </div>
                @elseif ($currentPacket->status === 'approved')
                    <div class="px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 rounded-lg font-medium">
                        ✓ Approved by manager
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 text-center">
            <p class="text-gray-600 dark:text-gray-400">No packet for current month. Create one to get started.</p>
        </div>
    @endif

    <!-- Edit Form -->
    @if ($showForm && $selectedPacket)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Edit Packet: {{ $selectedPacket->getMonthLabel() }}</h2>

            <form wire:submit="savePacket" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Gross Revenue</label>
                        <input wire:model="gross_revenue" type="number" step="0.01" min="0"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="0.00">
                        @error('gross_revenue') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Product Cost</label>
                        <input wire:model="product_cost" type="number" step="0.01" min="0"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="0.00">
                        @error('product_cost') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Shipping Cost</label>
                        <input wire:model="shipping_cost" type="number" step="0.01" min="0"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="0.00">
                        @error('shipping_cost') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Other Costs (Optional)</label>
                        <input wire:model="other_costs" type="number" step="0.01" min="0"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="0.00">
                        @error('other_costs') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Notes (Optional)</label>
                    <textarea wire:model="notes" rows="4"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Add any notes about this packet..."></textarea>
                    @error('notes') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition font-medium">
                        💾 Save Changes
                    </button>
                    <button type="button" wire:click="$set('showForm', false)"
                        class="px-6 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition font-medium">
                        ✕ Cancel
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- Pending Packets -->
    @if ($pendingPackets->count() > 0 && !$showForm)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Pending Review</h2>

            <div class="space-y-3">
                @foreach ($pendingPackets as $packet)
                    @if ($packet->id !== $currentPacket?->id)
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $packet->getMonthLabel() }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Status: <span @class([
                                        'font-semibold',
                                        'text-yellow-600 dark:text-yellow-400' => $packet->status === 'draft',
                                        'text-blue-600 dark:text-blue-400' => $packet->status === 'submitted',
                                        'text-red-600 dark:text-red-400' => $packet->status === 'rejected',
                                    ])>{{ ucfirst(str_replace('_', ' ', $packet->status)) }}</span>
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button wire:click="selectPacket({{ $packet->id }})"
                                    class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded transition">
                                    View
                                </button>
                                @if ($packet->status === 'draft' || $packet->status === 'rejected')
                                    <button wire:click="editPacket({{ $packet->id }})"
                                        class="px-3 py-1 bg-gray-600 hover:bg-gray-700 text-white text-sm rounded transition">
                                        Edit
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    <!-- Approved History -->
    @if ($showHistory && $approvedPackets->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Approved Packets (Last 12 Months)</h2>

            <div class="space-y-3">
                @foreach ($approvedPackets as $packet)
                    <div class="flex items-center justify-between p-4 bg-green-50 dark:bg-green-900/10 rounded-lg border border-green-200 dark:border-green-700">
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $packet->getMonthLabel() }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Share: <span class="font-semibold text-green-600 dark:text-green-400">${{ number_format($packet->profit_share_amount, 2) }}</span>
                            </p>
                        </div>
                        <button wire:click="selectPacket({{ $packet->id }})"
                            class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-sm rounded transition">
                            View
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
