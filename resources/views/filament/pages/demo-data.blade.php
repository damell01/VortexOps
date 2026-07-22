<x-filament-panels::page>
    @php
        $includes = [
            ['icon' => 'heroicon-o-video-camera', 'title' => 'Shows across the pipeline', 'body' => 'Reconciled, pending-approval, and pending-review shows — plus 15 historical shows so the charts have weeks of data.'],
            ['icon' => 'heroicon-o-shopping-cart', 'title' => 'Sold items (mapped & unmapped)', 'body' => 'Each show has orders; some are pre-mapped to inventory and some are left for a streamer to map in the log.'],
            ['icon' => 'heroicon-o-clipboard-document-list', 'title' => 'Streamer log entries', 'body' => 'One per show at every stage: approved & locked, awaiting admin, and streamer-to-do — so you can test review, approval, and send-back.'],
            ['icon' => 'heroicon-o-cube', 'title' => 'Inventory, stock & movements', 'body' => '10 products across locations with stock levels, transfer/return history, and reorder levels feeding Product Insights.'],
            ['icon' => 'heroicon-o-qr-code', 'title' => 'A scannable pallet', 'body' => 'Pallet PO-2026-003 is mid-receipt with cases carrying real barcodes (VX-CASE-0001 … VX-CASE-0004) you can scan on the Receive page.'],
            ['icon' => 'heroicon-o-banknotes', 'title' => 'Payouts, batches & a loan', 'body' => 'Weekly pay batches (paid + draft) with per-streamer payouts, and an active loan whose repayments deduct from payouts.'],
            ['icon' => 'heroicon-o-chat-bubble-left-right', 'title' => 'Feedback tickets', 'body' => 'Open and in-progress tickets to try the feedback triage flow.'],
        ];

        $logins = [
            ['Owner / Super Admin', 'dev@vortexbreaks.com', 'devpassword'],
            ['Streamer — Jordan', 'jordan@vortexbreaks.com', 'demopassword'],
            ['Streamer — Taylor', 'taylor@vortexbreaks.com', 'demopassword'],
            ['Streamer — Alex', 'alex@vortexbreaks.com', 'demopassword'],
        ];
    @endphp

    <div class="space-y-8">
        {{-- What gets loaded --}}
        <section>
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">What gets loaded</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($includes as $i)
                    <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-4">
                        <div class="flex items-center gap-2 mb-1.5">
                            <x-dynamic-component :component="$i['icon']" class="h-5 w-5 text-primary-500" />
                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $i['title'] }}</p>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ $i['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Demo logins --}}
        <section>
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Demo logins</h2>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">Log in as a streamer to see how the app scopes to their own shows, payouts, and log entries.</p>
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-white/5 text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Role</th>
                            <th class="px-4 py-2 text-left font-medium">Email</th>
                            <th class="px-4 py-2 text-left font-medium">Password</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($logins as [$role, $email, $pass])
                            <tr>
                                <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $role }}</td>
                                <td class="px-4 py-2 font-mono text-gray-600 dark:text-gray-300">{{ $email }}</td>
                                <td class="px-4 py-2 font-mono text-gray-600 dark:text-gray-300">{{ $pass }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <p class="text-xs text-gray-400">
            The seeder only adds records that don't already exist, so pressing the button again is safe — it won't duplicate data.
        </p>

        {{-- What "Clear demo / test data" removes --}}
        <section>
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Clear demo / test data</h2>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                Use this while testing show scraping/imports to wipe operational records back to a clean slate without losing your logins or channel setup.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="rounded-xl border border-red-200 dark:border-red-900/40 bg-red-50/50 dark:bg-red-900/10 p-4">
                    <p class="font-semibold text-red-800 dark:text-red-400 mb-1.5">Deleted</p>
                    <p class="text-sm text-red-700 dark:text-red-300 leading-relaxed">
                        Shows, Whatnot orders, streamer log entries, deduction requests, payouts &amp; batches, inventory (stock, lots, cases, movements, locations), pallets &amp; receiving sessions, ledger entries, time entries, streamer loans, and AI logs/tasks.
                    </p>
                </div>
                <div class="rounded-xl border border-green-200 dark:border-green-900/40 bg-green-50/50 dark:bg-green-900/10 p-4">
                    <p class="font-semibold text-green-800 dark:text-green-400 mb-1.5">Kept</p>
                    <p class="text-sm text-green-700 dark:text-green-300 leading-relaxed">
                        Users, streamers, vendors, Whatnot channels, and all settings.
                    </p>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
