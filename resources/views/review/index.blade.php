<x-review-layout title="Feedback Center">

    <section class="review-hero relative overflow-hidden rounded-[2rem] p-8">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(34,211,238,0.12),transparent_28%),radial-gradient(circle_at_left,rgba(124,58,237,0.08),transparent_26%)]"></div>
        <div class="relative flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="review-kicker text-xs font-semibold uppercase tracking-[0.24em]">Review Workspace</p>
                <h1 class="mt-4 text-4xl font-semibold tracking-tight text-slate-950">Feedback Center</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-600">
                    Capture, review, and discuss feedback from one shared workspace without relying on separate project-management tooling.
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="review-muted-card rounded-2xl px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Open Sessions</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $sessions->where('status', 'open')->count() }}</p>
                </div>
                <div class="review-muted-card rounded-2xl px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Feedback Items</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $recentItems->count() }}</p>
                </div>
                <div class="review-muted-card rounded-2xl px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Access</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ auth()->user()?->isSuperAdmin() ? 'Internal Admin' : 'Client / Reviewer' }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="mt-8 grid gap-6 xl:grid-cols-[1.05fr,0.95fr]">
        <section class="space-y-5">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-950">Review Sessions</h2>
                <span class="text-xs uppercase tracking-[0.18em] text-slate-500">{{ $sessions->count() }} total</span>
            </div>

            @forelse ($sessions as $session)
                <a href="{{ route('review.session', $session) }}"
                   class="review-surface group block overflow-hidden rounded-[1.75rem] p-6 transition duration-200 hover:border-cyan-300/40 hover:bg-white">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-xl font-semibold tracking-tight text-slate-950">{{ $session->title }}</h3>
                                <span class="rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-cyan-700">
                                    {{ \App\Models\ReviewSession::statusLabels()[$session->status] ?? ucfirst($session->status) }}
                                </span>
                                @if ($projectsEnabled && $session->project?->name)
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-600">
                                        {{ $session->project->name }}
                                    </span>
                                @endif
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                <span>{{ $session->items_count }} item{{ $session->items_count !== 1 ? 's' : '' }}</span>
                                <span>Created {{ $session->created_at->diffForHumans() }}</span>
                                @if ($session->createdBy?->name)
                                    <span>By {{ $session->createdBy->name }}</span>
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
            @empty
                <div class="review-surface rounded-[1.75rem] border-dashed px-6 py-16 text-center text-slate-500">
                    <p class="text-base font-medium text-slate-900">No review sessions yet.</p>
                    <p class="mt-2 text-sm">Use review mode inside the app to create the first session and start capturing feedback.</p>
                </div>
            @endforelse
        </section>

        <section class="space-y-5">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-950">Recent Feedback</h2>
                <span class="text-xs uppercase tracking-[0.18em] text-slate-500">Latest items</span>
            </div>

            @forelse ($recentItems as $item)
                @php
                    $statusMap = [
                        'open' => ['border-rose-200 bg-rose-50 text-rose-700', 'Open'],
                        'in_progress' => ['border-amber-200 bg-amber-50 text-amber-700', 'In Progress'],
                        'fixed' => ['border-emerald-200 bg-emerald-50 text-emerald-700', 'Fixed'],
                        'approved' => ['border-cyan-200 bg-cyan-50 text-cyan-700', 'Approved'],
                        'rejected' => ['border-slate-200 bg-slate-100 text-slate-600', 'Rejected'],
                        'wont_fix' => ['border-slate-200 bg-slate-100 text-slate-600', "Won't Fix"],
                    ];
                    [$statusCss, $statusLabel] = $statusMap[$item->status] ?? ['border-slate-200 bg-slate-100 text-slate-600', ucfirst($item->status)];
                @endphp

                <a href="{{ route('review.item', $item) }}"
                   class="review-surface group block overflow-hidden rounded-[1.5rem] p-4 transition hover:border-cyan-300/40 hover:bg-white">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center">
                        <div class="h-24 w-full shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 md:w-36">
                            @if ($item->screenshot)
                                <img src="{{ $item->screenshot }}" class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.02]" alt="Screenshot">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-slate-400">No screenshot</div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $statusCss }}">{{ $statusLabel }}</span>
                                @if ($projectsEnabled && $item->session?->project?->name)
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-600">
                                        {{ $item->session->project->name }}
                                    </span>
                                @endif
                            </div>

                            <p class="mt-3 truncate text-base font-semibold text-slate-950">{{ $item->page_title ?: $item->page_url }}</p>

                            @if ($item->comment)
                                <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{{ $item->comment }}</p>
                            @endif

                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                <span>{{ $item->created_at->diffForHumans() }}</span>
                                <span>{{ $item->session?->title }}</span>
                            </div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="review-surface rounded-[1.75rem] border-dashed px-6 py-16 text-center text-slate-500">
                    <p class="text-base font-medium text-slate-900">No feedback items yet.</p>
                    <p class="mt-2 text-sm">New annotations and notes will appear here once reviewers start using review mode.</p>
                </div>
            @endforelse
        </section>
    </div>

</x-review-layout>
