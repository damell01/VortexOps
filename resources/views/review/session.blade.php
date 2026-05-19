<x-review-layout
    title="{{ $session->title }}"
    :session-id="$session->id"
    :breadcrumb="'<a href=\''.route('review.session', $session).'\' class=\'text-sm font-medium text-gray-700\'>'.$session->title.'</a>'"
>

    <div class="mb-6 flex items-start justify-between">
        <div>
            <a href="{{ route('review.index') }}" class="mb-2 inline-flex items-center gap-1 text-xs text-gray-400 hover:text-gray-600">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                All Sessions
            </a>
            <h1 class="text-2xl font-bold text-gray-900">{{ $session->title }}</h1>
            <p class="mt-1 text-xs text-gray-400">{{ $items->count() }} item{{ $items->count() !== 1 ? 's' : '' }}</p>
        </div>
    </div>

    @if ($items->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white py-16 text-center">
            <p class="text-sm font-medium text-gray-400">No items in this session yet.</p>
            <p class="mt-1 text-xs text-gray-300">Use "Leave Feedback" above to annotate any page.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($items as $item)
                @php
                    $statusMap = [
                        'open'        => ['bg-red-50 text-red-700 ring-red-200',       'Open'],
                        'in_progress' => ['bg-yellow-50 text-yellow-700 ring-yellow-200', 'In Progress'],
                        'fixed'       => ['bg-green-50 text-green-700 ring-green-200',   'Fixed'],
                        'approved'    => ['bg-emerald-50 text-emerald-700 ring-emerald-200', 'Approved'],
                        'rejected'    => ['bg-gray-100 text-gray-500 ring-gray-200',     'Rejected'],
                        'wont_fix'    => ['bg-gray-100 text-gray-500 ring-gray-200',     "Won't Fix"],
                    ];
                    $typeMap = [
                        'annotation' => ['bg-violet-50 text-violet-700', '✏️ Annotation'],
                        'bug'        => ['bg-red-50 text-red-700',        '🐛 Bug'],
                        'suggestion' => ['bg-blue-50 text-blue-700',      '💡 Suggestion'],
                        'question'   => ['bg-amber-50 text-amber-700',    '❓ Question'],
                    ];
                    [$statusCss, $statusLabel] = $statusMap[$item->status]   ?? ['bg-gray-100 text-gray-500 ring-gray-200', ucfirst($item->status)];
                    [$typeCss, $typeLabel]     = $typeMap[$item->type]       ?? ['bg-gray-100 text-gray-600', ucfirst($item->type)];
                @endphp

                <a href="{{ route('review.item', $item) }}"
                   class="flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-violet-300 hover:shadow-sm">

                    {{-- Screenshot thumb --}}
                    <div class="h-14 w-20 shrink-0 overflow-hidden rounded-lg bg-gray-100">
                        @if ($item->screenshot)
                            <img src="{{ $item->screenshot }}" class="h-full w-full object-cover" alt="Screenshot">
                        @else
                            <div class="flex h-full w-full items-center justify-center">
                                <svg class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        @endif
                    </div>

                    {{-- Details --}}
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-900">
                            {{ $item->page_title ?: $item->page_url }}
                        </p>
                        @if ($item->comment)
                            <p class="mt-0.5 truncate text-xs text-gray-400">{{ $item->comment }}</p>
                        @endif
                        <div class="mt-1.5 flex items-center gap-1.5">
                            <span class="rounded-md px-1.5 py-0.5 text-[11px] font-medium {{ $typeCss }}">{{ $typeLabel }}</span>
                            @if ($item->comments->count() > 0)
                                <span class="text-[11px] text-gray-400">· {{ $item->comments->count() }} {{ $item->comments->count() === 1 ? 'reply' : 'replies' }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Status + date --}}
                    <div class="flex shrink-0 flex-col items-end gap-1.5">
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 {{ $statusCss }}">
                            {{ $statusLabel }}
                        </span>
                        <span class="text-[11px] text-gray-400">{{ $item->created_at->diffForHumans() }}</span>
                    </div>

                    <svg class="h-4 w-4 shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @endforeach
        </div>
    @endif

</x-review-layout>
