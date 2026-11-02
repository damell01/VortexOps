<section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-6" data-vx-page="assistant-dashboard">
    <div class="text-xs font-bold uppercase tracking-wide text-primary-600">Streamer assistant</div>
    <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">My dashboard</h2>
    <p class="mt-1 text-sm text-gray-500">Clock in when you start, clock out when you finish, and view your paid pay runs.</p>
</section>
<livewire:clock-widget :compact="true" :key="'assistant-dashboard-clock'" />
<section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-6" aria-label="My time history">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">My time history</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Your recent clock-ins and clock-outs, newest first.</p>
        </div>
        <div class="rounded-xl bg-blue-50 px-4 py-3 text-right dark:bg-blue-950/40">
            <p class="text-xs font-medium text-blue-700 dark:text-blue-300">Hours this week</p>
            <p class="text-xl font-bold text-blue-900 dark:text-blue-100">{{ \App\Models\TimeEntry::formatMinutes((int) $assistantWeekMinutes) }}</p>
        </div>
    </div>
    <div class="mt-5 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full min-w-[540px] text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                <tr><th scope="col" class="px-4 py-3">Date</th><th scope="col" class="px-4 py-3">Clock in</th><th scope="col" class="px-4 py-3">Clock out</th><th scope="col" class="px-4 py-3">Duration</th><th scope="col" class="px-4 py-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($assistantTimeEntries as $entry)
                    @php $tz = auth()->user()?->timezone ?: config('app.timezone'); @endphp
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $entry->clocked_in_at?->timezone($tz)->format('M j, Y') }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $entry->clocked_in_at?->timezone($tz)->format('g:i A') }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $entry->clocked_out_at?->timezone($tz)->format('g:i A') ?? 'Still clocked in' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-semibold">{{ $entry->duration_minutes === null ? '—' : \App\Models\TimeEntry::formatMinutes((int) $entry->duration_minutes) }}</td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $entry->is_open ? 'In progress' : match($entry->status) { 'ok' => 'Recorded', 'approved' => 'Approved', 'flagged' => 'Needs review', 'rejected' => 'Rejected', default => ucfirst((string) $entry->status) } }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No time clock history yet. Your shifts will appear here after you clock in.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($assistantTimeEntries->hasPages())
        <div class="mt-4 flex items-center justify-between gap-3">
            <button type="button" wire:click="previousAssistantTimePage" @disabled($assistantTimeEntries->onFirstPage()) class="vxw-btn vxw-btn--sm">Previous</button>
            <span class="text-xs text-gray-500">Page {{ $assistantTimeEntries->currentPage() }} of {{ $assistantTimeEntries->lastPage() }}</span>
            <button type="button" wire:click="nextAssistantTimePage" @disabled(!$assistantTimeEntries->hasMorePages()) class="vxw-btn vxw-btn--sm">Next</button>
        </div>
    @endif
</section>
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
