<x-filament-panels::page>
    <div class="space-y-8">
        {{-- Workflow Pipeline --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-8">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-8">Stream to Payout Pipeline</h2>

            <div class="relative">
                {{-- Timeline Line --}}
                <div class="hidden lg:block absolute top-1/2 left-0 right-0 h-1 bg-gradient-to-r from-blue-400 via-purple-400 to-green-400 -translate-y-1/2"></div>

                {{-- Steps Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                    {{-- Step 1: Stream Ends --}}
                    <div class="relative">
                        <div class="flex flex-col items-center">
                            <div class="relative z-10 flex items-center justify-center w-16 h-16 rounded-full border-4 border-white dark:border-gray-900 bg-blue-500 text-white shadow-lg mb-4">
                                <x-heroicon-o-camera class="h-8 w-8" />
                            </div>
                            <div class="text-center">
                                <h3 class="font-bold text-gray-900 dark:text-gray-100 text-sm">Stream Ends</h3>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">Show is completed</p>
                            </div>
                        </div>
                    </div>

                    {{-- Arrow --}}
                    <div class="hidden lg:flex items-center justify-center">
                        <x-heroicon-o-arrow-right class="h-6 w-6 text-gray-400 dark:text-gray-600" />
                    </div>

                    {{-- Step 2: End of Stream --}}
                    <div class="relative">
                        <div class="flex flex-col items-center">
                            <div class="relative z-10 flex items-center justify-center w-16 h-16 rounded-full border-4 border-white dark:border-gray-900 bg-purple-500 text-white shadow-lg mb-4">
                                <x-heroicon-o-check-circle class="h-8 w-8" />
                            </div>
                            <div class="text-center">
                                <h3 class="font-bold text-gray-900 dark:text-gray-100 text-sm">Log Items</h3>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
                                    <a href="{{ \App\Filament\Pages\EndOfStreamForm::getUrl() }}" class="text-purple-600 dark:text-purple-400 hover:underline">
                                        End of Stream Form
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Arrow --}}
                    <div class="hidden lg:flex items-center justify-center">
                        <x-heroicon-o-arrow-right class="h-6 w-6 text-gray-400 dark:text-gray-600" />
                    </div>

                    {{-- Step 3: Admin Review --}}
                    <div class="relative">
                        <div class="flex flex-col items-center">
                            <div class="relative z-10 flex items-center justify-center w-16 h-16 rounded-full border-4 border-white dark:border-gray-900 bg-indigo-500 text-white shadow-lg mb-4">
                                <x-heroicon-o-document-check class="h-8 w-8" />
                            </div>
                            <div class="text-center">
                                <h3 class="font-bold text-gray-900 dark:text-gray-100 text-sm">Review</h3>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
                                    <a href="{{ route('filament.admin.resources.streamer-logs.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        Streamer Log
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Arrow --}}
                    <div class="hidden lg:flex items-center justify-center">
                        <x-heroicon-o-arrow-right class="h-6 w-6 text-gray-400 dark:text-gray-600" />
                    </div>

                    {{-- Step 4: Calculate Payout --}}
                    <div class="relative">
                        <div class="flex flex-col items-center">
                            <div class="relative z-10 flex items-center justify-center w-16 h-16 rounded-full border-4 border-white dark:border-gray-900 bg-green-500 text-white shadow-lg mb-4">
                                <x-heroicon-o-currency-dollar class="h-8 w-8" />
                            </div>
                            <div class="text-center">
                                <h3 class="font-bold text-gray-900 dark:text-gray-100 text-sm">Payout</h3>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">Approve & pay out</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Mobile View Info --}}
            <div class="lg:hidden mt-8 space-y-4 border-t border-gray-200 dark:border-gray-700 pt-8">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <strong>Pipeline Flow:</strong>
                    <ol class="list-decimal list-inside space-y-2 mt-2">
                        <li>Stream ends</li>
                        <li>Streamer fills out End of Stream form</li>
                        <li>Admin reviews in Streamer Log</li>
                        <li>Admin approves and calculates payout</li>
                    </ol>
                </div>
            </div>
        </div>

        {{-- Status Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-6">
                <div class="text-sm font-semibold text-blue-900 dark:text-blue-100 uppercase tracking-wide">
                    Need End of Stream
                </div>
                <div class="mt-2 text-3xl font-bold text-blue-600 dark:text-blue-400">
                    {{ $this->workflowStats['pending_end_of_stream'] }}
                </div>
                <p class="text-xs text-blue-700 dark:text-blue-300 mt-2">
                    Shows awaiting quick log
                </p>
            </div>

            <div class="rounded-lg border border-purple-200 dark:border-purple-800 bg-purple-50 dark:bg-purple-900/20 p-6">
                <div class="text-sm font-semibold text-purple-900 dark:text-purple-100 uppercase tracking-wide">
                    Pending Admin Review
                </div>
                <div class="mt-2 text-3xl font-bold text-purple-600 dark:text-purple-400">
                    {{ $this->workflowStats['pending_admin_review'] }}
                </div>
                <p class="text-xs text-purple-700 dark:text-purple-300 mt-2">
                    Awaiting approval
                </p>
            </div>

            <div class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/20 p-6">
                <div class="text-sm font-semibold text-indigo-900 dark:text-indigo-100 uppercase tracking-wide">
                    Awaiting Streamer
                </div>
                <div class="mt-2 text-3xl font-bold text-indigo-600 dark:text-indigo-400">
                    {{ $this->workflowStats['awaiting_streamer'] }}
                </div>
                <p class="text-xs text-indigo-700 dark:text-indigo-300 mt-2">
                    Sent back for edits
                </p>
            </div>

            <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-6">
                <div class="text-sm font-semibold text-green-900 dark:text-green-100 uppercase tracking-wide">
                    Approved
                </div>
                <div class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">
                    {{ $this->workflowStats['approved'] }}
                </div>
                <p class="text-xs text-green-700 dark:text-green-300 mt-2">
                    Ready for payout
                </p>
            </div>
        </div>

        {{-- Quick Action Buttons --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <a href="{{ \App\Filament\Pages\EndOfStreamForm::getUrl() }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-3 text-sm font-semibold text-white hover:bg-purple-500 transition-colors">
                    <x-heroicon-o-camera class="h-5 w-5" />
                    End of Stream
                </a>
                <a href="{{ route('filament.admin.resources.streamer-logs.index') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-500 transition-colors">
                    <x-heroicon-o-document-check class="h-5 w-5" />
                    Review Logs
                </a>
                <a href="{{ route('filament.admin.resources.shows.index') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-500 transition-colors">
                    <x-heroicon-o-tv class="h-5 w-5" />
                    View Shows
                </a>
                <a href="{{ route('filament.admin.resources.payouts.index') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-3 text-sm font-semibold text-white hover:bg-green-500 transition-colors">
                    <x-heroicon-o-currency-dollar class="h-5 w-5" />
                    Payouts
                </a>
            </div>
        </div>
    </div>
</x-filament-panels::page>
