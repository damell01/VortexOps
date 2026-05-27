<x-review-layout
    title="{{ $session->title }}"
    :session-id="$session->id"
    :project-id="$session->project?->id"
    :breadcrumb="$session->project ? '<a href=\''.route('review.project', $session->project).'\' class=\'text-sm font-medium text-slate-800\'>'.$session->project->name.'</a><span class=\'mx-2 text-slate-400\'>/</span><span class=\'text-sm font-medium text-slate-500\'>'.$session->title.'</span>' : '<span class=\'text-sm font-medium text-slate-500\'>'.$session->title.'</span>'"
>

    <section class="review-hero rounded-[1.75rem] p-6">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <a href="{{ $session->project ? route('review.project', $session->project) : route('review.index') }}" class="review-kicker inline-flex items-center gap-2 text-xs font-medium uppercase tracking-[0.18em] transition hover:text-cyan-700">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    {{ $session->project ? $session->project->name : 'Project Hub' }}
                </a>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $session->title }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ $items->count() }} item{{ $items->count() !== 1 ? 's' : '' }} captured in this review session.</p>
            </div>

            <div class="review-muted-card rounded-2xl px-5 py-4">
                <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Session State</p>
                <p class="mt-2 text-lg font-semibold text-slate-950">{{ \App\Models\ReviewSession::statusLabels()[$session->status] ?? ucfirst($session->status) }}</p>
            </div>
        </div>
    </section>

    @if ($session->project)
        <section class="review-surface mt-6 rounded-[1.75rem] p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="review-kicker text-xs font-semibold uppercase tracking-[0.22em]">Project Status</p>
                    <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ $session->project->name }}</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ $session->project->phase ?: (\App\Models\Project::statusLabels()[$session->project->status] ?? ucfirst($session->project->status)) }}
                    </p>
                    @if ($session->project->current_focus)
                        <p class="mt-4 text-sm leading-7 text-slate-600">{{ $session->project->current_focus }}</p>
                    @endif
                </div>

                <div class="review-muted-card min-w-[240px] rounded-2xl p-5">
                    <div class="flex items-center justify-between text-xs font-medium text-slate-500">
                        <span>Progress</span>
                        <span>{{ $session->project->progress_percent }}%</span>
                    </div>
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 via-sky-500 to-violet-500" style="width: {{ max(0, min(100, $session->project->progress_percent)) }}%"></div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($items->isEmpty())
        <div class="review-surface mt-6 rounded-[1.75rem] border-dashed py-20 text-center text-slate-500">
            <p class="text-base font-medium text-slate-900">No items in this session yet.</p>
            <p class="mt-2 text-sm">Use Leave Feedback above to annotate any page.</p>
        </div>
    @else
        <div class="mt-6 grid gap-4">
            @foreach ($items as $item)
                @php
                    $statusMap = [
                        'open' => ['border-rose-200 bg-rose-50 text-rose-700', 'Open'],
                        'in_progress' => ['border-amber-200 bg-amber-50 text-amber-700', 'In Progress'],
                        'fixed' => ['border-emerald-200 bg-emerald-50 text-emerald-700', 'Fixed'],
                        'approved' => ['border-cyan-200 bg-cyan-50 text-cyan-700', 'Approved'],
                        'rejected' => ['border-slate-200 bg-slate-100 text-slate-600', 'Rejected'],
                        'wont_fix' => ['border-slate-200 bg-slate-100 text-slate-600', "Won't Fix"],
                    ];
                    $typeMap = [
                        'annotation' => ['bg-violet-50 text-violet-700 border-violet-200', 'Annotation'],
                        'bug' => ['bg-rose-50 text-rose-700 border-rose-200', 'Bug'],
                        'suggestion' => ['bg-sky-50 text-sky-700 border-sky-200', 'Suggestion'],
                        'question' => ['bg-amber-50 text-amber-700 border-amber-200', 'Question'],
                    ];
                    [$statusCss, $statusLabel] = $statusMap[$item->status] ?? ['border-slate-200 bg-slate-100 text-slate-600', ucfirst($item->status)];
                    [$typeCss, $typeLabel] = $typeMap[$item->type] ?? ['bg-slate-100 text-slate-600 border-slate-200', ucfirst($item->type)];
                @endphp

                <a href="{{ route('review.item', $item) }}"
                   class="review-surface group block overflow-hidden rounded-[1.5rem] p-4 transition hover:border-cyan-300/40 hover:bg-white">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center">
                        <div class="h-28 w-full shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 md:h-24 md:w-40">
                            @if ($item->screenshot)
                                <img src="{{ $item->screenshot }}" class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.02]" alt="Screenshot">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-slate-400">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $typeCss }}">{{ $typeLabel }}</span>
                                <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $statusCss }}">{{ $statusLabel }}</span>
                            </div>

                            <p class="mt-3 truncate text-base font-semibold text-slate-950">
                                {{ $item->page_title ?: $item->page_url }}
                            </p>

                            @if ($item->comment)
                                <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{{ $item->comment }}</p>
                            @endif

                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                <span>{{ $item->created_at->diffForHumans() }}</span>
                                @if ($item->comments->count() > 0)
                                    <span>{{ $item->comments->count() }} {{ $item->comments->count() === 1 ? 'reply' : 'replies' }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="hidden text-slate-400 md:block">
                            <svg class="h-5 w-5 transition group-hover:translate-x-0.5 group-hover:text-cyan-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

</x-review-layout>
