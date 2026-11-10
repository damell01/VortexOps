<x-filament-panels::page>
    <div class="space-y-4 md:space-y-5">
        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-violet-700 via-violet-600 to-purple-500 rounded-2xl p-4 md:p-5 text-white shadow-lg shadow-violet-500/20">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h1 class="text-2xl md:text-2xl font-bold mb-1">Streamer Hub</h1>
                    <p class="text-violet-100">Here's your streaming dashboard. Quick access to everything you need.</p>
                </div>
                <div class="flex gap-2 flex-wrap md:flex-nowrap">
                    {{-- A plus icon labelled "New Log" that opened the list of
                         existing logs. Filing a report is End of Stream, the
                         same destination the quick action further down uses. --}}
                    <a href="{{ route('filament.admin.pages.end-of-stream') }}"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg font-semibold transition">
                        <x-heroicon-o-plus-circle class="h-5 w-5" />
                        <span class="inline">Report a Show</span>
                    </a>
                    @livewire('create-manual-show', ['streamer' => $this->getStreamer()], key('create-show-' . $this->getStreamer()->id))
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="vxr-stats">
            <x-vxr.stat label="Total Shows" :value="number_format($stats['total_shows'])" icon="heroicon-o-video-camera" tone="accent" :note="$stats['upcoming_shows'].' upcoming'" />
            <x-vxr.stat label="Total Revenue" :value="'$'.number_format($stats['total_revenue'], 0)" icon="heroicon-o-banknotes" tone="ok" note="All time" />
            <x-vxr.stat label="Logs Pending" :value="number_format($stats['logs_pending'])" icon="heroicon-o-clipboard-document-list" tone="warn" note="Need logging" />
            <x-vxr.stat label="Share Pending" :value="number_format($stats['profit_share_pending'])" icon="heroicon-o-clock" tone="info" note="Awaiting approval" />
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

                <a href="{{ \App\Filament\Pages\DashboardImproved::getUrl() }}"
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
                    <a href="{{ \App\Filament\Pages\EndOfStreamForm::getUrl() }}"
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
