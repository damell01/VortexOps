<x-review-layout title="{{ $project->name }}" :project-id="$project->id">

    <section class="review-hero rounded-[1.9rem] p-7">
        <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-4xl">
                <a href="{{ route('review.index') }}" class="review-kicker inline-flex items-center gap-2 text-xs font-medium uppercase tracking-[0.18em] transition hover:text-cyan-700">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Project Hub
                </a>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <h1 class="text-4xl font-semibold tracking-tight text-slate-950">{{ $project->name }}</h1>
                    <span class="rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-cyan-700">
                        {{ $project->phase ?: (\App\Models\Project::statusLabels()[$project->status] ?? ucfirst($project->status)) }}
                    </span>
                </div>

                @if ($project->summary)
                    <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-600">{{ $project->summary }}</p>
                @endif
            </div>

            <div class="grid min-w-[320px] gap-3 sm:grid-cols-2 xl:w-[390px]">
                <div class="review-muted-card rounded-2xl p-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Progress</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $project->progress_percent }}%</p>
                    @if ($project->launch_date)
                        <p class="mt-2 text-xs text-slate-500">Launch ETA {{ $project->launch_date->format('F j, Y') }}</p>
                    @endif
                </div>
                <div class="review-muted-card rounded-2xl p-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Workspace</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $reviewsEnabled ? 'Status + Feedback' : 'Status Only' }}</p>
                    <p class="mt-2 text-xs text-slate-500">{{ $project->manager?->name ? 'PM ' . $project->manager->name : 'PM not assigned yet' }}</p>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <div class="mb-2 flex items-center justify-between text-xs font-medium text-slate-500">
                <span>Delivery Progress</span>
                <span>{{ $project->progress_percent }}%</span>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-slate-200">
                <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 via-sky-500 to-violet-500" style="width: {{ max(0, min(100, $project->progress_percent)) }}%"></div>
            </div>
        </div>
    </section>

    <div class="mt-7 grid gap-6 xl:grid-cols-[1.15fr,0.85fr]">
        <div class="space-y-6">
            <section class="review-surface rounded-[1.65rem] p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-950">Overview</h2>
                    <span class="rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-cyan-700">Live</span>
                </div>

                <div class="mt-5 grid gap-4 {{ $reviewsEnabled ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }}">
                    @if ($reviewsEnabled)
                        <div class="review-muted-card rounded-2xl p-4">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-violet-700/80">Open Issues</p>
                            <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $project->open_review_items_count }}</p>
                        </div>
                    @endif
                    <div class="review-muted-card rounded-2xl p-4">
                        <p class="text-[11px] uppercase tracking-[0.18em] text-amber-700/80">Pending Approvals</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $project->pending_approvals_count }}</p>
                    </div>
                    @if ($reviewsEnabled)
                        <div class="review-muted-card rounded-2xl p-4">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-emerald-700/80">Resolved</p>
                            <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $project->resolved_review_items_count }}</p>
                        </div>
                    @endif
                    @unless($reviewsEnabled)
                        <div class="review-muted-card rounded-2xl p-4">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-cyan-700/80">Client Lead</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $project->owner?->name ?: 'Not set' }}</p>
                        </div>
                    @endunless
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div class="review-muted-card rounded-2xl p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Current Focus</p>
                        <div class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $project->current_focus ?: 'No active sprint summary yet.' }}</div>
                    </div>
                    <div class="review-muted-card rounded-2xl p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Needed From You</p>
                        <div class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $project->client_needs ?: 'Nothing blocking progress right now.' }}</div>
                    </div>
                </div>
            </section>

            <section class="review-surface rounded-[1.65rem] p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Milestones</h2>
                        <p class="mt-1 text-sm text-slate-500">Progress stays tied to the rollout roadmap and delivery phases.</p>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-600">{{ $project->milestones->count() }} total</span>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($project->milestones as $milestone)
                        <div class="review-muted-card rounded-2xl p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-slate-950">{{ $milestone->title }}</p>
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ match($milestone->status) { 'completed', 'approved' => 'border border-emerald-200 bg-emerald-50 text-emerald-700', 'in_progress' => 'border border-sky-200 bg-sky-50 text-sky-700', 'blocked' => 'border border-rose-200 bg-rose-50 text-rose-700', default => 'border border-slate-200 bg-slate-100 text-slate-600' } }}">
                                            {{ \App\Models\ProjectMilestone::statusLabels()[$milestone->status] ?? ucfirst($milestone->status) }}
                                        </span>
                                    </div>
                                    @if ($milestone->description)
                                        <p class="mt-2 text-sm leading-7 text-slate-600">{{ $milestone->description }}</p>
                                    @endif
                                </div>
                                <div class="text-right text-xs text-slate-500">
                                    <p>{{ $milestone->due_date?->format('M j, Y') ?: 'No due date' }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No milestones have been added yet.</p>
                    @endforelse
                </div>
            </section>

            @if ($reviewsEnabled)
                <section class="review-surface rounded-[1.65rem] p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Feedback Sessions</h2>
                            <p class="mt-1 text-sm text-slate-500">Each session groups related client review rounds and annotations.</p>
                        </div>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-600">{{ $project->reviewSessions->count() }} sessions</span>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse ($project->reviewSessions as $session)
                            <a href="{{ route('review.session', $session) }}" class="review-muted-card group block rounded-2xl p-4 transition hover:border-cyan-300/40 hover:bg-white">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-950">{{ $session->title }}</p>
                                        <p class="mt-2 text-xs text-slate-500">{{ $session->items_count }} item{{ $session->items_count === 1 ? '' : 's' }} - {{ $session->created_at->format('M j, Y g:i A') }}</p>
                                    </div>
                                    <span class="rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-700">
                                        {{ \App\Models\ReviewSession::statusLabels()[$session->status] ?? ucfirst($session->status) }}
                                    </span>
                                </div>
                            </a>
                        @empty
                            <p class="text-sm text-slate-500">No feedback sessions yet. Review mode will start filling this in.</p>
                        @endforelse
                    </div>
                </section>

                <section class="review-surface rounded-[1.65rem] p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Latest Annotations</h2>
                            <p class="mt-1 text-sm text-slate-500">Recent screenshot-backed feedback captured across the workspace.</p>
                        </div>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-600">{{ $project->reviewItems->count() }} shown</span>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse ($project->reviewItems as $item)
                            <a href="{{ route('review.item', $item) }}" class="review-muted-card group block rounded-2xl p-4 transition hover:border-cyan-300/40 hover:bg-white">
                                <div class="flex items-start gap-4">
                                    <div class="h-20 w-28 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                                        @if ($item->screenshot)
                                            <img src="{{ $item->screenshot }}" alt="Annotation screenshot" class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.02]">
                                        @else
                                            <div class="flex h-full items-center justify-center text-xs text-slate-400">No shot</div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate font-semibold text-slate-950">{{ $item->page_title ?: $item->page_url }}</p>
                                                @if ($item->comment)
                                                    <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{{ $item->comment }}</p>
                                                @endif
                                            </div>
                                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ in_array($item->status, ['open', 'in_progress']) ? 'border border-amber-200 bg-amber-50 text-amber-700' : 'border border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                                                {{ \App\Models\ReviewItem::statusLabels()[$item->status] ?? ucfirst($item->status) }}
                                            </span>
                                        </div>
                                        <p class="mt-2 text-xs text-slate-500">
                                            {{ $item->session?->title ?: 'Session' }} - {{ $item->createdBy?->name ?: 'Unknown' }} - {{ $item->created_at->format('M j, Y g:i A') }}
                                        </p>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <p class="text-sm text-slate-500">No annotations have been captured for this workspace yet.</p>
                        @endforelse
                    </div>
                </section>
            @endif
        </div>

        <div class="space-y-6">
            <section class="review-surface rounded-[1.65rem] p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Pending Approvals</h2>
                        <p class="mt-1 text-sm text-slate-500">Visible signoffs and launch gates the team is waiting on.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($project->approvals as $approval)
                        <div class="review-muted-card rounded-2xl p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-950">{{ $approval->label }}</p>
                                    @if ($approval->description)
                                        <p class="mt-2 text-sm leading-7 text-slate-600">{{ $approval->description }}</p>
                                    @endif
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $approval->status === 'pending' ? 'border border-amber-200 bg-amber-50 text-amber-700' : ($approval->status === 'approved' ? 'border border-emerald-200 bg-emerald-50 text-emerald-700' : 'border border-slate-200 bg-slate-100 text-slate-600') }}">
                                    {{ \App\Models\ProjectApproval::statusLabels()[$approval->status] ?? ucfirst($approval->status) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No approvals are waiting right now.</p>
                    @endforelse
                </div>
            </section>

            <section class="review-surface rounded-[1.65rem] p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Project Conversation</h2>
                        <p class="mt-1 text-sm text-slate-500">Rollout notes, blockers, client questions, and delivery context.</p>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-600">{{ $project->comments->count() }} recent</span>
                </div>

                <form method="POST" action="{{ route('review.project.comment', $project) }}" class="mt-5">
                    @csrf
                    <label for="project-comment-body" class="sr-only">Add project comment</label>
                    <textarea
                        id="project-comment-body"
                        name="body"
                        rows="4"
                        required
                        maxlength="5000"
                        placeholder="Post a project note, client question, blocker, or update for the team..."
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-100"
                    >{{ old('body') }}</textarea>
                    @error('body')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                    <button
                        type="submit"
                        class="mt-3 inline-flex items-center rounded-2xl bg-gradient-to-r from-cyan-500 via-sky-500 to-violet-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-cyan-500/20 transition hover:scale-[1.01]"
                    >
                        Post Update
                    </button>
                </form>

                <div class="mt-6 space-y-3">
                    @forelse ($project->comments as $comment)
                        <div class="review-muted-card rounded-2xl p-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm font-semibold text-slate-900">{{ $comment->user?->name ?? 'Unknown' }}</span>
                                <span class="text-xs text-slate-500">{{ $comment->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="mt-3 whitespace-pre-wrap text-sm leading-7 text-slate-600">{{ $comment->body }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No project comments yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

</x-review-layout>
