@php
    use App\Models\Setting;
    use App\Support\AdminModules;
    $demoMode   = (bool) Setting::get('demo_mode', false);
    $user       = auth()->user();
    $isAdmin    = $user?->isAdmin() || $user?->isOwner();
    $isAuthPage = request()->routeIs('filament.admin.auth.*')
               || str_contains(request()->path(), 'admin/login')
               || str_contains(request()->path(), 'admin/password-reset')
               || str_contains(request()->path(), 'admin/register');
@endphp

@if ($demoMode && ! $user && ! $isAuthPage)
    {{-- Non-admin sees a full-page showcase overlay --}}
    <div
        class="fixed inset-0 z-[9999] flex flex-col items-center justify-start overflow-y-auto bg-white dark:bg-gray-950"
        style="display: flex;"
    >
        {{-- Header --}}
        <div class="w-full border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-6 py-4 flex items-center gap-3">
            <div class="flex items-center gap-2">
                <div class="h-8 w-8 rounded-lg bg-violet-600 flex items-center justify-center">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <span class="text-base font-bold text-gray-900 dark:text-gray-100">
                    {{ \App\Models\Setting::get('brand_name', 'VortexOps') }}
                </span>
            </div>
        </div>

        {{-- Hero --}}
        <div class="w-full max-w-3xl mx-auto px-6 pt-16 pb-10 text-center">
            <div class="inline-flex items-center justify-center h-16 w-16 rounded-2xl bg-violet-600 mb-6 shadow-lg">
                <svg class="h-9 w-9 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 tracking-tight">
                Welcome to {{ \App\Models\Setting::get('brand_name', 'VortexOps') }}
            </h1>
            <p class="mt-4 text-base text-gray-500 dark:text-gray-400 max-w-xl mx-auto">
                Your all-in-one operations platform for Whatnot sports card breaks — from live show management to payouts, inventory, and analytics.
            </p>
            <div class="mt-6 inline-flex items-center gap-2 rounded-full bg-violet-50 dark:bg-violet-900/30 border border-violet-200 dark:border-violet-800 px-4 py-2 text-sm font-medium text-violet-700 dark:text-violet-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Full access coming soon
            </div>
        </div>

        {{-- Module grid --}}
        <div class="w-full max-w-4xl mx-auto px-6 pb-16">
            <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-5">Platform Modules</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @php
                    $moduleCards = [
                        ['icon' => 'M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z',
                         'label' => 'Live Shows', 'desc' => 'Manage Whatnot shows, show status, and live reconciliation.', 'color' => 'violet'],
                        ['icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                         'label' => 'Payouts', 'desc' => 'Calculate and track streamer payouts with multiple pay types.', 'color' => 'emerald'],
                        ['icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                         'label' => 'Inventory', 'desc' => 'Pallets, locations, barcode receiving, and stock tracking.', 'color' => 'blue'],
                        ['icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                         'label' => 'Analytics', 'desc' => 'SPH, AOV, business net, and streamer performance metrics.', 'color' => 'indigo'],
                        ['icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                         'label' => 'Reports', 'desc' => 'Ledger, statements, profit share packets, and CSV exports.', 'color' => 'amber'],
                        ['icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
                         'label' => 'Streamers', 'desc' => 'Profiles, pay type config, loans, and streamer logs.', 'color' => 'pink'],
                    ];
                @endphp

                @foreach($moduleCards as $card)
                    @php
                        $colors = [
                            'violet' => 'bg-violet-50 dark:bg-violet-900/20 border-violet-200 dark:border-violet-800',
                            'emerald'=> 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800',
                            'blue'   => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800',
                            'indigo' => 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 dark:border-indigo-800',
                            'amber'  => 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800',
                            'pink'   => 'bg-pink-50 dark:bg-pink-900/20 border-pink-200 dark:border-pink-800',
                        ];
                        $iconColors = [
                            'violet' => 'bg-violet-100 dark:bg-violet-900/50 text-violet-600 dark:text-violet-400',
                            'emerald'=> 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400',
                            'blue'   => 'bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400',
                            'indigo' => 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400',
                            'amber'  => 'bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400',
                            'pink'   => 'bg-pink-100 dark:bg-pink-900/50 text-pink-600 dark:text-pink-400',
                        ];
                        $cardColor = $colors[$card['color']] ?? $colors['violet'];
                        $iconColor = $iconColors[$card['color']] ?? $iconColors['violet'];
                    @endphp
                    <div class="relative rounded-xl border {{ $cardColor }} p-5 overflow-hidden">
                        {{-- Blurred content --}}
                        <div class="filter blur-[2px] select-none pointer-events-none">
                            <div class="flex items-start gap-3 mb-3">
                                <div class="rounded-lg {{ $iconColor }} p-2 flex-shrink-0">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $card['label'] }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $card['desc'] }}</p>
                                </div>
                            </div>
                            {{-- Fake stat bars --}}
                            <div class="space-y-2 mt-3">
                                <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full w-full"></div>
                                <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full w-3/4"></div>
                                <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full w-1/2"></div>
                            </div>
                        </div>
                        {{-- Coming Soon overlay --}}
                        <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-white/40 dark:bg-gray-950/40 backdrop-blur-[1px]">
                            <div class="rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-2 shadow-sm">
                                <svg class="h-5 w-5 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-900 rounded-full px-3 py-1 border border-gray-200 dark:border-gray-700 shadow-sm">Coming Soon</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="text-center text-xs text-gray-400 dark:text-gray-600 mt-8">
                Contact us to get full access to your VortexOps workspace.
            </p>
        </div>
    </div>
@endif
