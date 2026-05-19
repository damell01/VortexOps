<x-review-layout
    title="{{ $item->page_title ?: 'Review Item #'.$item->id }}"
    :session-id="$item->review_session_id"
    :project-id="$item->session->project?->id"
    :breadcrumb="$item->session->project ? '<a href=\''.route('review.project', $item->session->project).'\' class=\'text-sm font-medium text-gray-700\'>'.$item->session->project->name.'</a><span class=\'mx-2 text-gray-300\'>/</span><a href=\''.route('review.session', $item->session).'\' class=\'text-sm font-medium text-gray-700\'>'.$item->session->title.'</a>' : '<a href=\''.route('review.session', $item->session).'\' class=\'text-sm font-medium text-gray-700\'>'.$item->session->title.'</a>'"
>

@php
    $statusMap = [
        'open' => ['bg-red-100 text-red-700', 'ring-red-300', 'Open'],
        'in_progress' => ['bg-yellow-100 text-yellow-700', 'ring-yellow-300', 'In Progress'],
        'fixed' => ['bg-green-100 text-green-700', 'ring-green-300', 'Fixed'],
        'approved' => ['bg-emerald-100 text-emerald-700', 'ring-emerald-300', 'Approved'],
        'rejected' => ['bg-gray-100 text-gray-500', 'ring-gray-300', 'Rejected'],
        'wont_fix' => ['bg-gray-100 text-gray-500', 'ring-gray-300', "Won't Fix"],
    ];
    $typeMap = [
        'annotation' => 'Annotation',
        'bug' => 'Bug',
        'suggestion' => 'Suggestion',
        'question' => 'Question',
    ];
    [$statusBg, $statusRing, $statusLabel] = $statusMap[$item->status] ?? ['bg-gray-100 text-gray-500', 'ring-gray-300', ucfirst($item->status)];
    $isSuperAdmin = auth()->user()->isSuperAdmin();
@endphp

    <a href="{{ route('review.session', $item->session) }}"
       class="mb-4 inline-flex items-center gap-1 text-xs text-gray-400 hover:text-gray-600">
        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        {{ $item->session->title }}
    </a>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">
                            {{ $item->page_title ?: 'Item #' . $item->id }}
                        </h1>
                        <a href="{{ $item->page_url }}" target="_blank"
                           class="mt-0.5 inline-block max-w-xs truncate text-xs text-violet-500 hover:underline">
                            {{ $item->page_url }}
                        </a>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $statusBg }} {{ $statusRing }}">
                        {{ $statusLabel }}
                    </span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                        {{ $typeMap[$item->type] ?? ucfirst($item->type) }}
                    </span>
                    @php
                        $priorityCss = match($item->priority) {
                            'high' => 'bg-red-50 text-red-700',
                            'low' => 'bg-gray-100 text-gray-500',
                            default => 'bg-yellow-50 text-yellow-700',
                        };
                    @endphp
                    <span class="rounded-lg px-2.5 py-1 text-xs font-medium {{ $priorityCss }}">
                        {{ ucfirst($item->priority) }} priority
                    </span>
                    <span class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs text-gray-500">
                        {{ $item->created_at->format('M j, Y g:i A') }}
                    </span>
                    @if ($isSuperAdmin && $item->createdBy)
                        <span class="rounded-lg bg-violet-50 px-2.5 py-1 text-xs text-violet-700">
                            by {{ $item->createdBy->name }}
                        </span>
                    @endif
                    @if ($isSuperAdmin && $item->assignedTo)
                        <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs text-blue-700">
                            assigned to {{ $item->assignedTo->name }}
                        </span>
                    @endif
                </div>

                @if ($item->comment)
                    <p class="mt-4 rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-700">
                        {{ $item->comment }}
                    </p>
                @endif
            </div>

            @if ($isSuperAdmin)
                <div class="rounded-2xl border border-violet-100 bg-violet-50 p-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-violet-600">Update Status</p>
                    <form method="POST" action="{{ route('review.item.status', $item) }}" class="flex flex-wrap gap-2">
                        @csrf
                        @method('PATCH')
                        @foreach (\App\Models\ReviewItem::statusLabels() as $value => $label)
                            <button
                                type="submit"
                                name="status"
                                value="{{ $value }}"
                                class="rounded-lg border px-3 py-1.5 text-xs font-medium transition
                                    {{ $item->status === $value
                                        ? 'border-violet-500 bg-violet-600 text-white shadow-sm'
                                        : 'border-gray-200 bg-white text-gray-700 hover:border-violet-300 hover:text-violet-700' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </form>
                </div>
            @endif

            @if ($item->screenshot)
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-4 py-2.5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Annotation</p>
                    </div>
                    <img src="{{ $item->screenshot }}"
                         alt="Annotated screenshot"
                         class="block w-full"
                         loading="lazy">
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-4 py-3">
                    <p class="text-sm font-semibold text-gray-900">
                        Thread
                        @if ($item->comments->isNotEmpty())
                            <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
                                {{ $item->comments->count() }}
                            </span>
                        @endif
                    </p>
                </div>

                <div class="divide-y divide-gray-50">
                    @forelse ($item->comments as $comment)
                        @php $isMe = $comment->user_id === auth()->id(); @endphp
                        <div class="px-4 py-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold {{ $isMe ? 'text-violet-700' : 'text-gray-700' }}">
                                    {{ $comment->user?->name ?? 'Unknown' }}
                                    @if ($isMe) <span class="font-normal text-violet-400">(you)</span> @endif
                                </span>
                                <span class="text-[11px] text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="mt-1.5 whitespace-pre-wrap text-sm text-gray-700">{{ $comment->body }}</p>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-xs text-gray-400">
                            No comments yet. Add one below.
                        </div>
                    @endforelse
                </div>

                <div class="border-t border-gray-100 p-4">
                    <form method="POST" action="{{ route('review.item.comment', $item) }}">
                        @csrf
                        <textarea
                            name="body"
                            rows="3"
                            placeholder="Leave a note, ask a question, or give an update..."
                            class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-violet-400 focus:bg-white focus:outline-none focus:ring-1 focus:ring-violet-400"
                            required
                        ></textarea>
                        @error('body')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        <button
                            type="submit"
                            class="mt-2 w-full rounded-lg bg-violet-600 py-2 text-xs font-semibold text-white transition hover:bg-violet-700"
                        >
                            Send Reply
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</x-review-layout>
