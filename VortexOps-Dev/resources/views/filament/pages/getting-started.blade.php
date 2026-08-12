<x-filament-panels::page>
    <div class="space-y-8">
        <!-- Header -->
        <div class="rounded-lg bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-800 dark:to-primary-900 px-6 py-8 text-white">
            <h2 class="text-3xl font-bold mb-2">Welcome to VortexOps</h2>
            <p class="text-primary-100 text-lg">Your operations hub for managing shows, inventory, and payouts</p>
        </div>

        <!-- Quick Navigation -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <a href="/admin/end-of-stream" class="block p-4 rounded-lg border-2 border-primary-200 dark:border-primary-800 hover:border-primary-400 dark:hover:border-primary-700 hover:shadow-md transition-all">
                <div class="flex items-start gap-3">
                    <div class="text-2xl">📋</div>
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white">End of Stream</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Log items sold after your show</p>
                    </div>
                </div>
            </a>
            <a href="/admin/resources/streamer-logs" class="block p-4 rounded-lg border-2 border-blue-200 dark:border-blue-800 hover:border-blue-400 dark:hover:border-blue-700 hover:shadow-md transition-all">
                <div class="flex items-start gap-3">
                    <div class="text-2xl">👀</div>
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white">Streamer Log</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Review submitted show data</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Complete Workflow for Streamers -->
        <div class="space-y-4">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">📺 Streamer Workflow</h3>
            <div class="space-y-3">
                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center font-bold text-primary-700 dark:text-primary-300">1</div>
                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-white">Stream & Record Sales</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Conduct your show and keep track of what you sell</p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center font-bold text-primary-700 dark:text-primary-300">2</div>
                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-white">Go to "End of Stream" Form</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">After your show ends, navigate to the End of Stream form from your navigation menu or <a href="/admin/end-of-stream" class="text-primary-600 dark:text-primary-400 hover:underline">click here</a></p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center font-bold text-primary-700 dark:text-primary-300">3</div>
                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-white">Select Your Show</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Choose the show you just completed from the list</p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center font-bold text-primary-700 dark:text-primary-300">4</div>
                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-white">Select Items Sold</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Search and click on each item you sold. Enter the quantity for each item. Use the search bar to find items by name, SKU, or brand</p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center font-bold text-primary-700 dark:text-primary-300">5</div>
                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-white">Submit & Review</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Click Save to submit. You'll be taken to your Streamer Log where you can review everything before admin approval</p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900 flex items-center justify-center font-bold text-amber-700 dark:text-amber-300">6</div>
                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-white">Wait for Admin Review</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">An admin will review your submission and approve it. Once approved, your show is ready for payout processing</p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900 flex items-center justify-center font-bold text-emerald-700 dark:text-emerald-300">✓</div>
                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-white">Payout Processed</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Your payout will be calculated and processed according to your payout settings</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Complete Workflow for Admins -->
        @if(auth()->user()?->isAdmin())
        <div class="space-y-4">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">👨‍💼 Admin Workflow</h3>
            <div class="space-y-3">
                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center font-bold text-blue-700 dark:text-blue-300">1</div>
                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-white">Monitor Pending Submissions</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Check your dashboard for shows awaiting admin review. Navigate to Streamer Logs resource to see all pending entries</p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center font-bold text-blue-700 dark:text-blue-300">2</div>
                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-white">Review Streamer Log</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Open the log entry to see the show details and items sold. Verify the streamer's submission is accurate</p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center font-bold text-blue-700 dark:text-blue-300">3</div>
                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-white">Fill in Missing Data</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Enter any additional information like gross revenue, costs, etc. that the streamer didn't provide</p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center font-bold text-blue-700 dark:text-blue-300">4</div>
                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-white">Approve the Entry</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Click the "Approve" button to move the entry to the next stage. This triggers payout calculation</p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center font-bold text-blue-700 dark:text-blue-300">5</div>
                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-white">Review Payout</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Once the log is approved, the payout is generated. Review it in the Payouts section to verify calculations</p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center font-bold text-blue-700 dark:text-blue-300">6</div>
                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-white">(Optional) Distribute Profit Share</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">If using profit share, go to Profit Share Management to split earnings between streamer and manager</p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center font-bold text-blue-700 dark:text-blue-300">7</div>
                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-white">Process Payout</h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Mark the payout as complete after funds are transferred</p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Key Concepts -->
        <div class="space-y-4">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">💡 Key Concepts</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                    <h4 class="font-bold text-gray-900 dark:text-white mb-2">Streamer Log Entry</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">A record of a show that includes the show details and items sold. Goes through an approval workflow before payout</p>
                </div>

                <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                    <h4 class="font-bold text-gray-900 dark:text-white mb-2">Status Badges</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">🔄 Pending = awaiting admin review | 👀 Awaiting = waiting for action | ✓ Approved = ready for payout</p>
                </div>

                <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                    <h4 class="font-bold text-gray-900 dark:text-white mb-2">Edit Permission</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Streamers can request edit permission if they need to change something after submission. Admins approve/reject the request</p>
                </div>

                <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                    <h4 class="font-bold text-gray-900 dark:text-white mb-2">Profit Share</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Optional: Split payout between streamer and manager. Admins set the percentage after show is approved</p>
                </div>
            </div>
        </div>

        <!-- Troubleshooting -->
        <div class="space-y-4">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">🔧 Troubleshooting</h3>
            <div class="space-y-3">
                <details class="group border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <summary class="flex cursor-pointer items-center justify-between font-bold text-gray-900 dark:text-white">
                        I can't find the End of Stream form
                        <span class="transition group-open:rotate-180">▼</span>
                    </summary>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">The End of Stream form is only available to streamers. If you're logged in as a streamer, look in the "Streams" section of your navigation menu. You can also access it directly at /admin/end-of-stream</p>
                </details>

                <details class="group border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <summary class="flex cursor-pointer items-center justify-between font-bold text-gray-900 dark:text-white">
                        My show is locked for editing
                        <span class="transition group-open:rotate-180">▼</span>
                    </summary>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Once a streamer logs a show, it becomes read-only to prevent accidental changes. If you need to edit it, click the "Request Edit Permission" button and an admin will review your request</p>
                </details>

                <details class="group border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <summary class="flex cursor-pointer items-center justify-between font-bold text-gray-900 dark:text-white">
                        Items aren't showing in the form
                        <span class="transition group-open:rotate-180">▼</span>
                    </summary>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Make sure the items are marked as active in your inventory. Items must have stock available to appear in the End of Stream form. You can search by name, SKU, or brand</p>
                </details>

                <details class="group border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <summary class="flex cursor-pointer items-center justify-between font-bold text-gray-900 dark:text-white">
                        Payout isn't being calculated
                        <span class="transition group-open:rotate-180">▼</span>
                    </summary>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Make sure the log entry is approved by an admin. Payouts are only generated for approved entries. Check that the streamer has a payout method set up</p>
                </details>
            </div>
        </div>

        <!-- Support -->
        <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-6">
            <h3 class="font-bold text-blue-900 dark:text-blue-300 mb-2">Need Help?</h3>
            <p class="text-sm text-blue-800 dark:text-blue-400">If you have questions about the workflow or run into issues, contact your administrator. They can help guide you through the process or make adjustments to your settings</p>
        </div>
    </div>
</x-filament-panels::page>
