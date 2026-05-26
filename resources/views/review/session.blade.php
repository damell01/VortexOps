<x-review-layout
    title="{{ $session->title }}"
    :session-id="$session->id"
    :project-id="$session->project?->id"
    :breadcrumb="$session->project ? '<a href=\''.route('review.project', $session->project).'\' class=\'text-sm font-medium text-slate-200\'>'.$session->project->name.'</a><span class=\'mx-2 text-slate-500\'>/</span><span class=\'text-sm font-medium text-slate-400\'>'.$session->title.'</span>' : '<span class=\'text-sm font-medium text-slate-400\'>'.$session->title.'</span>'"
>

    <section class="rounded-[1.75rem] border border-white/10 bg-[rgba(9,16,31,0.8)] p-6 shadow-[0_24px_60px_rgba(2,6,23,0.32)] backdrop-blur-xl">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <a href="{{ $session->project ? route('review.project', $session->project) : route('review.index') }}" class="inline-flex items-center gap-2 text-xs font-medium uppercase tracking-[0.18em] text-cyan-300/80 transition hover:text-cyan-200">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    {{ $session->project ? $session->project->name : 'Project Hub' }}
                </a>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white">{{ $session->title }}</h1>
                <p class="mt-2 text-sm text-slate-300">{{ $items->count() }} item{{ $items->count() !== 1 ? 's' : '' }} captured in this review session.</p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 px-5 py-4">
                <p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Session State</p>
                <p class="mt-2 text-lg font-semibold text-white">{{ \App\Models\ReviewSession::statusLabels()[$session->status] ?? ucfirst($session->status) }}</p>
            </div>
        </div>
    </section>

    @if ($session->project)
        <section class="mt-6 rounded-[1.75rem] border border-cyan-300/10 bg-[linear-gradient(135deg,rgba(15,23,42,0.88),rgba(8,15,29,0.92))] p-6 shadow-[0_24px_60px_rgba(2,6,23,0.28)]">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-300/80">Project Status</p>
                    <h2 class="mt-3 text-2xl font-semibold text-white">{{ $session->project->name }}</h2>
                    <p class="mt-2 text-sm text-slate-300">
                        {{ $session->project->phase ?: (\App\Models\Project::statusLabels()[$session->project->status] ?? ucfirst($session->project->status)) }}
                    </p>
                    @if ($session->project->current_focus)
                        <p class="mt-4 text-sm leading-7 text-slate-300">{{ $session->project->current_focus }}</p>
                    @endif
                </div>

                <div class="min-w-[240px] rounded-2xl border border-white/10 bg-white/5 p-5">
                    <div class="flex items-center justify-between text-xs font-medium text-slate-400">
                        <span>Progress</span>
                        <span>{{ $session->project->progress_percent }}%</span>
                    </div>
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-800">
                        <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 via-sky-500 to-violet-500" style="width: {{ max(0, min(100, $session->project->progress_percent)) }}%"></div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($items->isEmpty())
        <div class="mt-6 rounded-[1.75rem] border border-dashed border-white/15 bg-[rgba(9,16,31,0.78)] py-20 text-center text-slate-400 backdrop-blur-xl">
            <p class="text-base font-medium text-slate-200">No items in this session yet.</p>
            <p class="mt-2 text-sm">Use “Leave Feedback” above to annotate any page.</p>
        </div>
    @else
        <div class="mt-6 grid gap-4">
            @foreach ($items as $item)
                @php
                    $statusMap = [
                        'open' => ['border-rose-300/20 bg-rose-400/10 text-rose-200', 'Open'],
                        'in_progress' => ['border-amber-300/20 bg-amber-400/10 text-amber-200', 'In Progress'],
                        'fixed' => ['border-emerald-300/20 bg-emerald-400/10 text-emerald-200', 'Fixed'],
                        'approved' => ['border-cyan-300/20 bg-cyan-400/10 text-cyan-200', 'Approved'],
                        'rejected' => ['border-slate-300/20 bg-slate-400/10 text-slate-300', 'Rejected'],
                        'wont_fix' => ['border-slate-300/20 bg-slate-400/10 text-slate-300', "Won't Fix"],
                    ];
                    $typeMap = [
                        'annotation' => ['bg-violet-400/10 text-violet-200 border-violet-300/20', 'Annotation'],
                        'bug' => ['bg-rose-400/10 text-rose-200 border-rose-300/20', 'Bug'],
                        'suggestion' => ['bg-sky-400/10 text-sky-200 border-sky-300/20', 'Suggestion'],
                        'question' => ['bg-amber-400/10 text-amber-200 border-amber-300/20', 'Question'],
                    ];
                    [$statusCss, $statusLabel] = $statusMap[$item->status] ?? ['border-slate-300/20 bg-slate-400/10 text-slate-300', ucfirst($item->status)];
                    [$typeCss, $typeLabel] = $typeMap[$item->type] ?? ['bg-slate-400/10 text-slate-300 border-slate-300/20', ucfirst($item->type)];
                @endphp

                <a href="{{ route('review.item', $item) }}"
                   class="group block overflow-hidden rounded-[1.5rem] border border-white/10 bg-[rgba(9,16,31,0.78)] p-4 shadow-[0_20px_50px_rgba(2,6,23,0.24)] backdrop-blur-xl transition hover:border-cyan-300/20 hover:bg-[rgba(11,19,37,0.92)]">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center">
                        <div class="h-28 w-full shrink-0 overflow-hidden rounded-2xl border border-white/10 bg-slate-900 md:h-24 md:w-40">
                            @if ($item->screenshot)
                                <img src="{{ $item->screenshot }}" class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.02]" alt="Screenshot">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-slate-500">
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

                            <p class="mt-3 truncate text-base font-semibold text-white">
                                {{ $item->page_title ?: $item->page_url }}
                            </p>

                            @if ($item->comment)
                                <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-300">{{ $item->comment }}</p>
                            @endif

                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-400">
                                <span>{{ $item->created_at->diffForHumans() }}</span>
                                @if ($item->comments->count() > 0)
                                    <span>{{ $item->comments->count() }} {{ $item->comments->count() === 1 ? 'reply' : 'replies' }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="hidden text-slate-500 md:block">
                            <svg class="h-5 w-5 transition group-hover:translate-x-0.5 group-hover:text-cyan-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

</x-review-layout>
