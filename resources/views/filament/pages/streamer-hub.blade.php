<x-filament-panels::page>
    <div class="space-y-8">
        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg p-6 md:p-8 text-white">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold mb-2">Welcome back, {{ $this->getStreamer()->name }}! 👋</h1>
                    <p class="text-indigo-100">Here's your streaming dashboard. Quick access to everything you need.</p>
                </div>
                <div class="flex gap-3 flex-wrap md:flex-nowrap">
                    <a href="{{ route('filament.admin.resources.streamer-logs.index') }}"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg font-semibold transition">
                        <x-heroicon-o-plus-circle class="h-5 w-5" />
                        <span class="hidden sm:inline">New Log</span>
                    </a>
                    @livewire('create-manual-show', ['streamer' => $this->getStreamer()], key('create-show-' . $this->getStreamer()->id))
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 md:p-6">
                <p class="text-xs md:text-sm text-blue-600 dark:text-blue-400 uppercase font-semibold">Total Shows</p>
                <p class="text-2xl md:text-3xl font-bold text-blue-900 dark:text-blue-100 mt-2">{{ $stats['total_shows'] }}</p>
                <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">{{ $stats['upcoming_shows'] }} upcoming</p>
            </div>

            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-4 md:p-6">
                <p class="text-xs md:text-sm text-green-600 dark:text-green-400 uppercase font-semibold">Total Revenue</p>
                <p class="text-2xl md:text-3xl font-bold text-green-900 dark:text-green-100 mt-2">${{ number_format($stats['total_revenue'], 0) }}</p>
                <p class="text-xs text-green-600 dark:text-green-400 mt-1">All time</p>
            </div>

            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-4 md:p-6">
                <p class="text-xs md:text-sm text-amber-600 dark:text-amber-400 uppercase font-semibold">Logs Pending</p>
                <p class="text-2xl md:text-3xl font-bold text-amber-900 dark:text-amber-100 mt-2">{{ $stats['logs_pending'] }}</p>
                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">Need logging</p>
            </div>

            <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 rounded-lg p-4 md:p-6">
                <p class="text-xs md:text-sm text-purple-600 dark:text-purple-400 uppercase font-semibold">Share Pending</p>
                <p class="text-2xl md:text-3xl font-bold text-purple-900 dark:text-purple-100 mt-2">{{ $stats['profit_share_pending'] }}</p>
                <p class="text-xs text-purple-600 dark:text-purple-400 mt-1">Awaiting approval</p>
            </div>
        </div>

        <!-- Main Actions Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Shows Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 hover:shadow-lg transition">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="text-2xl">📹</span>
                            My Shows
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">View upcoming and past shows, create new ones</p>
                    </div>
                </div>

                <div class="space-y-3 mb-4">
                    <div class="flex items-center gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <span class="text-2xl">📅</span>
                        <div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Upcoming</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['upcoming_shows'] }} shows</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                        <span class="text-2xl">📝</span>
                        <div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Pending Logs</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['logs_pending'] }} shows</p>
                        </div>
                    </div>
                </div>

                <a href="{{ route('filament.admin.pages.streamer-shows') }}"
                    class="block w-full px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold text-center transition">
                    Manage Shows →
                </a>
            </div>

            <!-- Profit Share Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 hover:shadow-lg transition">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="text-2xl">💰</span>
                            Profit Share
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Review monthly packets, submit for approval</p>
                    </div>
                </div>

                <div class="space-y-3 mb-4">
                    <div class="flex items-center gap-3 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <span class="text-2xl">✓</span>
                        <div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Approved</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['profit_share_approved'] }} packets</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg">
                        <span class="text-2xl">⏳</span>
                        <div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Pending Review</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['profit_share_pending'] }} packets</p>
                        </div>
                    </div>
                </div>

                <a href="{{ route('filament.admin.pages.streamer-profit-share') }}"
                    class="block w-full px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold text-center transition">
                    View Packets →
                </a>
            </div>

            <!-- Streamer Logs Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 hover:shadow-lg transition">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="text-2xl">📊</span>
                            Stream Logs
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Log stream details, revenue, and items</p>
                    </div>
                </div>

                <div class="space-y-3 mb-4">
                    <div class="flex items-center gap-3 p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                        <span class="text-2xl">✍️</span>
                        <div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Quick Access</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">Create & manage logs</p>
                        </div>
                    </div>
                </div>

                <a href="{{ route('filament.admin.resources.streamer-logs.index') }}"
                    class="block w-full px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-semibold text-center transition">
                    View Logs →
                </a>
            </div>

            <!-- Quick Actions Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 hover:shadow-lg transition">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="text-2xl">⚡</span>
                            Quick Actions
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Common tasks and shortcuts</p>
                    </div>
                </div>

                <div class="space-y-2">
                    {{--
                        This pointed at streamer-logs.create, which is not a
                        route: the resource has no create page, and could not
                        sensibly have one — a report belongs to a show, so
                        there is nothing to attach a report created from
                        nowhere to. The route helper threw on it, which took
                        the whole Streamer Hub down.

                        End of Stream is where a report is actually filed.
                    --}}
                    <a href="{{ route('filament.admin.pages.end-of-stream') }}"
                        class="flex items-center gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 rounded-lg transition text-gray-900 dark:text-white">
                        <span class="text-lg">➕</span>
                        <span class="font-semibold text-sm">File an End of Stream Report</span>
                    </a>
                    <a href="{{ route('filament.admin.pages.streamer-shows') }}?tab=create"
                        class="flex items-center gap-3 p-3 bg-indigo-50 dark:bg-indigo-900/20 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 rounded-lg transition text-gray-900 dark:text-white">
                        <span class="text-lg">📹</span>
                        <span class="font-semibold text-sm">Create Manual Show</span>
                    </a>
                    <a href="{{ route('filament.admin.pages.streamer-profit-share') }}"
                        class="flex items-center gap-3 p-3 bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/40 rounded-lg transition text-gray-900 dark:text-white">
                        <span class="text-lg">📤</span>
                        <span class="font-semibold text-sm">Submit Profit Share</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activity Notice -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-6">
            <div class="flex gap-3">
                <span class="text-2xl">💡</span>
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Pro Tips</h3>
                    <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                        <li>✓ Log your streams immediately after they end to keep profit share calculations accurate</li>
                        <li>✓ Review your profit share packet at the end of each month before submitting for approval</li>
                        <li>✓ Create manual shows if your stream wasn't automatically imported from Whatnot</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
