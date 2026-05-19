<x-review-layout title="Dashboard">

    <div class="mb-8 flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Review Sessions</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ auth()->user()->isSuperAdmin() ? 'All sessions across the platform.' : 'Sessions containing your feedback.' }}
            </p>
        </div>
    </div>

    @forelse ($sessions as $session)
        @php
            $statusColors = [
                'open'      => 'bg-green-50 text-green-700 ring-green-200',
                'submitted' => 'bg-yellow-50 text-yellow-700 ring-yellow-200',
                'closed'    => 'bg-gray-100 text-gray-500 ring-gray-200',
            ];
            $pill = $statusColors[$session->status] ?? 'bg-gray-100 text-gray-500 ring-gray-200';
        @endphp

        <a href="{{ route('review.session', $session) }}"
           class="mb-4 flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-violet-300 hover:shadow-md">

            <div class="flex items-center gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50">
                    <svg class="h-5 w-5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-gray-900">{{ $session->title }}</p>
                    <p class="text-xs text-gray-400">{{ $session->created_at->format('M j, Y') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-4">
                @if ($session->open_count > 0)
                    <div class="text-center">
                        <p class="text-lg font-bold text-red-600">{{ $session->open_count }}</p>
                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">Open</p>
                    </div>
                @endif
                @if ($session->fixed_count > 0)
                    <div class="text-center">
                        <p class="text-lg font-bold text-green-600">{{ $session->fixed_count }}</p>
                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">Fixed</p>
                    </div>
                @endif
                <div class="text-center">
                    <p class="text-lg font-bold text-gray-700">{{ $session->total_count }}</p>
                    <p class="text-[10px] text-gray-400 uppercase tracking-wide">Total</p>
                </div>

                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 {{ $pill }}">
                    {{ ucfirst($session->status) }}
                </span>

                <svg class="h-4 w-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </a>
    @empty
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white py-16 text-center">
            <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm font-medium text-gray-400">No review sessions yet.</p>
            <p class="mt-1 text-xs text-gray-300">Use the "Leave Feedback" button to start annotating.</p>
        </div>
    @endforelse

</x-review-layout>
