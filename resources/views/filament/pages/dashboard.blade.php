<x-filament-widgets::widgets
    :widgets="$widgets"
    :columns="$columns"
/>

<!-- Role-Based Quick Start Guidance -->
<div class="mt-8">
    @if(auth()->user()?->isStreamer() && !auth()->user()?->isAdmin())
        <!-- STREAMER WORKFLOW -->
        <div class="space-y-4">
            <h2 class="text-xl font-bold text-gray-900">📋 Your Streaming Workflow</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Step 1: Check Shows to Review -->
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                    <h3 class="font-bold text-blue-900 mb-2">Step 1: Review Your Shows</h3>
                    <p class="text-sm text-blue-800 mb-3">Check if any of your shows need review or mapping.</p>
                    <a href="{{ route('filament.admin.resources.streamer-log-entries.index') }}" class="inline-block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium">
                        Go to Streamer Logs →
                    </a>
                </div>

                <!-- Step 2: Map Inventory -->
                <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                    <h3 class="font-bold text-green-900 mb-2">Step 2: Map Inventory Items</h3>
                    <p class="text-sm text-green-800 mb-3">Map each item sold to your inventory system.</p>
                    <a href="{{ route('filament.admin.resources.inventory-items.index') }}" class="inline-block bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm font-medium">
                        View Inventory →
                    </a>
                </div>

                <!-- Step 3: Check Payouts -->
                <div class="bg-purple-50 border-l-4 border-purple-500 p-4 rounded">
                    <h3 class="font-bold text-purple-900 mb-2">Step 3: Earnings & Payouts</h3>
                    <p class="text-sm text-purple-800 mb-3">View your earnings and payout schedule.</p>
                    <a href="{{ route('filament.admin.resources.payouts.index') }}" class="inline-block bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded text-sm font-medium">
                        View Payouts →
                    </a>
                </div>

                <!-- Step 4: Need Help? -->
                <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded">
                    <h3 class="font-bold text-amber-900 mb-2">📚 Documentation</h3>
                    <p class="text-sm text-amber-800 mb-3">Learn how to use each feature of the platform.</p>
                    <button class="inline-block bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded text-sm font-medium" onclick="alert('Help docs coming soon!')">
                        Get Help →
                    </button>
                </div>
            </div>
        </div>

    @elseif(auth()->user()?->isAdmin())
        <!-- ADMIN WORKFLOW -->
        <div class="space-y-4">
            <h2 class="text-xl font-bold text-gray-900">⚙️ Admin Operations Hub</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Section 1: Streams Management -->
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 p-4 rounded-lg">
                    <h3 class="font-bold text-blue-900 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4z"/>
                        </svg>
                        Streams Management
                    </h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('filament.admin.resources.shows.index') }}" class="text-blue-600 hover:text-blue-800 font-medium">→ View All Shows</a></li>
                        <li><a href="{{ route('filament.admin.resources.shows.index') }}?status=pending_review" class="text-blue-600 hover:text-blue-800 font-medium">→ Pending Review</a></li>
                        <li><a href="{{ route('filament.admin.resources.streamer-log-entries.index') }}" class="text-blue-600 hover:text-blue-800 font-medium">→ Streamer Logs</a></li>
                    </ul>
                </div>

                <!-- Section 2: Inventory Management -->
                <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 p-4 rounded-lg">
                    <h3 class="font-bold text-green-900 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V4a2 2 0 00-2-2H6z"/>
                        </svg>
                        Inventory Control
                    </h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('filament.admin.resources.inventory-items.index') }}" class="text-green-600 hover:text-green-800 font-medium">→ All Items</a></li>
                        <li><a href="{{ route('filament.admin.resources.inventory-stocks.index') }}" class="text-green-600 hover:text-green-800 font-medium">→ Stock Levels</a></li>
                        <li><a href="{{ route('filament.admin.resources.pallets.index') }}" class="text-green-600 hover:text-green-800 font-medium">→ Receiving Pallets</a></li>
                    </ul>
                </div>

                <!-- Section 3: Financial Management -->
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 p-4 rounded-lg">
                    <h3 class="font-bold text-purple-900 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M8.16 5.314l4.897-4.897a1 1 0 011.415 0l4.897 4.897a1 1 0 01-1.415 1.415L13 4.828V10a1 1 0 11-2 0V4.828l-3.793 3.793a1 1 0 01-1.415-1.415z"/>
                        </svg>
                        Financial Hub
                    </h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('filament.admin.resources.payouts.index') }}" class="text-purple-600 hover:text-purple-800 font-medium">→ Payouts</a></li>
                        <li><a href="{{ route('filament.admin.resources.streamers.index') }}" class="text-purple-600 hover:text-purple-800 font-medium">→ Streamers</a></li>
                        <li><a href="{{ route('filament.admin.resources.deduction-requests.index') }}" class="text-purple-600 hover:text-purple-800 font-medium">→ Deductions</a></li>
                    </ul>
                </div>

                <!-- Section 4: Fulfillment -->
                <div class="bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-200 p-4 rounded-lg">
                    <h3 class="font-bold text-orange-900 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        </svg>
                        Fulfillment & Shipping
                    </h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('filament.admin.resources.fulfillment-requests.index') }}" class="text-orange-600 hover:text-orange-800 font-medium">→ Fulfillment Queue</a></li>
                        <li><a href="{{ route('filament.admin.resources.shipping-surcharges.index') }}" class="text-orange-600 hover:text-orange-800 font-medium">→ Shipping Surcharges</a></li>
                    </ul>
                </div>

                <!-- Section 5: Settings & Configuration -->
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 border border-gray-200 p-4 rounded-lg">
                    <h3 class="font-bold text-gray-900 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z"/>
                        </svg>
                        Configuration
                    </h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('filament.admin.resources.streamers.index') }}" class="text-gray-600 hover:text-gray-800 font-medium">→ Streamers</a></li>
                        <li><a href="{{ route('filament.admin.resources.vendors.index') }}" class="text-gray-600 hover:text-gray-800 font-medium">→ Vendors</a></li>
                        <li><a href="{{ route('filament.admin.pages.settings') }}" class="text-gray-600 hover:text-gray-800 font-medium">→ Settings</a></li>
                    </ul>
                </div>

                <!-- Section 6: System Status -->
                <div class="bg-gradient-to-br from-teal-50 to-teal-100 border border-teal-200 p-4 rounded-lg">
                    <h3 class="font-bold text-teal-900 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                        </svg>
                        System Status
                    </h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('filament.admin.pages.system-health') }}" class="text-teal-600 hover:text-teal-800 font-medium">→ Health Check</a></li>
                        <li><a href="{{ route('filament.admin.resources.activity-log.index') }}" class="text-teal-600 hover:text-teal-800 font-medium">→ Activity Log</a></li>
                        <li class="text-teal-600">{{ now()->format('l, F j, Y') }}</li>
                    </ul>
                </div>
            </div>
        </div>

    @else
        <!-- DEFAULT USER VIEW -->
        <div class="bg-blue-50 border border-blue-200 p-6 rounded-lg">
            <h2 class="text-lg font-bold text-blue-900 mb-2">Welcome to VortexOps</h2>
            <p class="text-blue-800">You're all set! Use the navigation on the left to explore available features and modules.</p>
        </div>
    @endif
</div>

<style>
    /* Better spacing and typography */
    .grid > div {
        transition: all 0.3s ease;
    }

    .grid > div:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    /* Mobile optimization */
    @media (max-width: 640px) {
        .grid {
            grid-template-columns: 1fr !important;
        }

        h2 {
            font-size: 1.25rem;
        }
    }
</style>
