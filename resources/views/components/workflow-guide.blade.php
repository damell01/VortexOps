@php
    $user = auth()->user();
    $isStreamer = $user?->isStreamer() && !$user->isAdmin();
    $isFulfillment = $user?->isFulfillment() && !$user->isAdmin();
    $isAdmin = $user?->isAdmin();
@endphp

<div class="mt-8 space-y-6 animate-fade-in-up">
    @if($isStreamer)
        <!-- Streamer Workflow Guide -->
        <div class="overflow-hidden rounded-xl bg-white dark:bg-gray-900 shadow-lg">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-8 text-white">
                <h2 class="text-2xl font-bold">📚 Streamer Workflow Guide</h2>
                <p class="mt-2 text-blue-100">Follow these steps after your show is imported</p>
            </div>

            <div class="p-6">
                <!-- Step 1: Review Show -->
                <div class="mb-8 flex gap-6">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                            <span class="text-lg font-bold text-blue-600 dark:text-blue-300">1</span>
                        </div>
                    </div>
                    <div class="flex-grow">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Review Your Shows</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-3">Your show has been imported! Go to your Streamer Logs to see shows waiting for review.</p>
                        <ul class="list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400">
                            <li>Shows automatically import after live streams on Whatnot</li>
                            <li>You'll see all items that were sold during the show</li>
                            <li>Red highlighted rows need to be mapped to your inventory</li>
                        </ul>
                        <a href="{{ route('filament.admin.resources.streamer-log-entries.index') }}" class="mt-4 inline-flex items-center gap-2 text-blue-600 dark:text-blue-400 font-medium hover:underline">
                            Go to Streamer Logs <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </a>
                    </div>
                </div>

                <hr class="my-6 border-gray-200 dark:border-gray-700">

                <!-- Step 2: Map Items -->
                <div class="mb-8 flex gap-6">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                            <span class="text-lg font-bold text-green-600 dark:text-green-300">2</span>
                        </div>
                    </div>
                    <div class="flex-grow">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Map Each Item to Your Inventory</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-3">Click the red "⚠ Choose item" cells to map each sale to your inventory items.</p>
                        <ul class="list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400 mb-4">
                            <li>Click "Map Item" button to open the full-screen search modal</li>
                            <li>Search by item name, SKU, or barcode</li>
                            <li>Select the item and location it should ship from</li>
                            <li>We'll auto-fill the unit cost from your inventory</li>
                            <li>Review and save your selection</li>
                        </ul>
                        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4">
                            <p class="text-sm text-green-800 dark:text-green-300">
                                <strong>Pro Tip:</strong> Use the "Fill Costs" button to automatically set unit costs for all mapped items at once!
                            </p>
                        </div>
                    </div>
                </div>

                <hr class="my-6 border-gray-200 dark:border-gray-700">

                <!-- Step 3: Enter Costs -->
                <div class="mb-8 flex gap-6">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900">
                            <span class="text-lg font-bold text-purple-600 dark:text-purple-300">3</span>
                        </div>
                    </div>
                    <div class="flex-grow">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Enter Show Costs & Revenue</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-3">Scroll to the top of the show and enter the gross revenue and product costs.</p>
                        <ul class="list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400">
                            <li><strong>Gross Revenue:</strong> Total money taken in during the show</li>
                            <li><strong>Product Cost:</strong> Sum of what you paid wholesale for items sold</li>
                            <li>These numbers are used to calculate your profit share payout</li>
                        </ul>
                    </div>
                </div>

                <hr class="my-6 border-gray-200 dark:border-gray-700">

                <!-- Step 4: Submit -->
                <div class="flex gap-6">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900">
                            <span class="text-lg font-bold text-orange-600 dark:text-orange-300">4</span>
                        </div>
                    </div>
                    <div class="flex-grow">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Review & Submit</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-3">Once all items are mapped and costs are entered, submit your log for admin review.</p>
                        <ul class="list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400">
                            <li>An admin will review your submission within 24 hours</li>
                            <li>They may request adjustments if anything seems off</li>
                            <li>Once approved, your payout will be calculated automatically</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    @elseif($isFulfillment)
        <!-- Fulfillment Workflow Guide -->
        <div class="overflow-hidden rounded-xl bg-white dark:bg-gray-900 shadow-lg">
            <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-6 py-8 text-white">
                <h2 class="text-2xl font-bold">📦 Fulfillment Workflow Guide</h2>
                <p class="mt-2 text-orange-100">Your step-by-step packing and shipping process</p>
            </div>

            <div class="p-6">
                <!-- Step 1: View Shows -->
                <div class="mb-8 flex gap-6">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900">
                            <span class="text-lg font-bold text-orange-600 dark:text-orange-300">1</span>
                        </div>
                    </div>
                    <div class="flex-grow">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Check Your Fulfillment Queue</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-3">Go to your Fulfillment Center to see shows assigned to you.</p>
                        <a href="{{ route('filament.admin.resources.fulfillment-center.index') }}" class="mt-3 inline-flex items-center gap-2 text-orange-600 dark:text-orange-400 font-medium hover:underline">
                            Open Fulfillment Center <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </a>
                    </div>
                </div>

                <hr class="my-6 border-gray-200 dark:border-gray-700">

                <!-- Step 2: Update Status -->
                <div class="mb-8 flex gap-6">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900">
                            <span class="text-lg font-bold text-red-600 dark:text-red-300">2</span>
                        </div>
                    </div>
                    <div class="flex-grow">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Update Item Shipping Status</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-3">As you process items, update their status in the table.</p>
                        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                            <p><strong>Ready to Ship:</strong> Item is packed and ready for pickup</p>
                            <p><strong>Shipped:</strong> Package has been handed off to carrier</p>
                            <p><strong>Delivered:</strong> Customer has received the package</p>
                        </div>
                    </div>
                </div>

                <hr class="my-6 border-gray-200 dark:border-gray-700">

                <!-- Step 3: Add Tracking -->
                <div class="mb-8 flex gap-6">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                            <span class="text-lg font-bold text-blue-600 dark:text-blue-300">3</span>
                        </div>
                    </div>
                    <div class="flex-grow">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Add Tracking Numbers</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-3">Enter the carrier tracking number for each shipment.</p>
                        <ul class="list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400">
                            <li>Customers can see their tracking info</li>
                            <li>Helps us monitor delivery performance</li>
                            <li>Required for delivery confirmation</li>
                        </ul>
                    </div>
                </div>

                <hr class="my-6 border-gray-200 dark:border-gray-700">

                <!-- Step 4: Monitor -->
                <div class="flex gap-6">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                            <span class="text-lg font-bold text-green-600 dark:text-green-300">4</span>
                        </div>
                    </div>
                    <div class="flex-grow">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Track Shipments</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-3">Visit your Shipments list to monitor in-transit and delivered packages.</p>
                        <a href="{{ route('filament.admin.resources.shipments.index') }}" class="mt-3 inline-flex items-center gap-2 text-green-600 dark:text-green-400 font-medium hover:underline">
                            View All Shipments <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>

    @elseif($isAdmin)
        <!-- Admin Workflow Guide -->
        <div class="overflow-hidden rounded-xl bg-white dark:bg-gray-900 shadow-lg">
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-8 text-white">
                <h2 class="text-2xl font-bold">⚙️ Admin Management Guide</h2>
                <p class="mt-2 text-purple-100">Key workflows to keep the platform running smoothly</p>
            </div>

            <div class="p-6 space-y-8">
                <!-- Section 1: Streams -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                            📺
                        </span>
                        Managing Streams
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400 ml-10">
                        <li><strong>Review Pending Shows:</strong> Check shows for accurate item mapping</li>
                        <li><strong>Approve Streamer Logs:</strong> Verify costs and approve for payout</li>
                        <li><strong>Handle Deductions:</strong> Review and approve cost deductions from streamers</li>
                    </ul>
                </div>

                <!-- Section 2: Inventory -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                            📦
                        </span>
                        Managing Inventory
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400 ml-10">
                        <li><strong>Receive Pallets:</strong> Log incoming inventory from vendors</li>
                        <li><strong>Set Reorder Levels:</strong> Configure low-stock alerts for items</li>
                        <li><strong>Control Access:</strong> Set which inventory locations each streamer can use</li>
                        <li><strong>Track Stock:</strong> Monitor stock levels across all locations</li>
                    </ul>
                </div>

                <!-- Section 3: Payouts -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900">
                            💰
                        </span>
                        Processing Payouts
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400 ml-10">
                        <li><strong>Calculate Payouts:</strong> Generate payout records after show approval</li>
                        <li><strong>Review Amounts:</strong> Verify calculations match payout formulas</li>
                        <li><strong>Process Payments:</strong> Create payment batches and mark as paid</li>
                    </ul>
                </div>

                <!-- Section 4: Fulfillment -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900">
                            🚚
                        </span>
                        Overseeing Fulfillment
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400 ml-10">
                        <li><strong>Assign Fulfillment:</strong> Route shows to fulfillment team members</li>
                        <li><strong>Monitor Progress:</strong> Track packing and shipping status</li>
                        <li><strong>Handle Issues:</strong> Address fulfillment problems and exceptions</li>
                    </ul>
                </div>
            </div>
        </div>
    @endif
</div>
