<x-review-layout
    title="{{ $item->page_title ?: 'Review Item #' . $item->id }}"
    :session-id="$item->review_session_id"
    :project-id="$item->session->project?->id"
    :breadcrumb="$item->session->project ? '<a href=\''.route('review.project', $item->session->project).'\' class=\'text-sm font-medium text-slate-800\'>'.$item->session->project->name.'</a><span class=\'mx-2 text-slate-400\'>/</span><a href=\''.route('review.session', $item->session).'\' class=\'text-sm font-medium text-slate-500\'>'.$item->session->title.'</a>' : '<a href=\''.route('review.session', $item->session).'\' class=\'text-sm font-medium text-slate-500\'>'.$item->session->title.'</a>'"
>

@php
    $statusMap = [
        'open' => ['text-rose-700 bg-rose-50 border-rose-200', 'Open'],
        'in_progress' => ['text-amber-700 bg-amber-50 border-amber-200', 'In Progress'],
        'fixed' => ['text-emerald-700 bg-emerald-50 border-emerald-200', 'Fixed'],
        'approved' => ['text-cyan-700 bg-cyan-50 border-cyan-200', 'Approved'],
        'rejected' => ['text-slate-600 bg-slate-100 border-slate-200', 'Rejected'],
        'wont_fix' => ['text-slate-600 bg-slate-100 border-slate-200', "Won't Fix"],
    ];
    $typeMap = [
        'annotation' => ['Annotation', 'text-violet-700 bg-violet-50 border-violet-200'],
        'bug' => ['Bug', 'text-rose-700 bg-rose-50 border-rose-200'],
        'suggestion' => ['Suggestion', 'text-sky-700 bg-sky-50 border-sky-200'],
        'question' => ['Question', 'text-amber-700 bg-amber-50 border-amber-200'],
    ];
    $priorityMap = [
        'high' => 'text-rose-700 bg-rose-50 border-rose-200',
        'normal' => 'text-amber-700 bg-amber-50 border-amber-200',
        'low' => 'text-slate-600 bg-slate-100 border-slate-200',
    ];
    [$statusCss, $statusLabel] = $statusMap[$item->status] ?? ['text-slate-600 bg-slate-100 border-slate-200', ucfirst($item->status)];
    [$typeLabel, $typeCss] = $typeMap[$item->type] ?? [ucfirst($item->type), 'text-slate-600 bg-slate-100 border-slate-200'];
    $priorityCss = $priorityMap[$item->priority] ?? 'text-slate-600 bg-slate-100 border-slate-200';
    $isSuperAdmin = auth()->user()->isSuperAdmin();
@endphp

    <section class="review-hero rounded-[1.75rem] p-6">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <a href="{{ route('review.session', $item->session) }}" class="review-kicker inline-flex items-center gap-2 text-xs font-medium uppercase tracking-[0.18em] transition hover:text-cyan-700">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    {{ $item->session->title }}
                </a>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">
                    {{ $item->page_title ?: 'Item #' . $item->id }}
                </h1>
                <a href="{{ $item->page_url }}" target="_blank" class="mt-3 inline-flex max-w-full items-center gap-2 truncate text-sm text-sky-600 transition hover:text-sky-700">
                    <span class="truncate">{{ $item->page_url }}</span>
                </a>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] {{ $statusCss }}">{{ $statusLabel }}</span>
                <span class="rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] {{ $typeCss }}">{{ $typeLabel }}</span>
                <span class="rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] {{ $priorityCss }}">{{ ucfirst($item->priority) }} Priority</span>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-slate-200 pt-4 text-xs text-slate-500">
            <span>Submitted {{ $item->created_at->format('M j, Y g:i A') }}</span>
            @if ($isSuperAdmin && $item->createdBy)
                <span>Reporter <span class="font-medium text-slate-800">{{ $item->createdBy->name }}</span></span>
            @endif
            @if ($isSuperAdmin && $item->assignedTo)
                <span>Assigned <span class="font-medium text-slate-800">{{ $item->assignedTo->name }}</span></span>
            @endif
        </div>
    </section>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[1.35fr,0.65fr]">
        <div class="space-y-6">
            @if ($item->comment)
                <section class="review-surface rounded-[1.5rem] p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Context</p>
                    <p class="mt-4 whitespace-pre-wrap text-sm leading-7 text-slate-700">{{ $item->comment }}</p>
                </section>
            @endif

            @if ($isSuperAdmin)
                <section class="review-surface rounded-[1.5rem] p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-700">Workflow Controls</p>
                    <form method="POST" action="{{ route('review.item.status', $item) }}" class="mt-4 flex flex-wrap gap-2">
                        @csrf
                        @method('PATCH')
                        @foreach (\App\Models\ReviewItem::statusLabels() as $value => $label)
                            <button
                                type="submit"
                                name="status"
                                value="{{ $value }}"
                                class="rounded-xl border px-3 py-2 text-xs font-semibold transition {{ $item->status === $value ? 'border-cyan-300 bg-cyan-500 text-white shadow-lg shadow-cyan-500/20' : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-cyan-300 hover:text-slate-950' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </form>
                </section>
            @endif

            @if ($item->screenshot)
                <section class="review-surface overflow-hidden rounded-[1.5rem]">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Captured Page</p>
                            <p class="mt-1 text-sm text-slate-600">Saved screenshot with markup for this review item.</p>
                        </div>
                    </div>
                    <div class="bg-slate-50 p-3">
                        <img src="{{ $item->screenshot }}"
                             alt="Annotated screenshot"
                             class="block w-full rounded-2xl border border-slate-200"
                             loading="lazy">
                    </div>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            <section class="review-surface rounded-[1.5rem]">
                <div class="border-b border-slate-200 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Conversation Thread</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $item->comments->count() }} message{{ $item->comments->count() === 1 ? '' : 's' }}</p>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse ($item->comments as $comment)
                        @php $isMe = $comment->user_id === auth()->id(); @endphp
                        <div class="px-5 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-semibold {{ $isMe ? 'text-cyan-700' : 'text-slate-800' }}">
                                    {{ $comment->user?->name ?? 'Unknown' }}
                                    @if ($isMe)
                                        <span class="font-normal text-cyan-500">(you)</span>
                                    @endif
                                </span>
                                <span class="text-[11px] text-slate-400">{{ $comment->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="mt-2 whitespace-pre-wrap text-sm leading-7 text-slate-600">{{ $comment->body }}</p>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-500">
                            No comments yet. Start the thread below.
                        </div>
                    @endforelse
                </div>

                <div class="border-t border-slate-200 p-5">
                    <form method="POST" action="{{ route('review.item.comment', $item) }}">
                        @csrf
                        <textarea
                            name="body"
                            rows="4"
                            placeholder="Leave a note, ask a question, or post an update..."
                            class="w-full resize-none rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-100"
                            required
                        ></textarea>
                        @error('body')
                            <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                        <button
                            type="submit"
                            class="mt-3 w-full rounded-2xl bg-gradient-to-r from-cyan-500 via-sky-500 to-violet-600 py-3 text-sm font-semibold text-white shadow-lg shadow-cyan-500/20 transition hover:scale-[1.01]"
                        >
                            Send Reply
                        </button>
                    </form>
                </div>
            </section>
        </aside>
    </div>

</x-review-layout>
