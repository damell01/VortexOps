<x-filament-panels::page>
    <div class="space-y-4">

        {{-- Refresh button --}}
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-400">Live view of shows moving through the ops pipeline.</p>
            <button
                wire:click="$refresh"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
            >
                <x-heroicon-o-arrow-path class="h-3.5 w-3.5" wire:loading.class="animate-spin" />
                Refresh
            </button>
        </div>

        {{-- Board columns --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 items-start">
            @foreach ($this->getColumns() as $column)
                @php $count = $column['shows']->count(); @endphp

                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 overflow-hidden flex flex-col h-[65vh] min-h-[420px] max-h-[720px]">

                    {{-- Column header — stays put; only the cards below scroll --}}
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between flex-shrink-0"
                         style="border-top: 3px solid {{ $column['color'] }}">
                        <div class="flex items-center gap-2">
                            <span class="text-sm">{{ $column['icon'] }}</span>
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $column['label'] }}</span>
                        </div>
                        @if ($count > 0)
                            <span class="inline-flex items-center justify-center h-5 w-5 rounded-full text-[11px] font-bold text-white"
                                  style="background:{{ $column['color'] }}">{{ $count }}</span>
                        @endif
                    </div>

                    {{-- Cards — this scrolls independently, page height never changes --}}
                    <div class="flex flex-col gap-2 p-2 min-h-[80px] flex-1 overflow-y-auto">
                        @forelse ($column['shows'] as $show)
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-3 space-y-2 hover:shadow-sm transition-shadow">

                                {{-- Title + date --}}
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ $this->getShowUrl($show) }}"
                                       class="text-sm font-semibold text-gray-900 dark:text-gray-100 hover:text-primary-600 dark:hover:text-primary-400 leading-snug line-clamp-2">
                                        {{ $show->title ?? 'Show #' . $show->id }}
                                    </a>
                                </div>

                                {{-- Date + revenue row --}}
                                <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                    @if ($show->show_date)
                                        <span>{{ $show->show_date->format('M j, Y') }}</span>
                                    @endif
                                    @if ($show->gross_revenue)
                                        <span class="font-semibold text-gray-700 dark:text-gray-300">${{ number_format((float)$show->gross_revenue, 0) }}</span>
                                    @endif
                                </div>

                                {{-- Time-in-status (aging) — spots stuck shows in the pipeline --}}
                                @php
                                    $age = $show->days_in_status;
                                    [$ageTone, $ageBg] = match (true) {
                                        is_null($age)  => ['text-gray-400', 'bg-gray-100 dark:bg-white/5'],
                                        $age >= 7      => ['text-rose-700 dark:text-rose-300', 'bg-rose-100 dark:bg-rose-500/15'],
                                        $age >= 3      => ['text-amber-700 dark:text-amber-300', 'bg-amber-100 dark:bg-amber-500/15'],
                                        default        => ['text-gray-500 dark:text-gray-400', 'bg-gray-100 dark:bg-white/5'],
                                    };
                                    $ageLabel = is_null($age)
                                        ? 'age unknown'
                                        : ($age === 0 ? 'today' : $age . 'd in status');
                                @endphp
                                <div>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $ageTone }} {{ $ageBg }}"
                                          @if($show->entered_status_at) title="Since {{ $show->entered_status_at->format('M j, Y g:i A') }}" @endif>
                                        @if(! is_null($age) && $age >= 7)
                                            <x-heroicon-s-exclamation-triangle class="h-3 w-3" />
                                        @else
                                            <x-heroicon-o-clock class="h-3 w-3" />
                                        @endif
                                        {{ $ageLabel }}
                                    </span>
                                </div>

                                {{-- Streamers --}}
                                @if ($show->streamers->isNotEmpty())
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($show->streamers as $streamer)
                                            <span class="inline-flex items-center rounded-full bg-sky-50 dark:bg-sky-900/30 border border-sky-200 dark:border-sky-800 px-2 py-0.5 text-[11px] font-medium text-sky-700 dark:text-sky-400">
                                                {{ $streamer->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Action link --}}
                                <div class="flex items-center gap-2 pt-0.5">
                                    @if ($column['status'] === 'pending_approval')
                                        <a href="{{ $this->getApprovalUrl($show) }}"
                                           class="inline-flex items-center gap-1 text-[11px] font-medium text-sky-600 dark:text-sky-400 hover:underline">
                                            <x-heroicon-o-clipboard-document-check class="h-3.5 w-3.5" />
                                            Review Approval
                                        </a>
                                    @else
                                        <a href="{{ $this->getShowUrl($show) }}"
                                           class="inline-flex items-center gap-1 text-[11px] font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:underline">
                                            <x-heroicon-o-arrow-right class="h-3.5 w-3.5" />
                                            View
                                        </a>
                                    @endif

                                    {{-- Deduction request status indicator --}}
                                    @if ($show->latestDeductionRequest)
                                        @php $drStatus = $show->latestDeductionRequest->status; @endphp
                                        <span class="ml-auto text-[10px] font-semibold uppercase tracking-wide
                                            {{ $drStatus === 'pending' ? 'text-amber-600 dark:text-amber-400' : '' }}
                                            {{ $drStatus === 'processed' ? 'text-emerald-600 dark:text-emerald-400' : '' }}
                                            {{ $drStatus === 'rejected' ? 'text-red-500' : '' }}
                                            {{ in_array($drStatus, ['draft']) ? 'text-gray-400' : '' }}">
                                            {{ \App\Models\DeductionRequest::statusLabels()[$drStatus] ?? $drStatus }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="flex items-center justify-center h-16 text-xs text-gray-400 dark:text-gray-600">
                                No shows here
                            </div>
                        @endforelse
                    </div>

                    @if ($column['hiddenOlder'] > 0)
                        <a href="{{ $this->getReconciledShowsUrl() }}"
                           class="flex-shrink-0 flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <x-heroicon-o-archive-box class="h-3.5 w-3.5" />
                            +{{ $column['hiddenOlder'] }} older than {{ $column['windowDays'] }}d — view all reconciled
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
