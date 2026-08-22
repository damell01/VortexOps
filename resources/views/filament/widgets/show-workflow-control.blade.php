@php($counts = $this->counts)

<x-filament-widgets::widget>
    <div class="space-y-4">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-600">Show Workflow Engine</div>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">How post-show reports move through VortexOps</h2>
                    <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">These controls affect the existing End of Stream report, Show detail, inventory movements, and admin review workflow.</p>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach ([
                        ['Reports', $counts['pending_reports']],
                        ['Unmatched', $counts['unmatched_lines']],
                        ['Open Shipments', $counts['open_shipments']],
                        ['Unassigned', $counts['unassigned_fulfillment']],
                    ] as [$label, $value])
                        <div class="min-w-28 rounded-xl bg-gray-50 px-3 py-2 text-center dark:bg-gray-800">
                            <div class="text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($value) }}</div>
                            <div class="text-[11px] text-gray-500">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="font-medium text-gray-950 dark:text-white">When inventory should post</div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Controls when Sold / Giveaway / Promo lines become actual inventory movements.</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                        @foreach ([
                            'on_submit' => ['On Submit', 'Fastest. Post when streamer submits.'],
                            'clean_only' => ['Clean Only', 'Auto-post only reports with no inventory exceptions.'],
                            'on_approval' => ['On Approval', 'Nothing posts until admin approval.'],
                        ] as $value => [$label, $description])
                            <button type="button" wire:click="setPostingPolicy('{{ $value }}')"
                                class="rounded-xl border p-3 text-left transition {{ $postingPolicy === $value ? 'border-primary-500 bg-primary-50 ring-1 ring-primary-500 dark:bg-primary-950/30' : 'border-gray-200 hover:border-gray-300 dark:border-gray-700' }}">
                                <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $label }}</div>
                                <div class="mt-1 text-[11px] leading-4 text-gray-500 dark:text-gray-400">{{ $description }}</div>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="font-medium text-gray-950 dark:text-white">When admins should review</div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Lets you choose between strict review and a mostly automatic clean-report flow.</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                        @foreach ([
                            'required' => ['Every Report', 'Admin approval required for every submitted report.'],
                            'exceptions_only' => ['Exceptions Only', 'Clean reports auto-approve; problems stay in review.'],
                            'auto' => ['Automatic', 'Reports auto-approve after submission.'],
                        ] as $value => [$label, $description])
                            <button type="button" wire:click="setReviewPolicy('{{ $value }}')"
                                class="rounded-xl border p-3 text-left transition {{ $reviewPolicy === $value ? 'border-primary-500 bg-primary-50 ring-1 ring-primary-500 dark:bg-primary-950/30' : 'border-gray-200 hover:border-gray-300 dark:border-gray-700' }}">
                                <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $label }}</div>
                                <div class="mt-1 text-[11px] leading-4 text-gray-500 dark:text-gray-400">{{ $description }}</div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-filament-widgets::widget>
