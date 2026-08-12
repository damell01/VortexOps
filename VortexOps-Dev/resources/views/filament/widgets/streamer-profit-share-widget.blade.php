<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="text-2xl">💰</span>
                    Profit Share
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $currentMonth }} Status</p>
            </div>
            <a href="{{ route('filament.admin.pages.streamer-profit-share') }}"
                class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs rounded font-medium transition">
                View Details →
            </a>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-3">
                <p class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold">{{ $currentMonth }} Share</p>
                <p class="text-2xl font-bold text-indigo-900 dark:text-indigo-100">${{ number_format($currentShare, 2) }}</p>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                <p class="text-xs text-blue-600 dark:text-blue-400 font-semibold">Status</p>
                <p class="text-sm font-bold text-blue-900 dark:text-blue-100 capitalize">
                    @if ($currentStatus === 'draft')
                        <span class="text-yellow-600 dark:text-yellow-400">📝 Draft</span>
                    @elseif ($currentStatus === 'pending_review')
                        <span class="text-amber-600 dark:text-amber-400">⏳ Finalizing</span>
                    @elseif ($currentStatus === 'submitted')
                        <span class="text-blue-600 dark:text-blue-400">📤 Submitted</span>
                    @elseif ($currentStatus === 'approved')
                        <span class="text-green-600 dark:text-green-400">✓ Approved</span>
                    @elseif ($currentStatus === 'rejected')
                        <span class="text-red-600 dark:text-red-400">✕ Rejected</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="space-y-2 mb-4 border-t border-gray-200 dark:border-gray-700 pt-3">
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Pending Review</span>
                <span class="font-bold text-gray-900 dark:text-white">{{ $pendingPackets }}</span>
            </div>
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Approved This Year</span>
                <span class="font-bold text-gray-900 dark:text-white">{{ $approvedThisYear }}</span>
            </div>
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Total Approved</span>
                <span class="font-bold text-green-600 dark:text-green-400">${{ number_format($totalApprovedThisYear, 2) }}</span>
            </div>
        </div>

        <a href="{{ route('filament.admin.pages.streamer-profit-share') }}"
            class="block w-full text-center px-3 py-2 bg-indigo-100 dark:bg-indigo-900/30 hover:bg-indigo-200 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 rounded text-sm font-medium transition">
            View Profit Share Packets
        </a>
    </x-filament::section>
</x-filament-widgets::widget>
