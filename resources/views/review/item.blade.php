<x-review-layout
    title="{{ $item->page_title ?: 'Review Item #' . $item->id }}"
    :session-id="$item->review_session_id"
    :project-id="$item->session->project?->id"
    :breadcrumb="$item->session->project ? '<a href=\''.route('review.project', $item->session->project).'\' class=\'text-sm font-medium text-slate-200\'>'.$item->session->project->name.'</a><span class=\'mx-2 text-slate-500\'>/</span><a href=\''.route('review.session', $item->session).'\' class=\'text-sm font-medium text-slate-300\'>'.$item->session->title.'</a>' : '<a href=\''.route('review.session', $item->session).'\' class=\'text-sm font-medium text-slate-300\'>'.$item->session->title.'</a>'"
>

@php
    $statusMap = [
        'open' => ['text-rose-200 bg-rose-400/10 border-rose-300/20', 'Open'],
        'in_progress' => ['text-amber-200 bg-amber-400/10 border-amber-300/20', 'In Progress'],
        'fixed' => ['text-emerald-200 bg-emerald-400/10 border-emerald-300/20', 'Fixed'],
        'approved' => ['text-cyan-200 bg-cyan-400/10 border-cyan-300/20', 'Approved'],
        'rejected' => ['text-slate-300 bg-slate-400/10 border-slate-300/20', 'Rejected'],
        'wont_fix' => ['text-slate-300 bg-slate-400/10 border-slate-300/20', "Won't Fix"],
    ];
    $typeMap = [
        'annotation' => ['Annotation', 'text-violet-200 bg-violet-400/10 border-violet-300/20'],
        'bug' => ['Bug', 'text-rose-200 bg-rose-400/10 border-rose-300/20'],
        'suggestion' => ['Suggestion', 'text-sky-200 bg-sky-400/10 border-sky-300/20'],
        'question' => ['Question', 'text-amber-200 bg-amber-400/10 border-amber-300/20'],
    ];
    $priorityMap = [
        'high' => 'text-rose-200 bg-rose-400/10 border-rose-300/20',
        'normal' => 'text-amber-200 bg-amber-400/10 border-amber-300/20',
        'low' => 'text-slate-300 bg-slate-400/10 border-slate-300/20',
    ];
    [$statusCss, $statusLabel] = $statusMap[$item->status] ?? ['text-slate-300 bg-slate-400/10 border-slate-300/20', ucfirst($item->status)];
    [$typeLabel, $typeCss] = $typeMap[$item->type] ?? [ucfirst($item->type), 'text-slate-300 bg-slate-400/10 border-slate-300/20'];
    $priorityCss = $priorityMap[$item->priority] ?? 'text-slate-300 bg-slate-400/10 border-slate-300/20';
    $isSuperAdmin = auth()->user()->isSuperAdmin();
@endphp

    <section class="rounded-[1.75rem] border border-white/10 bg-[rgba(9,16,31,0.8)] p-6 shadow-[0_24px_60px_rgba(2,6,23,0.32)] backdrop-blur-xl">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <a href="{{ route('review.session', $item->session) }}" class="inline-flex items-center gap-2 text-xs font-medium uppercase tracking-[0.18em] text-cyan-300/80 transition hover:text-cyan-200">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    {{ $item->session->title }}
                </a>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white">
                    {{ $item->page_title ?: 'Item #' . $item->id }}
                </h1>
                <a href="{{ $item->page_url }}" target="_blank" class="mt-3 inline-flex max-w-full items-center gap-2 truncate text-sm text-sky-300 transition hover:text-sky-200">
                    <span class="truncate">{{ $item->page_url }}</span>
                </a>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] {{ $statusCss }}">{{ $statusLabel }}</span>
                <span class="rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] {{ $typeCss }}">{{ $typeLabel }}</span>
                <span class="rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] {{ $priorityCss }}">{{ ucfirst($item->priority) }} Priority</span>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-white/8 pt-4 text-xs text-slate-400">
            <span>Submitted {{ $item->created_at->format('M j, Y g:i A') }}</span>
            @if ($isSuperAdmin && $item->createdBy)
                <span>Reporter <span class="font-medium text-slate-200">{{ $item->createdBy->name }}</span></span>
            @endif
            @if ($isSuperAdmin && $item->assignedTo)
                <span>Assigned <span class="font-medium text-slate-200">{{ $item->assignedTo->name }}</span></span>
            @endif
        </div>
    </section>

    @if ($item->session->project)
        <section class="mt-6 rounded-[1.75rem] border border-cyan-300/10 bg-[linear-gradient(135deg,rgba(15,23,42,0.88),rgba(8,15,29,0.92))] p-6 shadow-[0_24px_60px_rgba(2,6,23,0.28)]">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-300/80">Project Status</p>
                    <h2 class="mt-3 text-2xl font-semibold text-white">{{ $item->session->project->name }}</h2>
                    <p class="mt-2 text-sm text-slate-300">
                        {{ $item->session->project->phase ?: (\App\Models\Project::statusLabels()[$item->session->project->status] ?? ucfirst($item->session->project->status)) }}
                    </p>
                    @if ($item->session->project->current_focus)
                        <p class="mt-4 text-sm leading-7 text-slate-300">{{ $item->session->project->current_focus }}</p>
                    @endif
                </div>
                <div class="min-w-[240px] rounded-2xl border border-white/10 bg-white/5 p-5">
                    <div class="flex items-center justify-between text-xs font-medium text-slate-400">
                        <span>Progress</span>
                        <span>{{ $item->session->project->progress_percent }}%</span>
                    </div>
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-800">
                        <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 via-sky-500 to-violet-500" style="width: {{ max(0, min(100, $item->session->project->progress_percent)) }}%"></div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[1.35fr,0.65fr]">
        <div class="space-y-6">
            @if ($item->comment)
                <section class="rounded-[1.5rem] border border-white/10 bg-[rgba(9,16,31,0.78)] p-5 shadow-[0_20px_50px_rgba(2,6,23,0.24)] backdrop-blur-xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Context</p>
                    <p class="mt-4 whitespace-pre-wrap text-sm leading-7 text-slate-200">{{ $item->comment }}</p>
                </section>
            @endif

            @if ($isSuperAdmin)
                <section class="rounded-[1.5rem] border border-violet-300/15 bg-violet-400/10 p-5 shadow-[0_20px_50px_rgba(2,6,23,0.18)] backdrop-blur-xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-200">Workflow Controls</p>
                    <form method="POST" action="{{ route('review.item.status', $item) }}" class="mt-4 flex flex-wrap gap-2">
                        @csrf
                        @method('PATCH')
                        @foreach (\App\Models\ReviewItem::statusLabels() as $value => $label)
                            <button
                                type="submit"
                                name="status"
                                value="{{ $value }}"
                                class="rounded-xl border px-3 py-2 text-xs font-semibold transition {{ $item->status === $value ? 'border-violet-300 bg-violet-500 text-white shadow-lg shadow-violet-950/20' : 'border-white/10 bg-slate-950/40 text-slate-200 hover:border-cyan-300/20 hover:text-white' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </form>
                </section>
            @endif

            @if ($item->screenshot)
                <section class="overflow-hidden rounded-[1.5rem] border border-white/10 bg-[rgba(9,16,31,0.78)] shadow-[0_20px_50px_rgba(2,6,23,0.24)] backdrop-blur-xl">
                    <div class="flex items-center justify-between border-b border-white/8 px-5 py-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Annotated Capture</p>
                            <p class="mt-1 text-sm text-slate-300">Saved screenshot with markup for this ticket.</p>
                        </div>
                    </div>
                    <div class="bg-slate-950/60 p-3">
                        <img src="{{ $item->screenshot }}"
                             alt="Annotated screenshot"
                             class="block w-full rounded-2xl border border-white/10"
                             loading="lazy">
                    </div>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            <section class="rounded-[1.5rem] border border-white/10 bg-[rgba(9,16,31,0.78)] shadow-[0_20px_50px_rgba(2,6,23,0.24)] backdrop-blur-xl">
                <div class="border-b border-white/8 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Conversation Thread</p>
                    <p class="mt-1 text-sm text-slate-300">{{ $item->comments->count() }} message{{ $item->comments->count() === 1 ? '' : 's' }}</p>
                </div>

                <div class="divide-y divide-white/6">
                    @forelse ($item->comments as $comment)
                        @php $isMe = $comment->user_id === auth()->id(); @endphp
                        <div class="px-5 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-semibold {{ $isMe ? 'text-cyan-200' : 'text-slate-200' }}">
                                    {{ $comment->user?->name ?? 'Unknown' }}
                                    @if ($isMe)
                                        <span class="font-normal text-cyan-400/80">(you)</span>
                                    @endif
                                </span>
                                <span class="text-[11px] text-slate-500">{{ $comment->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="mt-2 whitespace-pre-wrap text-sm leading-7 text-slate-300">{{ $comment->body }}</p>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-500">
                            No comments yet. Start the thread below.
                        </div>
                    @endforelse
                </div>

                <div class="border-t border-white/8 p-5">
                    <form method="POST" action="{{ route('review.item.comment', $item) }}">
                        @csrf
                        <textarea
                            name="body"
                            rows="4"
                            placeholder="Leave a note, ask a question, or post an update..."
                            class="w-full resize-none rounded-2xl border border-white/10 bg-slate-950/50 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500 focus:border-cyan-300/30 focus:outline-none focus:ring-2 focus:ring-cyan-400/10"
                            required
                        ></textarea>
                        @error('body')
                            <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                        @enderror
                        <button
                            type="submit"
                            class="mt-3 w-full rounded-2xl bg-gradient-to-r from-violet-600 to-cyan-500 py-3 text-sm font-semibold text-white shadow-lg shadow-violet-950/20 transition hover:scale-[1.01]"
                        >
                            Send Reply
                        </button>
                    </form>
                </div>
            </section>
        </aside>
    </div>

</x-review-layout>
