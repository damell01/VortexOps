<x-filament-panels::page>
    @php($project = $this->workspaceRecord())
    @php($reviewsEnabled = \App\Support\AdminModules::isEnabled('reviews'))

    <div class="space-y-6">
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-violet-900 text-white shadow-xl">
            <div class="grid gap-6 px-6 py-6 lg:grid-cols-[1.5fr,1fr] lg:px-8">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-violet-100">
                            {{ $project->phase ?: 'Project Hub' }}
                        </span>
                        <span class="rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-slate-100">
                            {{ \App\Models\Project::statusLabels()[$project->status] ?? ucfirst($project->status) }}
                        </span>
                    </div>

                    <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ $project->name }}</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200/90">
                        {{ $project->summary ?: 'This workspace tracks rollout progress, approvals, notes, milestones, and annotated feedback in one place.' }}
                    </p>

                    <div class="mt-6 grid gap-3 {{ $reviewsEnabled ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }}">
                        @if ($reviewsEnabled)
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-300">Open Feedback</p>
                                <p class="mt-2 text-3xl font-semibold">{{ $project->open_review_items_count }}</p>
                            </div>
                        @endif
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-300">Pending Approvals</p>
                            <p class="mt-2 text-3xl font-semibold">{{ $project->pending_approvals_count }}</p>
                        </div>
                        @if ($reviewsEnabled)
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-300">Resolved</p>
                                <p class="mt-2 text-3xl font-semibold">{{ $project->resolved_review_items_count }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-300">Progress</p>
                        <p class="text-sm font-medium text-slate-100">{{ $project->progress_percent }}%</p>
                    </div>
                    <div class="mt-3 h-3 rounded-full bg-white/10">
                        <div class="h-3 rounded-full bg-gradient-to-r from-cyan-400 to-violet-400" style="width: {{ max(4, min(100, $project->progress_percent)) }}%"></div>
                    </div>

                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-300">Client Owner</dt>
                            <dd class="text-right text-slate-50">{{ $project->owner?->name ?: 'Not set' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-300">Project Manager</dt>
                            <dd class="text-right text-slate-50">{{ $project->manager?->name ?: 'Not set' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-300">Launch ETA</dt>
                            <dd class="text-right text-slate-50">{{ $project->launch_date?->format('M j, Y') ?: 'TBD' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-300">Milestones</dt>
                            <dd class="text-right text-slate-50">{{ $project->completed_milestones_count }} / {{ $project->total_milestones_count }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.2fr,0.8fr]">
            <div class="space-y-6">
                <section class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-slate-950">Current Focus</h2>
                            <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-medium text-violet-700">Now</span>
                        </div>
                        <div class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $project->current_focus ?: 'Add current sprint focus, blockers, or the next rollout priorities here.' }}</div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-slate-950">Needed From Client</h2>
                            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">Pending</span>
                        </div>
                        <div class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $project->client_needs ?: 'No client-side asks are blocking the rollout right now.' }}</div>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Milestones</h2>
                            <p class="mt-1 text-sm text-slate-500">Track progress against the rollout roadmap without digging into raw forms.</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{{ $project->milestones->count() }} total</span>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse ($project->milestones as $milestone)
                            <div class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-semibold text-slate-900">{{ $milestone->title }}</h3>
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ match($milestone->status) { 'completed', 'approved' => 'bg-emerald-50 text-emerald-700', 'in_progress' => 'bg-blue-50 text-blue-700', 'blocked' => 'bg-rose-50 text-rose-700', default => 'bg-slate-100 text-slate-600' } }}">
                                                {{ \App\Models\ProjectMilestone::statusLabels()[$milestone->status] ?? ucfirst($milestone->status) }}
                                            </span>
                                        </div>
                                        @if ($milestone->description)
                                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $milestone->description }}</p>
                                        @endif
                                    </div>

                                    <div class="text-right text-xs text-slate-400">
                                        <p>{{ $milestone->due_date?->format('M j, Y') ?: 'No due date' }}</p>
                                        <p class="mt-1">{{ $milestone->visible_to_client ? 'Client visible' : 'Internal only' }}</p>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">No milestones yet.</p>
                        @endforelse
                    </div>
                </section>

                @if ($reviewsEnabled)
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-950">Latest Review Items</h2>
                                <p class="mt-1 text-sm text-slate-500">Annotated feedback now shows visual context directly in the list.</p>
                            </div>
                            <a href="{{ \App\Filament\Resources\ReviewItemResource::getUrl('index') }}" class="text-sm font-medium text-violet-700 hover:text-violet-800">
                                Open all
                            </a>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            @forelse ($project->reviewItems as $item)
                                <a href="{{ \App\Filament\Resources\ReviewItemResource::getUrl('view', ['record' => $item]) }}" class="group overflow-hidden rounded-2xl border border-slate-200 transition hover:border-violet-300 hover:shadow-md">
                                    <div class="aspect-[16/9] bg-slate-100">
                                        @if ($item->screenshot)
                                            <img src="{{ $item->screenshot }}" alt="Annotation screenshot" class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.01]">
                                        @else
                                            <div class="flex h-full items-center justify-center text-sm text-slate-400">No screenshot</div>
                                        @endif
                                    </div>
                                    <div class="p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ in_array($item->status, ['open', 'in_progress']) ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                                                {{ \App\Models\ReviewItem::statusLabels()[$item->status] ?? ucfirst($item->status) }}
                                            </span>
                                            <span class="text-xs text-slate-400">{{ $item->created_at->format('M j') }}</span>
                                        </div>
                                        <h3 class="mt-3 line-clamp-1 font-semibold text-slate-900">{{ $item->page_title ?: 'Untitled page' }}</h3>
                                        <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $item->comment ?: 'Open the item to add more detail.' }}</p>
                                    </div>
                                </a>
                            @empty
                                <p class="text-sm text-slate-400">No review items yet.</p>
                            @endforelse
                        </div>
                    </section>
                @endif
            </div>

            <div class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Project Conversation</h2>
                            <p class="mt-1 text-sm text-slate-500">Notes, internal comments, client context, and quick decisions all in one stream.</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{{ $project->comments->count() }}</span>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse ($project->comments->take(8) as $comment)
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-slate-900">{{ $comment->user?->name ?: 'Unknown user' }}</p>
                                    <p class="text-xs text-slate-400">{{ $comment->created_at->diffForHumans() }}</p>
                                </div>
                                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $comment->body }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">No comments yet. Use Quick Add to drop notes into the workspace without leaving this page.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Approvals</h2>
                            <p class="mt-1 text-sm text-slate-500">Keep launch gates and client signoffs visible.</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{{ $project->approvals->count() }}</span>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse ($project->approvals->take(6) as $approval)
                            <div class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold text-slate-900">{{ $approval->label }}</h3>
                                        @if ($approval->description)
                                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $approval->description }}</p>
                                        @endif
                                    </div>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $approval->status === 'approved' ? 'bg-emerald-50 text-emerald-700' : ($approval->status === 'changes_requested' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700') }}">
                                        {{ \App\Models\ProjectApproval::statusLabels()[$approval->status] ?? ucfirst($approval->status) }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">No approval items yet.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Status Updates</h2>
                            <p class="mt-1 text-sm text-slate-500">Quick operational notes and progress movement.</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-4">
                        @forelse ($project->statusUpdates->take(8) as $update)
                            <div class="border-l-2 border-violet-200 pl-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold text-slate-900">{{ $update->title }}</h3>
                                        @if ($update->body)
                                            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $update->body }}</p>
                                        @endif
                                    </div>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-600">
                                        {{ \App\Models\ProjectStatusUpdate::statusLabels()[$update->status] ?? ucfirst($update->status) }}
                                    </span>
                                </div>
                                <p class="mt-2 text-xs text-slate-400">{{ $update->created_at->format('M j, Y g:i A') }}{{ $update->author?->name ? ' · ' . $update->author->name : '' }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">No status updates yet.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
