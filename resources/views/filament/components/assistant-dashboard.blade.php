<section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-6" data-vx-page="assistant-dashboard">
    <div class="text-xs font-bold uppercase tracking-wide text-primary-600">Streamer assistant</div>
    <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">My dashboard</h2>
    <p class="mt-1 text-sm text-gray-500">Clock in when you start, clock out when you finish, and view your paid pay runs.</p>
</section>
<livewire:clock-widget :compact="true" :key="'assistant-dashboard-clock'" />
<section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-6" aria-label="Paid pay runs">
    <h2 class="text-sm font-semibold">Paid pay runs</h2>
    <p class="mt-2 text-2xl font-bold text-emerald-600">${{ number_format((float) $assistantPaidTotal, 2) }} <span class="text-xs font-normal text-gray-500">total paid</span></p>
    <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
        @forelse($assistantRecentPay as $payment)
            <div class="flex flex-wrap justify-between gap-3 py-3 text-sm"><span>{{ $payment->batch?->week_start?->format('M j') }} – {{ $payment->batch?->week_end?->format('M j, Y') }}</span><strong>${{ number_format((float) $payment->calculated_payout, 2) }}</strong></div>
        @empty
            <p class="py-3 text-sm text-gray-500">No paid pay runs yet.</p>
        @endforelse
    </div>
    @if($assistantRecentPay->hasPages())
        <div class="mt-3 flex items-center justify-between gap-3">
            <button type="button" wire:click="previousAssistantPayPage" @disabled($assistantRecentPay->onFirstPage()) class="vxw-btn vxw-btn--sm">Previous</button>
            <span class="text-xs text-gray-500">Page {{ $assistantRecentPay->currentPage() }} of {{ $assistantRecentPay->lastPage() }}</span>
            <button type="button" wire:click="nextAssistantPayPage" @disabled(!$assistantRecentPay->hasMorePages()) class="vxw-btn vxw-btn--sm">Next</button>
        </div>
    @endif
</section>
