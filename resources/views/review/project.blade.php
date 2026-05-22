<x-review-layout title="{{ $project->name }}" :project-id="$project->id">

    <section class="rounded-[1.9rem] border border-white/10 bg-[linear-gradient(135deg,rgba(8,15,29,0.92),rgba(13,22,39,0.86),rgba(49,46,129,0.86))] p-7 shadow-[0_30px_80px_rgba(2,6,23,0.42)] backdrop-blur-xl">
        <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-4xl">
                <a href="{{ route('review.index') }}" class="inline-flex items-center gap-2 text-xs font-medium uppercase tracking-[0.18em] text-cyan-300/80 transition hover:text-cyan-200">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Project Hub
                </a>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <h1 class="text-4xl font-semibold tracking-tight text-white">{{ $project->name }}</h1>
                    <span class="rounded-full border border-violet-300/20 bg-violet-400/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-violet-200">
                        {{ $project->phase ?: (\App\Models\Project::statusLabels()[$project->status] ?? ucfirst($project->status)) }}
                    </span>
                </div>

                @if ($project->summary)
                    <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-300">{{ $project->summary }}</p>
                @endif
            </div>

            <div class="grid min-w-[320px] gap-3 sm:grid-cols-2 xl:w-[390px]">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Progress</p>
                    <p class="mt-2 text-3xl font-semibold text-white">{{ $project->progress_percent }}%</p>
                    @if ($project->launch_date)
                        <p class="mt-2 text-xs text-slate-400">Launch ETA {{ $project->launch_date->format('F j, Y') }}</p>
                    @endif
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Workspace</p>
                    <p class="mt-2 text-sm font-semibold text-white">{{ $reviewsEnabled ? 'Status + Feedback' : 'Status Only' }}</p>
                    <p class="mt-2 text-xs text-slate-400">{{ $project->manager?->name ? 'PM ' . $project->manager->name : 'PM not assigned yet' }}</p>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <div class="mb-2 flex items-center justify-between text-xs font-medium text-slate-400">
                <span>Delivery Progress</span>
                <span>{{ $project->progress_percent }}%</span>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-slate-800">
                <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 via-sky-500 to-violet-500" style="width: {{ max(0, min(100, $project->progress_percent)) }}%"></div>
            </div>
        </div>
    </section>

    <div class="mt-7 grid gap-6 xl:grid-cols-[1.15fr,0.85fr]">
        <div class="space-y-6">
            <section class="rounded-[1.65rem] border border-white/10 bg-[rgba(9,16,31,0.78)] p-6 shadow-[0_20px_50px_rgba(2,6,23,0.28)] backdrop-blur-xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-white">Overview</h2>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">Live</span>
                </div>

                <div class="mt-5 grid gap-4 {{ $reviewsEnabled ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }}">
                    @if ($reviewsEnabled)
                        <div class="rounded-2xl border border-violet-300/15 bg-violet-400/10 p-4">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-violet-200/80">Open Issues</p>
                            <p class="mt-2 text-3xl font-semibold text-white">{{ $project->open_review_items_count }}</p>
                        </div>
                    @endif
                    <div class="rounded-2xl border border-amber-300/15 bg-amber-400/10 p-4">
                        <p class="text-[11px] uppercase tracking-[0.18em] text-amber-200/80">Pending Approvals</p>
                        <p class="mt-2 text-3xl font-semibold text-white">{{ $project->pending_approvals_count }}</p>
                    </div>
                    @if ($reviewsEnabled)
                        <div class="rounded-2xl border border-emerald-300/15 bg-emerald-400/10 p-4">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-emerald-200/80">Resolved</p>
                            <p class="mt-2 text-3xl font-semibold text-white">{{ $project->resolved_review_items_count }}</p>
                        </div>
                    @endif
                    @unless($reviewsEnabled)
                        <div class="rounded-2xl border border-cyan-300/15 bg-cyan-400/10 p-4">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-cyan-200/80">Client Lead</p>
                            <p class="mt-2 text-sm font-semibold text-white">{{ $project->owner?->name ?: 'Not set' }}</p>
                        </div>
                    @endunless
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Current Focus</p>
                        <div class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-200">{{ $project->current_focus ?: 'No active sprint summary yet.' }}</div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Needed From You</p>
                        <div class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-200">{{ $project->client_needs ?: 'Nothing blocking progress right now.' }}</div>
                    </div>
                </div>
            </section>

            <section class="rounded-[1.65rem] border border-white/10 bg-[rgba(9,16,31,0.78)] p-6 shadow-[0_20px_50px_rgba(2,6,23,0.28)] backdrop-blur-xl">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Milestones</h2>
                        <p class="mt-1 text-sm text-slate-400">Progress stays tied to the rollout roadmap and delivery phases.</p>
                    </div>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">{{ $project->milestones->count() }} total</span>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($project->milestones as $milestone)
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-white">{{ $milestone->title }}</p>
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ match($milestone->status) { 'completed', 'approved' => 'border border-emerald-300/20 bg-emerald-400/10 text-emerald-200', 'in_progress' => 'border border-sky-300/20 bg-sky-400/10 text-sky-200', 'blocked' => 'border border-rose-300/20 bg-rose-400/10 text-rose-200', default => 'border border-slate-300/20 bg-slate-400/10 text-slate-300' } }}">
                                            {{ \App\Models\ProjectMilestone::statusLabels()[$milestone->status] ?? ucfirst($milestone->status) }}
                                        </span>
                                    </div>
                                    @if ($milestone->description)
                                        <p class="mt-2 text-sm leading-7 text-slate-300">{{ $milestone->description }}</p>
                                    @endif
                                </div>
                                <div class="text-right text-xs text-slate-400">
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
                <section class="rounded-[1.65rem] border border-white/10 bg-[rgba(9,16,31,0.78)] p-6 shadow-[0_20px_50px_rgba(2,6,23,0.28)] backdrop-blur-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-white">Feedback Sessions</h2>
                            <p class="mt-1 text-sm text-slate-400">Each session groups related client review rounds and annotations.</p>
                        </div>
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">{{ $project->reviewSessions->count() }} sessions</span>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse ($project->reviewSessions as $session)
                            <a href="{{ route('review.session', $session) }}" class="group block rounded-2xl border border-white/10 bg-white/5 p-4 transition hover:border-cyan-300/20 hover:bg-white/8">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-white">{{ $session->title }}</p>
                                        <p class="mt-2 text-xs text-slate-400">{{ $session->items_count }} item{{ $session->items_count === 1 ? '' : 's' }} · {{ $session->created_at->format('M j, Y g:i A') }}</p>
                                    </div>
                                    <span class="rounded-full border border-white/10 bg-slate-950/40 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-200">
                                        {{ \App\Models\ReviewSession::statusLabels()[$session->status] ?? ucfirst($session->status) }}
                                    </span>
                                </div>
                            </a>
                        @empty
                            <p class="text-sm text-slate-500">No feedback sessions yet. Review Mode will start filling this in.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-[1.65rem] border border-white/10 bg-[rgba(9,16,31,0.78)] p-6 shadow-[0_20px_50px_rgba(2,6,23,0.28)] backdrop-blur-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-white">Latest Annotations</h2>
                            <p class="mt-1 text-sm text-slate-400">Recent screenshot-backed feedback captured across the workspace.</p>
                        </div>
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">{{ $project->reviewItems->count() }} shown</span>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse ($project->reviewItems as $item)
                            <a href="{{ route('review.item', $item) }}" class="group block rounded-2xl border border-white/10 bg-white/5 p-4 transition hover:border-cyan-300/20 hover:bg-white/8">
                                <div class="flex items-start gap-4">
                                    <div class="h-20 w-28 shrink-0 overflow-hidden rounded-xl border border-white/10 bg-slate-900">
                                        @if ($item->screenshot)
                                            <img src="{{ $item->screenshot }}" alt="Annotation screenshot" class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.02]">
                                        @else
                                            <div class="flex h-full items-center justify-center text-xs text-slate-500">No shot</div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate font-semibold text-white">{{ $item->page_title ?: $item->page_url }}</p>
                                                @if ($item->comment)
                                                    <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-300">{{ $item->comment }}</p>
                                                @endif
                                            </div>
                                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ in_array($item->status, ['open', 'in_progress']) ? 'border border-amber-300/20 bg-amber-400/10 text-amber-200' : 'border border-emerald-300/20 bg-emerald-400/10 text-emerald-200' }}">
                                                {{ \App\Models\ReviewItem::statusLabels()[$item->status] ?? ucfirst($item->status) }}
                                            </span>
                                        </div>
                                        <p class="mt-2 text-xs text-slate-400">
                                            {{ $item->session?->title ?: 'Session' }} · {{ $item->createdBy?->name ?: 'Unknown' }} · {{ $item->created_at->format('M j, Y g:i A') }}
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
            <section class="rounded-[1.65rem] border border-white/10 bg-[rgba(9,16,31,0.78)] p-6 shadow-[0_20px_50px_rgba(2,6,23,0.28)] backdrop-blur-xl">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Pending Approvals</h2>
                        <p class="mt-1 text-sm text-slate-400">Visible signoffs and launch gates the team is waiting on.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($project->approvals as $approval)
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-white">{{ $approval->label }}</p>
                                    @if ($approval->description)
                                        <p class="mt-2 text-sm leading-7 text-slate-300">{{ $approval->description }}</p>
                                    @endif
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $approval->status === 'pending' ? 'border border-amber-300/20 bg-amber-400/10 text-amber-200' : ($approval->status === 'approved' ? 'border border-emerald-300/20 bg-emerald-400/10 text-emerald-200' : 'border border-slate-300/20 bg-slate-400/10 text-slate-300') }}">
                                    {{ \App\Models\ProjectApproval::statusLabels()[$approval->status] ?? ucfirst($approval->status) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No approvals are waiting right now.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-[1.65rem] border border-white/10 bg-[rgba(9,16,31,0.78)] p-6 shadow-[0_20px_50px_rgba(2,6,23,0.28)] backdrop-blur-xl">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Project Conversation</h2>
                        <p class="mt-1 text-sm text-slate-400">Rollout notes, blockers, client questions, and delivery context.</p>
                    </div>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">{{ $project->comments->count() }} recent</span>
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
                        class="w-full rounded-2xl border border-white/10 bg-slate-950/40 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500 focus:border-cyan-300/30 focus:outline-none focus:ring-2 focus:ring-cyan-400/10"
                    >{{ old('body') }}</textarea>
                    @error('body')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                    <div class="mt-3 flex items-center justify-between gap-3">
                        <p class="text-xs text-slate-500">Comments are visible to everyone who can access this workspace.</p>
                        <button type="submit" class="inline-flex items-center rounded-2xl bg-gradient-to-r from-violet-600 to-cyan-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-violet-950/20 transition hover:scale-[1.01]">
                            Post Comment
                        </button>
                    </div>
                </form>

                <div class="mt-6 space-y-4">
                    @forelse ($project->comments as $comment)
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-white">{{ $comment->user?->name ?: 'Unknown user' }}</p>
                                <p class="text-xs text-slate-500">{{ $comment->created_at->format('M j, Y g:i A') }}</p>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm leading-7 text-slate-300">{{ $comment->body }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No project comments yet. Use this thread for client-facing rollout discussion and implementation notes.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-[1.65rem] border border-white/10 bg-[rgba(9,16,31,0.78)] p-6 shadow-[0_20px_50px_rgba(2,6,23,0.28)] backdrop-blur-xl">
                <div>
                    <h2 class="text-lg font-semibold text-white">Activity Feed</h2>
                    <p class="mt-1 text-sm text-slate-400">Official progress log for milestone movement, completed work, and next steps.</p>
                </div>

                <div class="mt-5 space-y-4">
                    @forelse ($project->statusUpdates as $update)
                        <div class="border-l-2 border-cyan-400/30 pl-4">
                            <p class="text-sm font-semibold text-white">{{ $update->title }}</p>
                            @if ($update->body)
                                <p class="mt-2 text-sm leading-7 text-slate-300">{{ $update->body }}</p>
                            @endif
                            <p class="mt-2 text-xs text-slate-500">
                                {{ \App\Models\ProjectStatusUpdate::statusLabels()[$update->status] ?? ucfirst($update->status) }}
                                · {{ $update->created_at->format('M j, Y g:i A') }}
                                @if ($update->author?->name)
                                    · {{ $update->author->name }}
                                @endif
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No project updates yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

</x-review-layout>
