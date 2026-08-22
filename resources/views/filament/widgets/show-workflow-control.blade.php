@php($counts = $this->counts)

<x-filament-widgets::widget>
    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Show Workflow</div>
                <h2 class="mt-1 text-base font-semibold text-gray-950 dark:text-white sm:text-lg">Post-show automation</h2>
                <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Choose when inventory posts and which reports actually need admin review.</p>
            </div>

            <div class="grid grid-cols-4 gap-1 overflow-hidden rounded-lg bg-gray-50 p-1 dark:bg-gray-800 sm:gap-2 sm:bg-transparent sm:p-0">
                @foreach ([
                    ['Reports', $counts['pending_reports']],
                    ['Unmatched', $counts['unmatched_lines']],
                    ['Shipments', $counts['open_shipments']],
                    ['Unassigned', $counts['unassigned_fulfillment']],
                ] as [$label, $value])
                    <div class="min-w-0 rounded-md px-1.5 py-2 text-center sm:min-w-24 sm:rounded-xl sm:bg-gray-50 sm:px-3 dark:sm:bg-gray-800">
                        <div class="text-base font-semibold leading-none text-gray-950 dark:text-white sm:text-lg">{{ number_format($value) }}</div>
                        <div class="mt-1 truncate text-[9px] font-medium uppercase tracking-wide text-gray-400 sm:text-[10px] sm:normal-case sm:tracking-normal">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700 sm:p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-gray-950 dark:text-white">Inventory posting</div>
                        <p class="mt-0.5 text-[11px] leading-4 text-gray-500 dark:text-gray-400">When Sold / Giveaway / Promo lines affect stock.</p>
                    </div>
                    <span class="rounded-full bg-primary-50 px-2 py-1 text-[10px] font-semibold text-primary-700 dark:bg-primary-950/30 dark:text-primary-300">{{ match($postingPolicy) { 'clean_only' => 'Clean only', 'on_approval' => 'On approval', default => 'On submit' } }}</span>
                </div>

                <div class="mt-3 grid gap-1.5">
                    @foreach ([
                        'on_submit' => ['On Submit', 'Post immediately after the streamer submits.'],
                        'clean_only' => ['Clean Reports Only', 'Post automatically only when inventory has no exceptions.'],
                        'on_approval' => ['On Admin Approval', 'Wait until an admin approves the report.'],
                    ] as $value => [$label, $description])
                        <button type="button" wire:click="setPostingPolicy('{{ $value }}')"
                            class="flex min-h-12 items-center gap-3 rounded-lg border px-3 py-2 text-left transition {{ $postingPolicy === $value ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/30' : 'border-gray-200 hover:border-gray-300 dark:border-gray-700' }}">
                            <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border {{ $postingPolicy === $value ? 'border-primary-600' : 'border-gray-300 dark:border-gray-600' }}">
                                @if($postingPolicy === $value)<span class="h-2 w-2 rounded-full bg-primary-600"></span>@endif
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs font-semibold text-gray-900 dark:text-white sm:text-sm">{{ $label }}</span>
                                <span class="mt-0.5 block text-[10px] leading-4 text-gray-500 dark:text-gray-400 sm:text-[11px]">{{ $description }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700 sm:p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-gray-950 dark:text-white">Admin review</div>
                        <p class="mt-0.5 text-[11px] leading-4 text-gray-500 dark:text-gray-400">Which reports should stop in the review queue.</p>
                    </div>
                    <span class="rounded-full bg-primary-50 px-2 py-1 text-[10px] font-semibold text-primary-700 dark:bg-primary-950/30 dark:text-primary-300">{{ match($reviewPolicy) { 'exceptions_only' => 'Exceptions', 'auto' => 'Automatic', default => 'Every report' } }}</span>
                </div>

                <div class="mt-3 grid gap-1.5">
                    @foreach ([
                        'required' => ['Every Report', 'An admin approves every submitted report.'],
                        'exceptions_only' => ['Exceptions Only', 'Clean reports pass automatically; problems wait for review.'],
                        'auto' => ['Automatic', 'Reports approve automatically after submission.'],
                    ] as $value => [$label, $description])
                        <button type="button" wire:click="setReviewPolicy('{{ $value }}')"
                            class="flex min-h-12 items-center gap-3 rounded-lg border px-3 py-2 text-left transition {{ $reviewPolicy === $value ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/30' : 'border-gray-200 hover:border-gray-300 dark:border-gray-700' }}">
                            <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border {{ $reviewPolicy === $value ? 'border-primary-600' : 'border-gray-300 dark:border-gray-600' }}">
                                @if($reviewPolicy === $value)<span class="h-2 w-2 rounded-full bg-primary-600"></span>@endif
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs font-semibold text-gray-900 dark:text-white sm:text-sm">{{ $label }}</span>
                                <span class="mt-0.5 block text-[10px] leading-4 text-gray-500 dark:text-gray-400 sm:text-[11px]">{{ $description }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</x-filament-widgets::widget>
