<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Streamer Actions --}}
        @if(auth()->user()->isStreamer() && !auth()->user()->isAdmin())
        <div>
            @livewire('create-manual-show', ['streamer' => auth()->user()->streamer])
        </div>
        @endif

        {{-- Filters & Search --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Filters</h2>
                <button wire:click="clearFilters" type="button"
                    class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                    Clear all
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                {{-- Search --}}
                <div>
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300 block mb-2">Search</label>
                    <input type="text" wire:model.live="searchQuery"
                        placeholder="Show title or notes…"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500" />
                </div>

                {{-- Status Filter --}}
                <div>
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300 block mb-2">Status</label>
                    <select wire:model.live="filterStatus"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                        <option value="all">All Shows</option>
                        <option value="active">🔴 Active</option>
                        <option value="completed">✓ Completed</option>
                        <option value="closed">⊘ Closed</option>
                    </select>
                </div>

                {{-- Streamer Filter (Admin Only) --}}
                @if(auth()->user()->isAdmin())
                <div>
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300 block mb-2">Streamer</label>
                    <select wire:model.live="filterStreamer"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Streamers</option>
                        @foreach($this->streamers as $streamer)
                        <option value="{{ $streamer->id }}">{{ $streamer->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Sort --}}
                <div>
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300 block mb-2">Sort By</label>
                    <select wire:model.live="sortBy"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                        <option value="date">Date (Newest)</option>
                        <option value="revenue">Revenue (Highest)</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Stats Summary --}}
        @php
            $stats = $this->shows;
            $activeCount = $stats->where('status', 'active')->count();
            $completedCount = $stats->where('status', 'completed')->count();
            $pendingSubmissions = $stats->filter(fn ($s) => $s->status !== 'closed' && !$s->streamerLogEntry)->count();
            $totalRevenue = $stats->sum('gross_revenue');
            $avgRevenue = $completedCount > 0 ? $totalRevenue / $completedCount : 0;

            // Compact tiles: a neutral card with a small tinted icon, rather
            // than a full colour wash with a 3xl emoji. Five fit on one row
            // and the table rises about 300px up the page.
            $tiles = [
                ['Active Shows',       $activeCount,                              'Currently live', 'blue',   'heroicon-o-play-circle'],
                ['Completed Shows',    $completedCount,                           'This month',     'green',  'heroicon-o-check-circle'],
                ['Pending Submission', $pendingSubmissions,                       'Awaiting review','amber',  'heroicon-o-clock'],
                ['Total Revenue',      '$' . number_format($totalRevenue, 0),     'All time',       'purple', 'heroicon-o-currency-dollar'],
                ['Avg Revenue',        '$' . number_format($avgRevenue, 0),       'Per show',       'orange', 'heroicon-o-chart-bar'],
            ];
        @endphp

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            @foreach ($tiles as [$label, $value, $sub, $tone, $icon])
                <div class="vx-stat-tile">
                    <span class="vx-stat-icon vx-tone-{{ $tone }}">
                        <x-filament::icon :icon="$icon" class="h-4 w-4" />
                    </span>
                    <span class="vx-stat-body">
                        <span class="vx-stat-label">{{ $label }}</span>
                        <span class="vx-stat-value">{{ $value }}</span>
                        <span class="vx-stat-sub">{{ $sub }}</span>
                    </span>
                </div>
            @endforeach
        </div>


        {{-- Shows Table --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full vx-cardify">
                    <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Show</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Streamer</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-900 dark:text-white">Date</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white">Revenue</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-900 dark:text-white">Items</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-900 dark:text-white">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-900 dark:text-white">Form</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-900 dark:text-white">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($this->shows as $show)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $show->title }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">ID: {{ $show->whatnot_show_id ?? $show->id }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    @foreach($show->streamers as $streamer)
                                        <span class="inline-block text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                                            {{ $streamer->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-400">
                                {{ $show->show_date->format('M d, Y') }}
                                @if($show->start_time)
                                    <div class="text-xs">{{ $show->start_time->format('H:i') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                ${{ number_format($show->gross_revenue ?? 0, 2) }}
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-400">
                                {{ count($show->orders) }} items
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $statusColors = [
                                        'active' => 'bg-red-100 dark:bg-red-950 text-red-800 dark:text-red-200',
                                        'completed' => 'bg-green-100 dark:bg-green-950 text-green-800 dark:text-green-200',
                                        'closed' => 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200',
                                    ];
                                    $statusIcons = ['active' => '🔴', 'completed' => '✓', 'closed' => '⊘'];
                                @endphp
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium {{ $statusColors[$show->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $statusIcons[$show->status] ?? '' }}
                                    {{ ucfirst($show->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $logEntry = $show->streamerLogEntry;
                                    if (!$logEntry) {
                                        $statusLabel = 'Pending';
                                        $statusClass = 'bg-yellow-100 dark:bg-yellow-950 text-yellow-800 dark:text-yellow-200';
                                        $statusIcon = '⏱';
                                    } elseif ($logEntry->status === 'admin_approved') {
                                        $statusLabel = 'Approved';
                                        $statusClass = 'bg-green-100 dark:bg-green-950 text-green-800 dark:text-green-200';
                                        $statusIcon = '✓';
                                    } else {
                                        $statusLabel = 'Submitted';
                                        $statusClass = 'bg-blue-100 dark:bg-blue-950 text-blue-800 dark:text-blue-200';
                                        $statusIcon = '📋';
                                    }
                                @endphp
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium {{ $statusClass }}">
                                    {{ $statusIcon }} {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex flex-col gap-2">
                                    @if($show->streamerLogEntry)
                                        <a href="{{ route('filament.admin.resources.streamer-logs.edit', $show->streamerLogEntry->id) }}"
                                            class="text-xs px-2 py-1 rounded bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-200 hover:bg-blue-200 dark:hover:bg-blue-800 font-medium text-center">
                                            📋 View Submission
                                        </a>
                                        @if(auth()->user()?->isAdmin() || auth()->user()?->isOwner())
                                            <button wire:click="requestFormResubmission('{{ $show->id }}')"
                                                class="text-xs px-2 py-1 rounded bg-orange-100 dark:bg-orange-900 text-orange-700 dark:text-orange-200 hover:bg-orange-200 dark:hover:bg-orange-800 font-medium">
                                                🔄 Request Changes
                                            </button>
                                        @endif
                                    @elseif($show->status !== 'closed')
                                        @if(auth()->user()?->isStreamer())
                                            <a href="{{ route('filament.admin.pages.end-of-stream', ['showId' => $show->id]) }}"
                                                class="text-xs px-2 py-1 rounded bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-200 hover:bg-amber-200 dark:hover:bg-amber-800 font-medium">
                                                📝 Submit Form
                                            </a>
                                        @elseif(auth()->user()?->isAdmin())
                                            <button wire:click="requestFormSubmission('{{ $show->id }}')"
                                                class="text-xs px-2 py-1 rounded bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 hover:bg-red-200 dark:hover:bg-red-800 font-medium">
                                                📬 Request Submission
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                <div class="text-3xl mb-2">📺</div>
                                <p>No shows found matching your filters</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Legend --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 p-4">
            <p class="text-xs font-semibold text-gray-900 dark:text-white mb-3">Legend</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs text-gray-600 dark:text-gray-400">
                <div>🔴 <strong>Active</strong> — Show in progress</div>
                <div>✓ <strong>Completed</strong> — Show finished</div>
                <div>⊘ <strong>Closed</strong> — Locked & finalized</div>
                <div>💰 <strong>Revenue</strong> — Gross sales amount</div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
