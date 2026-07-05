<x-filament-panels::page>
    @php($payout = $this->record)

    <div class="space-y-6">

        {{-- ── Summary + Final Payout ──────────────────────────────────────── --}}
        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Summary card --}}
            <div class="lg:col-span-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Payout Summary</h2>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 divide-y divide-gray-100 dark:divide-gray-800 sm:divide-y-0 sm:divide-x">
                    @foreach ([
                        ['label' => 'Show',          'value' => $payout->show?->title ?? '—'],
                        ['label' => 'Show Date',      'value' => $payout->show?->show_date?->format('M j, Y') ?? '—'],
                        ['label' => 'Streamer',       'value' => $payout->streamer?->name ?? '—'],
                        ['label' => 'Payout Type',    'value' => \App\Models\Streamer::payoutTypeLabels()[$payout->payout_type] ?? $payout->payout_type],
                        ['label' => 'Status',         'value' => null, 'badge' => true],
                        ['label' => 'Pay Run',        'value' => $payout->batch?->week_start?->format('M j, Y') ?? 'Unbatched'],
                    ] as $field)
                        <div class="px-5 py-3.5 sm:border-b sm:border-gray-100 sm:dark:border-gray-800">
                            <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ $field['label'] }}</dt>
                            @if (!empty($field['badge']))
                                @php
                                    $statusColors = [
                                        'draft'    => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
                                        'approved' => 'bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300',
                                        'paid'     => 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300',
                                        'pending'  => 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300',
                                    ];
                                    $statusLabel = \App\Models\Payout::statusLabels()[$payout->status] ?? ucfirst($payout->status);
                                    $statusColor = $statusColors[$payout->status] ?? 'bg-gray-100 text-gray-600';
                                @endphp
                                <dd class="mt-1">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusColor }}">
                                        {{ $statusLabel }}
                                    </span>
                                </dd>
                            @else
                                <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $field['value'] }}</dd>
                            @endif
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Final payout highlight --}}
            <div class="rounded-xl border-2 border-emerald-200 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950 overflow-hidden flex flex-col">
                <div class="px-5 py-3.5 border-b border-emerald-100 dark:border-emerald-800">
                    <h2 class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">Final Payout</h2>
                </div>
                <div class="flex-1 flex flex-col items-center justify-center px-5 py-8">
                    <p class="text-4xl font-extrabold text-emerald-600 dark:text-emerald-400 tabular-nums">
                        ${{ number_format((float) $payout->calculated_payout, 2) }}
                    </p>
                    <p class="mt-2 text-xs text-emerald-600 dark:text-emerald-500 text-center">
                        Calculated from payout rules for this streamer
                    </p>
                </div>
            </div>
        </div>

        {{-- ── Calculation Breakdown ────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Calculation Breakdown</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-gray-100 dark:divide-gray-800">
                @foreach ([
                    ['label' => 'Gross Revenue',          'value' => $payout->gross_show_revenue,      'accent' => 'border-violet-400',  'text' => 'text-violet-600 dark:text-violet-400'],
                    ['label' => 'Tips Included',          'value' => $payout->tips_included,           'accent' => 'border-amber-400',   'text' => 'text-amber-600 dark:text-amber-400'],
                    ['label' => 'Owner Fee Deducted',     'value' => $payout->owner_fee_deducted,      'accent' => 'border-rose-400',    'text' => 'text-rose-600 dark:text-rose-400'],
                    ['label' => 'Loan Repayment Deducted','value' => $payout->loan_repayment_deducted, 'accent' => 'border-orange-400',  'text' => 'text-orange-600 dark:text-orange-400'],
                ] as $item)
                    <div class="px-5 py-4 border-l-4 {{ $item['accent'] }}">
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ $item['label'] }}</p>
                        <p class="mt-1.5 text-xl font-bold {{ $item['text'] }} tabular-nums">
                            ${{ number_format((float) $item['value'], 2) }}
                        </p>
                    </div>
                @endforeach
            </div>

            @if ($payout->calculation_notes)
                <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-800">
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1.5">Notes</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300 rounded-lg bg-gray-50 dark:bg-gray-800 px-3 py-2.5">
                        {{ $payout->calculation_notes }}
                    </p>
                </div>
            @endif
        </div>

        {{-- ── Routing Information ──────────────────────────────────────────── --}}
        @if ($payout->routing_bank_label || $payout->streamer?->channel_routing_rules)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Routing</h2>
                </div>
                <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @if ($payout->routing_bank_label)
                        <div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Bank Label</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $payout->routing_bank_label }}</p>
                        </div>
                    @endif
                    @if ($payout->payout_reference)
                        <div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Reference</p>
                            <p class="mt-1 text-sm font-mono text-gray-700 dark:text-gray-300">{{ $payout->payout_reference }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
