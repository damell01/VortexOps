<x-filament-panels::page>
    @php($payout = $this->record)

    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-3">
            <x-filament::section class="lg:col-span-2">
                <x-slot name="heading">Payout Summary</x-slot>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <div class="text-sm text-gray-500">Show</div>
                        <div class="font-medium">{{ $payout->show?->title ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Show Date</div>
                        <div class="font-medium">{{ $payout->show?->show_date?->format('M j, Y') ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Streamer</div>
                        <div class="font-medium">{{ $payout->streamer?->name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Payout Type</div>
                        <div class="font-medium">{{ \App\Models\Streamer::payoutTypeLabels()[$payout->payout_type] ?? $payout->payout_type }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Status</div>
                        <div class="font-medium">{{ \App\Models\Payout::statusLabels()[$payout->status] ?? $payout->status }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Pay Run</div>
                        <div class="font-medium">{{ $payout->batch?->week_start?->format('M j, Y') ?? 'Unbatched' }}</div>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Final Payout</x-slot>

                <div class="text-3xl font-bold text-success-600">
                    ${{ number_format((float) $payout->calculated_payout, 2) }}
                </div>
                <div class="mt-2 text-sm text-gray-500">
                    Calculated from the configured payout rules for this streamer.
                </div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">Calculation Breakdown</x-slot>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                    <div class="text-sm text-gray-500">Gross Revenue</div>
                    <div class="mt-1 text-lg font-semibold">${{ number_format((float) $payout->gross_show_revenue, 2) }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                    <div class="text-sm text-gray-500">Tips Included</div>
                    <div class="mt-1 text-lg font-semibold">${{ number_format((float) $payout->tips_included, 2) }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                    <div class="text-sm text-gray-500">Owner Fee Deducted</div>
                    <div class="mt-1 text-lg font-semibold">${{ number_format((float) $payout->owner_fee_deducted, 2) }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                    <div class="text-sm text-gray-500">Loan Repayment Deducted</div>
                    <div class="mt-1 text-lg font-semibold">${{ number_format((float) $payout->loan_repayment_deducted, 2) }}</div>
                </div>
            </div>

            <div class="mt-4">
                <div class="text-sm text-gray-500">Notes</div>
                <div class="mt-1 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-900">
                    {{ $payout->calculation_notes ?: 'No additional calculation notes were saved for this payout.' }}
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
