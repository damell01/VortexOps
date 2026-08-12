<x-filament-panels::page>
    <div class="space-y-8">
        <!-- Create Manual Show Component -->
        @livewire('create-manual-show', ['streamer' => $this->getStreamer()])

        <!-- Upcoming Shows -->
        <div>
            <div class="mb-4">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Upcoming Shows</h2>
                <p class="text-gray-600 dark:text-gray-400">Scheduled shows you'll be streaming</p>
            </div>

            @php
                $upcomingShows = $this->getStreamer()
                    ->shows()
                    ->where('show_date', '>', now())
                    ->orderBy('show_date', 'asc')
                    ->get();
            @endphp

            @if ($upcomingShows->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($upcomingShows as $show)
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg border border-blue-200 dark:border-blue-700 p-6 hover:shadow-lg transition">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $show->title }}</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $show->channel }}</p>
                                </div>
                                <span class="inline-block px-3 py-1 bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 rounded-full text-xs font-semibold">
                                    📅 Upcoming
                                </span>
                            </div>

                            <div class="space-y-2 text-sm mb-4">
                                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                    <span class="text-lg">📍</span>
                                    <span>{{ $show->show_date->format('F j, Y') }} at {{ $show->show_date->format('g:i A') }}</span>
                                </div>
                                @if ($show->gross_revenue)
                                    <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                        <span class="text-lg">💰</span>
                                        <span>Estimated Revenue: ${{ number_format($show->gross_revenue, 2) }}</span>
                                    </div>
                                @endif
                                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                    <span class="text-lg">📦</span>
                                    <span>Expected Items: {{ $show->orders()->count() ?? 'TBD' }}</span>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-blue-200 dark:border-blue-700">
                                <a href="{{ route('filament.admin.resources.shows.edit', $show) }}"
                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-semibold text-sm">
                                    View Details →
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-8 text-center">
                    <p class="text-blue-900 dark:text-blue-200 font-semibold">No upcoming shows scheduled</p>
                    <p class="text-blue-700 dark:text-blue-400 text-sm mt-1">You don't have any shows scheduled yet. Check back soon!</p>
                </div>
            @endif
        </div>

        <!-- Past Shows -->
        <div>
            <div class="mb-4">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Past Shows</h2>
                <p class="text-gray-600 dark:text-gray-400">Shows you've already streamed</p>
            </div>

            @php
                $pastShows = $this->getStreamer()
                    ->shows()
                    ->where('show_date', '<=', now())
                    ->orderBy('show_date', 'desc')
                    ->get();
            @endphp

            @if ($pastShows->count() > 0)
                <div class="space-y-3">
                    @foreach ($pastShows as $show)
                        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition flex items-center justify-between">
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $show->title }}</h3>
                                <div class="flex flex-wrap gap-4 mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    <span>📅 {{ $show->show_date->format('M j, Y') }}</span>
                                    <span>📦 {{ $show->orders()->count() }} items</span>
                                    <span>💰 Revenue: ${{ number_format($show->gross_revenue, 2) }}</span>
                                </div>
                            </div>

                            <div class="flex gap-2 ml-4">
                                @if ($show->streamerLogEntry)
                                    <span class="inline-block px-3 py-1 bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 rounded-full text-xs font-semibold">
                                        ✓ Logged
                                    </span>
                                @else
                                    <span class="inline-block px-3 py-1 bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300 rounded-full text-xs font-semibold">
                                        ⏳ Pending Log
                                    </span>
                                @endif

                                <a href="{{ route('filament.admin.resources.shows.edit', $show) }}"
                                    class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded font-medium transition">
                                    View
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination/Load More could go here if needed -->
            @else
                <div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-8 text-center">
                    <p class="text-gray-600 dark:text-gray-400 font-semibold">No past shows</p>
                </div>
            @endif
        </div>

        <!-- Show Statistics -->
        <div class="bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 rounded-lg border border-purple-200 dark:border-purple-700 p-6">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Your Statistics</h3>

            @php
                $totalShows = $this->getStreamer()->shows()->count();
                $totalRevenue = $this->getStreamer()->shows()->sum('gross_revenue');
                $totalItems = $this->getStreamer()->shows()->withCount('orders')->get()->sum('orders_count');
                $completedLogs = $this->getStreamer()->streamerLogEntries()->count();
            @endphp

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded p-4 border border-purple-200 dark:border-purple-700">
                    <p class="text-xs text-purple-600 dark:text-purple-400 uppercase font-semibold">Total Shows</p>
                    <p class="text-3xl font-bold text-purple-900 dark:text-purple-100">{{ $totalShows }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded p-4 border border-green-200 dark:border-green-700">
                    <p class="text-xs text-green-600 dark:text-green-400 uppercase font-semibold">Total Revenue</p>
                    <p class="text-3xl font-bold text-green-900 dark:text-green-100">${{ number_format($totalRevenue, 0) }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded p-4 border border-blue-200 dark:border-blue-700">
                    <p class="text-xs text-blue-600 dark:text-blue-400 uppercase font-semibold">Items Sold</p>
                    <p class="text-3xl font-bold text-blue-900 dark:text-blue-100">{{ $totalItems }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded p-4 border border-indigo-200 dark:border-indigo-700">
                    <p class="text-xs text-indigo-600 dark:text-indigo-400 uppercase font-semibold">Logs Submitted</p>
                    <p class="text-3xl font-bold text-indigo-900 dark:text-indigo-100">{{ $completedLogs }}</p>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
