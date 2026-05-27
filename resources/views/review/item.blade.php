<x-review-layout
    title="{{ $item->page_title ?: 'Review Item' }}"
    :session-id="$item->review_session_id"
    :project-id="$item->session?->project?->id"
>

    @php
        $statusMap = [
            'open'        => ['border-rose-200 bg-rose-50 text-rose-700',     'Open'],
            'in_progress' => ['border-amber-200 bg-amber-50 text-amber-700',  'In Progress'],
            'fixed'       => ['border-emerald-200 bg-emerald-50 text-emerald-700', 'Fixed'],
            'approved'    => ['border-cyan-200 bg-cyan-50 text-cyan-700',     'Approved'],
            'rejected'    => ['border-slate-200 bg-slate-100 text-slate-600', 'Rejected'],
            'wont_fix'    => ['border-slate-200 bg-slate-100 text-slate-600', "Won't Fix"],
        ];
        $typeMap = [
            'annotation' => ['border-violet-200 bg-violet-50 text-violet-700', 'Annotation'],
            'bug'        => ['border-rose-200 bg-rose-50 text-rose-700',       'Bug'],
            'suggestion' => ['border-sky-200 bg-sky-50 text-sky-700',          'Suggestion'],
            'question'   => ['border-amber-200 bg-amber-50 text-amber-700',    'Question'],
        ];
        $priorityMap = [
            'low'    => ['border-slate-200 bg-slate-100 text-slate-500',     'Low'],
            'normal' => ['border-slate-200 bg-slate-100 text-slate-600',     'Normal'],
            'high'   => ['border-rose-200 bg-rose-50 text-rose-700',         'High Priority'],
        ];

        [$statusCss, $statusLabel] = $statusMap[$item->status] ?? ['border-slate-200 bg-slate-100 text-slate-600', ucfirst($item->status)];
        [$typeCss, $typeLabel]     = $typeMap[$item->type]     ?? ['border-slate-200 bg-slate-100 text-slate-600', ucfirst($item->type ?? 'annotation')];
        [$priCss, $priLabel]       = $priorityMap[$item->priority ?? 'normal'] ?? ['border-slate-200 bg-slate-100 text-slate-600', 'Normal'];

        $session = $item->session;
        $project = $session?->project;
    @endphp

    {{-- Hero / breadcrumb --}}
    <section class="review-hero rounded-[1.75rem] p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                {{-- Breadcrumb --}}
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    @if ($project)
                        <a href="{{ route('review.project', $project) }}"
                           class="review-kicker font-medium uppercase tracking-[0.18em] transition hover:text-cyan-700">
                            {{ $project->name }}
                        </a>
                        <span class="text-slate-400">/</span>
                    @endif
                    @if ($session)
                        <a href="{{ route('review.session', $session) }}"
                           class="review-kicker font-medium uppercase tracking-[0.18em] transition hover:text-cyan-700">
                            {{ $session->title }}
                        </a>
                        <span class="text-slate-400">/</span>
                    @endif
                    <span class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Item #{{ $item->id }}</span>
                </div>

                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">
                    {{ $item->page_title ?: 'Untitled Annotation' }}
                </h1>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $typeCss }}">{{ $typeLabel }}</span>
                    <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $statusCss }}">{{ $statusLabel }}</span>
                    @if (($item->priority ?? 'normal') !== 'normal')
                        <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $priCss }}">{{ $priLabel }}</span>
                    @endif
                </div>

                <p class="mt-3 text-xs text-slate-500">
                    Captured by <span class="font-medium text-slate-700">{{ $item->createdBy?->name ?? 'Unknown' }}</span>
                    &middot; {{ $item->created_at->format('M j, Y \a\t g:i A') }}
                </p>
            </div>

            @if (auth()->user()?->isSuperAdmin())
                <div class="review-muted-card min-w-[220px] rounded-2xl p-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Update Status</p>
                    <form method="POST" action="{{ route('review.item.status', $item) }}" class="mt-2 flex items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <select name="status"
                            class="flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 focus:border-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-100">
                            @foreach (\App\Models\ReviewItem::statusLabels() as $val => $lbl)
                                <option value="{{ $val }}" @selected($item->status === $val)>{{ $lbl }}</option>
                            @endforeach
                        </select>
                        <button type="submit"
                            class="rounded-xl bg-gradient-to-r from-cyan-500 via-sky-500 to-violet-600 px-4 py-2 text-xs font-semibold text-white shadow-lg shadow-cyan-500/20 transition hover:scale-[1.02]">
                            Save
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.4fr,0.6fr]">

        {{-- Left: screenshot + comment --}}
        <div class="space-y-6">

            {{-- Screenshot --}}
            @if ($item->screenshot)
                <section class="review-surface overflow-hidden rounded-[1.65rem]">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-sm font-semibold text-slate-950">Screenshot</h2>
                    </div>
                    <div class="p-4">
                        <img src="{{ $item->screenshot }}"
                             alt="Annotation screenshot"
                             class="w-full rounded-2xl border border-slate-200 object-contain shadow-sm">
                    </div>
                </section>
            @else
                <section class="review-surface rounded-[1.65rem] border-dashed px-6 py-14 text-center text-slate-400">
                    <svg class="mx-auto mb-3 h-10 w-10 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-sm font-medium text-slate-500">No screenshot captured</p>
                </section>
            @endif

            {{-- Feedback comment --}}
            @if ($item->comment)
                <section class="review-surface rounded-[1.65rem] p-6">
                    <h2 class="text-sm font-semibold text-slate-950">Feedback</h2>
                    <div class="mt-3 whitespace-pre-wrap text-sm leading-7 text-slate-700">{{ $item->comment }}</div>
                </section>
            @endif

            {{-- Comment thread --}}
            <section class="review-surface rounded-[1.65rem] p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-950">Discussion</h2>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-600">
                        {{ $item->comments->count() }} {{ $item->comments->count() === 1 ? 'reply' : 'replies' }}
                    </span>
                </div>

                <form method="POST" action="{{ route('review.item.comment', $item) }}" class="mt-5">
                    @csrf
                    <label for="item-comment-body" class="sr-only">Add comment</label>
                    <textarea
                        id="item-comment-body"
                        name="body"
                        rows="3"
                        required
                        maxlength="2000"
                        placeholder="Add a reply, clarification, or resolution note…"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-100"
                    >{{ old('body') }}</textarea>
                    @error('body')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                    <button
                        type="submit"
                        class="mt-3 inline-flex items-center rounded-2xl bg-gradient-to-r from-cyan-500 via-sky-500 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-cyan-500/20 transition hover:scale-[1.01]"
                    >
                        Post Reply
                    </button>
                </form>

                @if ($item->comments->count() > 0)
                    <div class="mt-6 space-y-3">
                        @foreach ($item->comments->sortBy('created_at') as $comment)
                            <div class="review-muted-card rounded-2xl p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-slate-900">{{ $comment->user?->name ?? 'Unknown' }}</span>
                                    <span class="text-xs text-slate-500">{{ $comment->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600">{{ $comment->body }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        {{-- Right: metadata sidebar --}}
        <div class="space-y-6">

            {{-- Item details --}}
            <section class="review-surface rounded-[1.65rem] p-6">
                <h2 class="text-sm font-semibold text-slate-950">Details</h2>

                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Type</dt>
                        <dd class="mt-1">
                            <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $typeCss }}">{{ $typeLabel }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Status</dt>
                        <dd class="mt-1">
                            <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $statusCss }}">{{ $statusLabel }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Priority</dt>
                        <dd class="mt-1">
                            <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $priCss }}">{{ $priLabel }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Page URL</dt>
                        <dd class="mt-1 break-all text-xs text-slate-600">
                            <a href="{{ $item->page_url }}" target="_blank" rel="noopener"
                               class="underline decoration-slate-300 underline-offset-2 transition hover:text-cyan-700">
                                {{ $item->page_url }}
                            </a>
                        </dd>
                    </div>
                    @if ($item->createdBy)
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Captured by</dt>
                            <dd class="mt-1 font-medium text-slate-800">{{ $item->createdBy->name }}</dd>
                        </div>
                    @endif
                    @if ($item->assignedTo)
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Assigned to</dt>
                            <dd class="mt-1 font-medium text-slate-800">{{ $item->assignedTo->name }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Captured</dt>
                        <dd class="mt-1 text-slate-600">{{ $item->created_at->format('M j, Y \a\t g:i A') }}</dd>
                    </div>
                    @if ($item->updated_at->gt($item->created_at->addMinutes(1)))
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Last updated</dt>
                            <dd class="mt-1 text-slate-600">{{ $item->updated_at->diffForHumans() }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- Session / Project context --}}
            @if ($session)
                <section class="review-surface rounded-[1.65rem] p-6">
                    <h2 class="text-sm font-semibold text-slate-950">Session</h2>
                    <div class="mt-4 space-y-3">
                        <a href="{{ route('review.session', $session) }}"
                           class="review-muted-card group flex items-center justify-between rounded-2xl p-3 transition hover:border-cyan-300/40 hover:bg-white">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $session->title }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ \App\Models\ReviewSession::statusLabels()[$session->status] ?? ucfirst($session->status) }}
                                </p>
                            </div>
                            <svg class="h-4 w-4 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-cyan-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>

                        @if ($project)
                            <a href="{{ route('review.project', $project) }}"
                               class="review-muted-card group flex items-center justify-between rounded-2xl p-3 transition hover:border-cyan-300/40 hover:bg-white">
                                <div>
                                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Project</p>
                                    <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ $project->name }}</p>
                                </div>
                                <svg class="h-4 w-4 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-cyan-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        @endif
                    </div>
                </section>
            @endif
        </div>
    </div>

</x-review-layout>
