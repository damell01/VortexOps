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
                    <a href="{{ route('filament.admin.resources.streamer-log-entries.index', ['tableFilters[status][value]' => 'pending']) }}"
                       class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2.5 rounded-lg font-medium transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Get Started →
                    </a>
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

        {{-- Workflow Steps (Role-Specific) --}}
        @if(auth()->user()?->isStreamer() && !auth()->user()?->isAdmin())
            <div class="bg-gradient-to-r from-blue-500 via-blue-400 to-cyan-400 rounded-xl p-8 text-white shadow-lg">
                <h2 class="text-2xl font-bold mb-6">Your Streaming Workflow</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Step 1 -->
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-6 border-2 border-white/40">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="bg-white/40 rounded-full w-10 h-10 flex items-center justify-center font-bold text-lg">1</div>
                            <h3 class="text-lg font-bold">Review Show</h3>
                        </div>
                        <p class="text-sm text-blue-50">Your show was imported. Click on it to see the items that were sold.</p>
                    </div>

                    <!-- Step 2 -->
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-6 border-2 border-white/40">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="bg-white/40 rounded-full w-10 h-10 flex items-center justify-center font-bold text-lg">2</div>
                            <h3 class="text-lg font-bold">Map Items</h3>
                        </div>
                        <p class="text-sm text-blue-50">Match each sold item to your inventory. Use our full-screen modal for quick searching.</p>
                    </div>

                    <!-- Step 3 -->
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-6 border-2 border-white/40">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="bg-white/40 rounded-full w-10 h-10 flex items-center justify-center font-bold text-lg">3</div>
                            <h3 class="text-lg font-bold">Approve & Submit</h3>
                        </div>
                        <p class="text-sm text-blue-50">Review the costs and submit for admin approval. Your payout will be calculated.</p>
                    </div>
                </div>
            </div>
        @elseif(auth()->user()?->isFulfillment() && !auth()->user()?->isAdmin())
            <div class="bg-gradient-to-r from-orange-500 via-orange-400 to-red-400 rounded-xl p-8 text-white shadow-lg">
                <h2 class="text-2xl font-bold mb-6">Your Fulfillment Workflow</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Step 1 -->
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-6 border-2 border-white/40">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="bg-white/40 rounded-full w-10 h-10 flex items-center justify-center font-bold text-lg">1</div>
                            <h3 class="text-lg font-bold">View Shows</h3>
                        </div>
                        <p class="text-sm text-orange-50">Check your Fulfillment Center to see shows ready for packing.</p>
                    </div>

                    <!-- Step 2 -->
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-6 border-2 border-white/40">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="bg-white/40 rounded-full w-10 h-10 flex items-center justify-center font-bold text-lg">2</div>
                            <h3 class="text-lg font-bold">Pack & Ship</h3>
                        </div>
                        <p class="text-sm text-orange-50">Pack items and update shipping statuses. Add tracking numbers as you go.</p>
                    </div>

                    <!-- Step 3 -->
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-6 border-2 border-white/40">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="bg-white/40 rounded-full w-10 h-10 flex items-center justify-center font-bold text-lg">3</div>
                            <h3 class="text-lg font-bold">Track Delivery</h3>
                        </div>
                        <p class="text-sm text-orange-50">Monitor shipments and mark as delivered when customers receive items.</p>
                    </div>
                </div>
            </div>
        @elseif(auth()->user()?->isAdmin())
            <div class="bg-gradient-to-r from-purple-500 via-purple-400 to-pink-400 rounded-xl p-8 text-white shadow-lg">
                <h2 class="text-2xl font-bold mb-6">Admin Operations Overview</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <!-- Streams -->
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-6 border-2 border-white/40">
                        <div class="flex items-center gap-3 mb-4">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4z"/></svg>
                            <h3 class="text-lg font-bold">Streams</h3>
                        </div>
                        <p class="text-sm text-purple-50">Review shows, map items, and approve streamer submissions.</p>
                    </div>

                    <!-- Inventory -->
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-6 border-2 border-white/40">
                        <div class="flex items-center gap-3 mb-4">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V4a2 2 0 00-2-2H6z" clip-rule="evenodd"/></svg>
                            <h3 class="text-lg font-bold">Inventory</h3>
                        </div>
                        <p class="text-sm text-purple-50">Manage items, receive pallets, and track stock levels.</p>
                    </div>

                    <!-- Payouts -->
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-6 border-2 border-white/40">
                        <div class="flex items-center gap-3 mb-4">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M8.16 5.314l4.897-4.897a1 1 0 011.415 0l4.897 4.897a1 1 0 01-1.415 1.415L13 4.828V10a1 1 0 11-2 0V4.828l-3.793 3.793a1 1 0 01-1.415-1.415z"/></svg>
                            <h3 class="text-lg font-bold">Payouts</h3>
                        </div>
                        <p class="text-sm text-purple-50">Process and track streamer payments and batches.</p>
                    </div>

                    <!-- Fulfillment -->
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-6 border-2 border-white/40">
                        <div class="flex items-center gap-3 mb-4">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/></svg>
                            <h3 class="text-lg font-bold">Fulfillment</h3>
                        </div>
                        <p class="text-sm text-purple-50">Oversee order packing, shipping, and delivery tracking.</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Detailed Workflow Guide --}}
        <x-workflow-guide></x-workflow-guide>
    </div>
</x-filament-panels::page>
