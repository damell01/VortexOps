<x-filament-panels::page>
    <div class="space-y-8">
        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-blue-600 to-cyan-600 rounded-lg p-6 md:p-8 text-white">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold mb-2">{{ $this->getManager()->isAdmin() ? 'Admin Dashboard' : 'Manager Dashboard' }} 📋</h1>
                    <p class="text-blue-100">Review and approve streamer profit share packets. Keep everything on track.</p>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 md:gap-4">
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-4 md:p-6">
                <p class="text-xs md:text-sm text-amber-600 dark:text-amber-400 uppercase font-semibold">Pending Review</p>
                <p class="text-2xl md:text-3xl font-bold text-amber-900 dark:text-amber-100 mt-2">{{ $stats['pending_review'] }}</p>
                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">Awaiting action</p>
            </div>

            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-4 md:p-6">
                <p class="text-xs md:text-sm text-green-600 dark:text-green-400 uppercase font-semibold">Approved</p>
                <p class="text-2xl md:text-3xl font-bold text-green-900 dark:text-green-100 mt-2">{{ $stats['approved'] }}</p>
                <p class="text-xs text-green-600 dark:text-green-400 mt-1">This month</p>
            </div>

            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg p-4 md:p-6">
                <p class="text-xs md:text-sm text-red-600 dark:text-red-400 uppercase font-semibold">Rejected</p>
                <p class="text-2xl md:text-3xl font-bold text-red-900 dark:text-red-100 mt-2">{{ $stats['rejected'] }}</p>
                <p class="text-xs text-red-600 dark:text-red-400 mt-1">Sent back</p>
            </div>

            <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 rounded-lg p-4 md:p-6">
                <p class="text-xs md:text-sm text-purple-600 dark:text-purple-400 uppercase font-semibold">Streamers</p>
                <p class="text-2xl md:text-3xl font-bold text-purple-900 dark:text-purple-100 mt-2">{{ $stats['managed_streamers'] }}</p>
                <p class="text-xs text-purple-600 dark:text-purple-400 mt-1">Assigned</p>
            </div>
        </div>

        <!-- Main Actions Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Profit Share Reviews Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 hover:shadow-lg transition">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="text-2xl">💰</span>
                            Profit Share Reviews
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Review and approve streamer packets</p>
                    </div>
                </div>

                <div class="space-y-3 mb-4">
                    <div class="flex items-center gap-3 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                        <span class="text-2xl">⏳</span>
                        <div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Pending</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['pending_review'] }} packets</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <span class="text-2xl">✓</span>
                        <div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Approved</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['approved'] }} packets</p>
                        </div>
                    </div>
                </div>

                <a href="{{ route('filament.admin.pages.manager-profit-share') }}"
                    class="block w-full px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold text-center transition">
                    Review Packets →
                </a>
            </div>

            <!-- Streamer Management Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 hover:shadow-lg transition">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="text-2xl">👥</span>
                            Manage Streamers
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">View and manage assigned streamers</p>
                    </div>
                </div>

                <div class="space-y-3 mb-4">
                    <div class="flex items-center gap-3 p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                        <span class="text-2xl">👤</span>
                        <div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Total</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['managed_streamers'] }} {{ $this->getManager()->isAdmin() ? 'streamers' : 'assigned' }}</p>
                        </div>
                    </div>
                </div>

                <a href="{{ route('filament.admin.resources.streamers.index') }}"
                    class="block w-full px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold text-center transition">
                    View Streamers →
                </a>
            </div>

            <!-- Status Breakdown Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 hover:shadow-lg transition">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="text-2xl">📊</span>
                            Packet Status
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Overview of all packet statuses</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 bg-amber-500 rounded-full"></span>
                            <span class="text-sm text-gray-600 dark:text-gray-300">Pending Review</span>
                        </div>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $stats['pending_review'] }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                            <span class="text-sm text-gray-600 dark:text-gray-300">Approved</span>
                        </div>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $stats['approved'] }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                            <span class="text-sm text-gray-600 dark:text-gray-300">Rejected</span>
                        </div>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $stats['rejected'] }}</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 hover:shadow-lg transition">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="text-2xl">⚡</span>
                            Quick Actions
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Fast shortcuts to common tasks</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <a href="{{ route('filament.admin.pages.manager-profit-share') }}"
                        class="flex items-center gap-3 p-3 bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 dark:hover:bg-amber-900/40 rounded-lg transition text-gray-900 dark:text-white">
                        <span class="text-lg">⏳</span>
                        <span class="font-semibold text-sm">Review Pending Packets</span>
                    </a>
                    <a href="{{ route('filament.admin.resources.streamers.index') }}"
                        class="flex items-center gap-3 p-3 bg-purple-50 dark:bg-purple-900/20 hover:bg-purple-100 dark:hover:bg-purple-900/40 rounded-lg transition text-gray-900 dark:text-white">
                        <span class="text-lg">👥</span>
                        <span class="font-semibold text-sm">View All Streamers</span>
                    </a>
                    @if ($this->getManager()->isAdmin())
                        <a href="{{ route('filament.admin.pages.inventory-dashboard') }}"
                            class="flex items-center gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 rounded-lg transition text-gray-900 dark:text-white">
                            <span class="text-lg">📦</span>
                            <span class="font-semibold text-sm">Inventory Dashboard</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Info Banner -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-6">
            <div class="flex gap-3">
                <span class="text-2xl">ℹ️</span>
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Review Guidelines</h3>
                    <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                        <li>✓ Check calculations match the streamer's logs for the month</li>
                        <li>✓ Verify profit share percentage is correctly applied</li>
                        <li>✓ Provide clear feedback if rejecting a packet</li>
                        <li>✓ Keep records of all approvals and rejections for audit purposes</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
