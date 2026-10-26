<x-filament-panels::page>
    <section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-6">
        <h2 class="text-sm font-semibold">Total paid</h2>
        <p class="mt-2 text-3xl font-bold text-emerald-600">${{ number_format((float) $paidTotal, 2) }}</p>
        <p class="mt-2 text-sm text-gray-500">Your payments from pay runs marked paid.</p>
    </section>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($payments as $payment)
            <article class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900" wire:key="my-payment-{{ $payment->id }}">
                <div class="text-xs font-semibold text-emerald-600">Paid</div>
                <h2 class="mt-1 text-sm font-semibold">{{ $payment->batch?->week_start?->format('M j, Y') }} – {{ $payment->batch?->week_end?->format('M j, Y') }}</h2>
                <p class="mt-2 text-2xl font-bold">${{ number_format((float) $payment->calculated_payout, 2) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ \App\Models\Streamer::payoutTypeLabels()[$payment->payout_type] ?? ucfirst($payment->payout_type ?? 'Payment') }}</p>
                @if($payment->show)<p class="mt-2 text-sm">{{ $payment->show->title ?: 'Show #'.$payment->show_id }}</p>@endif
                <dl class="mt-3 space-y-1 text-xs text-gray-500">
                    @if((float) $payment->hours_worked > 0)<div class="flex justify-between gap-2"><dt>Hours</dt><dd>{{ number_format((float) $payment->hours_worked, 2) }}</dd></div>@endif
                    @if((float) $payment->tips_included > 0)<div class="flex justify-between gap-2"><dt>Tips included</dt><dd>${{ number_format((float) $payment->tips_included, 2) }}</dd></div>@endif
                    @if((float) $payment->loan_repayment_deducted > 0)<div class="flex justify-between gap-2"><dt>Loan repayment</dt><dd>${{ number_format((float) $payment->loan_repayment_deducted, 2) }}</dd></div>@endif
                </dl>
            </article>
        @empty
            <p class="text-sm text-gray-500">No paid pay runs yet. Payments appear here after a pay run is marked paid.</p>
        @endforelse
    </div>
    {{ $payments->links() }}
</x-filament-panels::page>
