<x-filament-panels::page>
    <div class="space-y-6 animate-fade-in">
        {{-- Quick Status Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 animate-fade-in-up">
            @if(auth()->user()?->isStreamer() && !auth()->user()?->isAdmin())
                {{-- Streamer: Shows Awaiting Action --}}
                <div class="bg-gradient-to-br from-amber-50 to-orange-50 border-2 border-amber-200 rounded-xl p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-amber-900">Shows to Process</h3>
                        <span class="bg-amber-500 text-white rounded-full px-3 py-1 text-sm font-semibold">{{ $pendingShows ?? 0 }}</span>
                    </div>
                    <p class="text-sm text-amber-800 mb-4">Shows that need items mapped and costs entered</p>
                    <p class="text-xs text-amber-600">Check your Streamer Log in the sidebar to view and process shows</p>
                </div>

                {{-- Streamer: Pending Payouts --}}
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-green-900">Pending Payouts</h3>
                        <span class="bg-green-500 text-white rounded-full px-3 py-1 text-sm font-semibold">{{ $pendingPayouts ?? 0 }}</span>
                    </div>
                    <p class="text-sm text-green-800 mb-4">Approved payouts waiting to be processed</p>
                    <a href="{{ route('filament.admin.resources.payouts.index', ['tableFilters[status][value]' => 'approved']) }}"
                       class="inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white px-4 py-2.5 rounded-lg font-medium transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        View Payouts →
                    </a>
                </div>

                {{-- Streamer: Inventory Overview --}}
                <div class="bg-gradient-to-br from-purple-50 to-pink-50 border-2 border-purple-200 rounded-xl p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-purple-900">Your Inventory</h3>
                        <span class="bg-purple-500 text-white rounded-full px-3 py-1 text-sm font-semibold">{{ $inventoryCount ?? 0 }}</span>
                    </div>
                    <p class="text-sm text-purple-800 mb-4">Items you can map from your inventory</p>
                    <a href="{{ route('filament.admin.resources.inventory-items.index') }}"
                       class="inline-flex items-center gap-2 bg-purple-500 hover:bg-purple-600 text-white px-4 py-2.5 rounded-lg font-medium transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        View Inventory →
                    </a>
                </div>
            @elseif(auth()->user()?->isFulfillment() && !auth()->user()?->isAdmin())
                {{-- Fulfillment: Shows to Fulfill --}}
                <div class="bg-gradient-to-br from-blue-50 to-cyan-50 border-2 border-blue-200 rounded-xl p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-blue-900">Shows to Fulfill</h3>
                        <span class="bg-blue-500 text-white rounded-full px-3 py-1 text-sm font-semibold">{{ $showsToFulfill ?? 0 }}</span>
                    </div>
                    <p class="text-sm text-blue-800 mb-4">Shows with items ready to pack and ship</p>
                    <a href="{{ route('filament.admin.resources.fulfillment-center.index') }}"
                       class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2.5 rounded-lg font-medium transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.325 15.581l-5.404-5.404a1.5 1.5 0 10-2.122 2.122l5.404 5.404c.586.586 1.535.586 2.122 0zM9 3a6 6 0 100 12A6 6 0 009 3z"></path>
                        </svg>
                        Start Fulfilling →
                    </a>
                </div>

                {{-- Fulfillment: Shipments to Track --}}
                <div class="bg-gradient-to-br from-orange-50 to-red-50 border-2 border-orange-200 rounded-xl p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-orange-900">Shipments Ready</h3>
                        <span class="bg-orange-500 text-white rounded-full px-3 py-1 text-sm font-semibold">{{ $readyToShip ?? 0 }}</span>
                    </div>
                    <p class="text-sm text-orange-800 mb-4">Items packed and ready for carrier pickup</p>
                    <a href="{{ route('filament.admin.resources.shipments.index', ['tableFilters[status][value]' => 'Ready to Ship']) }}"
                       class="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white px-4 py-2.5 rounded-lg font-medium transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Review Shipments →
                    </a>
                </div>

                {{-- Fulfillment: In Transit --}}
                <div class="bg-gradient-to-br from-indigo-50 to-purple-50 border-2 border-indigo-200 rounded-xl p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-indigo-900">In Transit</h3>
                        <span class="bg-indigo-500 text-white rounded-full px-3 py-1 text-sm font-semibold">{{ $inTransit ?? 0 }}</span>
                    </div>
                    <p class="text-sm text-indigo-800 mb-4">Shipments currently being delivered</p>
                    <a href="{{ route('filament.admin.resources.shipments.index', ['tableFilters[status][value]' => 'Shipped']) }}"
                       class="inline-flex items-center gap-2 bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2.5 rounded-lg font-medium transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Track Shipments →
                    </a>
                </div>
            @elseif(auth()->user()?->isAdmin())
                {{-- Admin: Pending Shows --}}
                <div class="bg-gradient-to-br from-red-50 to-pink-50 border-2 border-red-200 rounded-xl p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-red-900">Pending Review</h3>
                        <span class="bg-red-500 text-white rounded-full px-3 py-1 text-sm font-semibold">{{ $pendingReview ?? 0 }}</span>
                    </div>
                    <p class="text-sm text-red-800 mb-4">Shows awaiting admin review and approval</p>
                    <a href="{{ route('filament.admin.resources.shows.index', ['tableFilters[status][value]' => 'pending_review']) }}"
                       class="inline-flex items-center gap-2 bg-red-500 hover:bg-red-600 text-white px-4 py-2.5 rounded-lg font-medium transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Review Now →
                    </a>
                </div>

                {{-- Admin: Pending Payouts --}}
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-green-900">Payouts to Process</h3>
                        <span class="bg-green-500 text-white rounded-full px-3 py-1 text-sm font-semibold">{{ $draftPayouts ?? 0 }}</span>
                    </div>
                    <p class="text-sm text-green-800 mb-4">Draft payouts ready for approval and payment</p>
                    <a href="{{ route('filament.admin.resources.payouts.index', ['tableFilters[status][value]' => 'draft']) }}"
                       class="inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white px-4 py-2.5 rounded-lg font-medium transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Process Payouts →
                    </a>
                </div>

                {{-- Admin: Inventory Management --}}
                <div class="bg-gradient-to-br from-blue-50 to-cyan-50 border-2 border-blue-200 rounded-xl p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-blue-900">Low Stock Items</h3>
                        <span class="bg-blue-500 text-white rounded-full px-3 py-1 text-sm font-semibold">{{ $lowStock ?? 0 }}</span>
                    </div>
                    <p class="text-sm text-blue-800 mb-4">Items below reorder level that need attention</p>
                    <a href="{{ route('filament.admin.resources.inventory-stocks.index') }}"
                       class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2.5 rounded-lg font-medium transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        Manage Inventory →
                    </a>
                </div>
            @endif
        </div>

        {{-- Dashboard Widgets --}}
        <x-filament-widgets::widgets
            :widgets="$this->getWidgets()"
            :columns="$this->getColumns()"
        />
    </div>
</x-filament-panels::page>
