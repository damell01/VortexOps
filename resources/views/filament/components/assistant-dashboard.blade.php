<section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-6" data-vx-page="assistant-dashboard">
    <div class="text-xs font-bold uppercase tracking-wide text-primary-600">Streamer assistant</div>
    <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">Shows across the team</h2>
    <p class="mt-1 text-sm text-gray-500">Find upcoming shows, check who is streaming, and track your time.</p>
</section>
@if(\App\Filament\Pages\Timekeeping::canAccess())
    <livewire:clock-widget :compact="true" :key="'assistant-dashboard-clock'" />
@endif
@if($canViewShows)
    <style>
        .vx-ah-cards{display:grid;gap:10px;grid-template-columns:repeat(auto-fill,minmax(230px,1fr))}
        .vx-ah-card{display:flex;gap:12px;align-items:flex-start;min-width:0;padding:12px;border:1px solid rgb(229 231 235);border-radius:14px;background:#fff;transition:border-color .15s}
        .vx-ah-card:hover{border-color:rgb(124 58 237 / .5)}
        .dark .vx-ah-card{border-color:rgb(55 65 81);background:rgb(17 24 39)}
        .vx-ah-date{flex:none;display:flex;flex-direction:column;align-items:center;width:48px;padding:6px 0;border-radius:10px;background:rgb(243 244 246);font-size:10px;line-height:1.2;text-transform:uppercase;color:rgb(107 114 128)}
        .dark .vx-ah-date{background:rgb(31 41 55)}
        .vx-ah-date b{font-size:20px;line-height:1.1;color:rgb(17 24 39)}
        .dark .vx-ah-date b{color:#fff}
        .vx-ah-title{font-weight:600;font-size:13px;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .vx-ah-meta{font-size:12px;color:rgb(107 114 128);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    </style>
    @foreach(['Upcoming shows' => $upcomingAssistantShows, 'Recent shows' => $recentAssistantShows] as $heading => $shows)
        <section class="min-w-0 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold">{{ $heading }}</h2>
                <div class="flex gap-4">
                    @if($loop->first && \App\Filament\Pages\Shows::canAccess())<a class="text-sm font-semibold text-primary-600" href="{{ \App\Filament\Pages\Shows::getUrl() }}">Calendar</a>@endif
                    <a class="text-sm font-semibold text-primary-600" href="{{ \App\Filament\Resources\ShowResource::getUrl() }}">View all shows</a>
                </div>
            </div>
            @if($shows->isEmpty())
                <p class="py-6 text-sm text-gray-500">No {{ strtolower($heading) }}.</p>
            @else
                <div class="vx-ah-cards">
                    @foreach($shows as $show)
                        <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}" class="vx-ah-card" wire:key="ah-{{ $loop->parent->index }}-{{ $show->id }}">
                            <span class="vx-ah-date"><span>{{ $show->show_date?->format('M') }}</span><b>{{ $show->show_date?->format('j') }}</b><span>{{ $show->show_date?->format('D') }}</span></span>
                            <span class="min-w-0">
                                <span class="vx-ah-title" title="{{ $show->title }}">{{ $show->title ?: 'Show #'.$show->id }}</span>
                                <span class="vx-ah-meta block">{{ $show->start_time ? $show->start_time->format('g:i A') : 'Time TBD' }} · {{ $show->streamers->pluck('name')->join(', ') ?: 'Streamer not assigned' }}</span>
                                <span class="vx-ah-meta block">{{ $show->channel?->name ?: 'No channel' }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach
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
