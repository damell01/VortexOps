<section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-6" data-vx-page="assistant-dashboard">
    <div class="text-xs font-bold uppercase tracking-wide text-primary-600">Streamer assistant</div>
    <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">Shows across the team</h2>
    <p class="mt-1 text-sm text-gray-500">Find upcoming shows, check who is streaming, and track your time.</p>
</section>
@if($canViewShows)
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach(['Upcoming shows' => $upcomingAssistantShows, 'Recent shows' => $recentAssistantShows] as $heading => $shows)
            <section class="min-w-0 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold">{{ $heading }}</h2>
                    <a class="text-sm font-semibold text-primary-600" href="{{ \App\Filament\Resources\ShowResource::getUrl() }}">View all shows</a>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($shows as $show)
                        <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}" class="block min-w-0 rounded-lg px-2 py-3 hover:bg-gray-50 dark:hover:bg-gray-800">
                            <div class="truncate text-sm font-semibold">{{ $show->title ?: 'Show #'.$show->id }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $show->show_date?->format('M j, Y') }}{{ $show->start_time ? ' · '.$show->start_time->format('g:i A') : '' }} · {{ $show->channel?->name ?: 'No channel' }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $show->streamers->pluck('name')->join(', ') ?: 'Streamer not assigned' }} · {{ ucfirst(str_replace('_', ' ', $show->status ?? 'unknown')) }}</div>
                        </a>
                    @empty
                        <p class="py-6 text-sm text-gray-500">No {{ strtolower($heading) }}.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
@endif
@if($canViewPay)
    <section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-3"><h2 class="text-sm font-semibold">My pay</h2><a class="text-sm font-semibold text-primary-600" href="{{ \App\Filament\Pages\MyPay::getUrl() }}">Payment history</a></div>
        <p class="mt-2 text-2xl font-bold text-emerald-600">${{ number_format((float) $assistantPaidTotal, 2) }} <span class="text-xs font-normal text-gray-500">total paid</span></p>
        <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
            @forelse($assistantRecentPay as $payment)
                <div class="flex justify-between gap-3 py-2 text-sm"><span>{{ $payment->batch?->week_start?->format('M j') }} – {{ $payment->batch?->week_end?->format('M j, Y') }}</span><strong>${{ number_format((float) $payment->calculated_payout, 2) }}</strong></div>
            @empty
                <p class="py-3 text-sm text-gray-500">No paid pay runs yet.</p>
            @endforelse
        </div>
    </section>
@endif
@if(\App\Livewire\StreamerLeaderboard::canView())
    @vite('resources/js/colosseum.js')
    <livewire:streamer-leaderboard :arena-compact="true" :key="'assistant-dashboard-leaderboard'" />
@endif
