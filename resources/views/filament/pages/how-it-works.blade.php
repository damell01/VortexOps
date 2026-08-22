<x-filament-panels::page>
    @php
        $steps = [
            [
                'icon' => 'heroicon-o-arrow-down-tray', 'tone' => 'sky',
                'title' => '1. Whatnot syncs the show',
                'body' => 'Shows, analytics, sold-item reference data, and shipment information are imported from Whatnot. Streamers do not need to re-enter those totals.',
            ],
            [
                'icon' => 'heroicon-o-user-circle', 'tone' => 'violet',
                'title' => '2. Streamers manage their own inventory',
                'body' => 'A streamer can move inventory into their streamer inventory whenever they take possession of it. That stock is not assigned to a specific show ahead of time and may remain with the streamer across multiple shows.',
            ],
            [
                'icon' => 'heroicon-o-clipboard-document-list', 'tone' => 'amber',
                'title' => '3. Streamer completes End of Stream',
                'body' => 'After the show, the streamer records inventory actually used for that show and marks each line as <strong>Sold</strong>, <strong>Giveaway</strong>, <strong>Promo / Bonus</strong>, or <strong>Other</strong>. Unlisted items are allowed and stay flagged for admin.',
            ],
            [
                'icon' => 'heroicon-o-check-badge', 'tone' => 'emerald',
                'title' => '4. The workflow reconciles the report',
                'body' => 'Depending on the configured policy, inventory can post on submission, only when the report is clean, or after admin approval. Clean reports can also auto-approve if the admin chooses an exceptions-only workflow.',
            ],
            [
                'icon' => 'heroicon-o-truck', 'tone' => 'sky',
                'title' => '5. Fulfillment works the show',
                'body' => 'Fulfillment works show-first: open a show, process its shipment and packing lines, and keep shipment status/tracking current. Regular fulfillment users only see their assigned shows.',
            ],
            [
                'icon' => 'heroicon-o-banknotes', 'tone' => 'emerald',
                'title' => '6. Finalize payout and reporting',
                'body' => 'Once the report and any fulfillment-dependent counts are settled, payout calculation and financial reporting use the show’s final data.',
            ],
        ];

        $tone = fn (string $c) => [
            'sky' => ['bg' => 'bg-sky-100 dark:bg-sky-500/15', 'text' => 'text-sky-600 dark:text-sky-400'],
            'amber' => ['bg' => 'bg-amber-100 dark:bg-amber-500/15', 'text' => 'text-amber-600 dark:text-amber-400'],
            'emerald' => ['bg' => 'bg-emerald-100 dark:bg-emerald-500/15', 'text' => 'text-emerald-600 dark:text-emerald-400'],
            'violet' => ['bg' => 'bg-violet-100 dark:bg-violet-500/15', 'text' => 'text-violet-600 dark:text-violet-400'],
        ][$c] ?? ['bg' => 'bg-gray-100 dark:bg-white/10', 'text' => 'text-gray-500'];
    @endphp

    <div class="mx-auto max-w-5xl space-y-4 sm:space-y-7">
        <section class="rounded-xl border border-primary-200 bg-primary-50/60 p-4 dark:border-primary-500/20 dark:bg-primary-500/5 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Your role</div>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white sm:text-lg">{{ $this->myRoleGuide['label'] }}</h2>
                        <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-medium text-primary-600 shadow-sm dark:bg-gray-900 sm:text-xs">Role-specific guide</span>
                    </div>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">These are the actions you should focus on. Other roles may see a different workflow.</p>
                </div>
            </div>

            <div class="mt-4 divide-y divide-primary-100 overflow-hidden rounded-xl border border-primary-100 bg-white dark:divide-primary-500/10 dark:border-primary-500/10 dark:bg-gray-900">
                @foreach ($this->myRoleGuide['items'] as $item)
                    <div class="flex gap-3 p-3 sm:p-4">
                        <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-primary-100 dark:bg-primary-500/15 sm:h-8 sm:w-8">
                            <x-heroicon-o-check class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $item['title'] }}</p>
                            <p class="mt-0.5 text-xs leading-5 text-gray-600 dark:text-gray-300 sm:text-sm">{!! $item['body'] !!}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section>
            <div class="mb-3 flex items-end justify-between gap-3 sm:mb-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">The show flow</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">The same lifecycle from import through payout.</p>
                </div>
                <span class="text-[10px] font-medium text-gray-400 sm:text-xs">6 steps</span>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                @foreach ($steps as $s)
                    @php $t = $tone($s['tone']); @endphp
                    <div class="flex gap-3 border-b border-gray-100 p-3 last:border-b-0 dark:border-gray-800 sm:gap-4 sm:p-4">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $t['bg'] }} sm:h-9 sm:w-9">
                            <x-dynamic-component :component="$s['icon']" class="h-4 w-4 {{ $t['text'] }} sm:h-5 sm:w-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $s['title'] }}</p>
                            <p class="mt-0.5 text-xs leading-5 text-gray-600 dark:text-gray-300 sm:text-sm">{!! $s['body'] !!}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-5">
            <div class="flex items-start gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-950 dark:text-violet-300">
                    <x-heroicon-o-cursor-arrow-rays class="h-4 w-4" />
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Use the page tours too</h2>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Operational pages have a small <strong>Tour</strong> button in the lower-right corner. The tour opens automatically the first time you visit a supported workflow page, then stays out of the way. Tap Tour anytime to replay it.</p>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
