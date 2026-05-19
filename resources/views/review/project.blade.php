<x-review-layout title="{{ $project->name }}" :project-id="$project->id">

    <div class="mb-6">
        <a href="{{ route('review.index') }}" class="mb-2 inline-flex items-center gap-1 text-xs text-gray-400 hover:text-gray-600">
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            All Projects
        </a>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-3xl font-bold text-gray-900">{{ $project->name }}</h1>
                    <span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700">
                        {{ $project->phase ?: (\App\Models\Project::statusLabels()[$project->status] ?? ucfirst($project->status)) }}
                    </span>
                </div>
                @if ($project->summary)
                    <p class="mt-2 max-w-3xl text-sm text-gray-600">{{ $project->summary }}</p>
                @endif
            </div>
            <div class="rounded-2xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-100">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Project Progress</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $project->progress_percent }}%</p>
                @if ($project->launch_date)
                    <p class="mt-1 text-xs text-gray-500">Launch ETA {{ $project->launch_date->format('F j, Y') }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Overview</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl bg-violet-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-violet-500">Open Issues</p>
                        <p class="mt-2 text-3xl font-bold text-violet-900">{{ $project->open_review_items_count }}</p>
                    </div>
                    <div class="rounded-2xl bg-amber-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Pending Approvals</p>
                        <p class="mt-2 text-3xl font-bold text-amber-900">{{ $project->pending_approvals_count }}</p>
                    </div>
                    <div class="rounded-2xl bg-emerald-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Resolved</p>
                        <p class="mt-2 text-3xl font-bold text-emerald-900">{{ $project->resolved_review_items_count }}</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current Focus</p>
                        <div class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $project->current_focus ?: 'No active sprint summary yet.' }}</div>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Needed From You</p>
                        <div class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $project->client_needs ?: 'Nothing blocking progress right now.' }}</div>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Milestones</h2>
                    <span class="text-xs text-gray-400">{{ $project->milestones->count() }} total</span>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse ($project->milestones as $milestone)
                        <div class="rounded-2xl border border-gray-100 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $milestone->title }}</p>
                                    @if ($milestone->description)
                                        <p class="mt-1 text-sm text-gray-600">{{ $milestone->description }}</p>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">
                                        {{ \App\Models\ProjectMilestone::statusLabels()[$milestone->status] ?? ucfirst($milestone->status) }}
                                    </span>
                                    @if ($milestone->due_date)
                                        <p class="mt-2 text-xs text-gray-400">Due {{ $milestone->due_date->format('M j, Y') }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No milestones have been added yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Feedback Sessions</h2>
                    <span class="text-xs text-gray-400">{{ $project->reviewSessions->count() }} sessions</span>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse ($project->reviewSessions as $session)
                        <a href="{{ route('review.session', $session) }}" class="block rounded-2xl border border-gray-100 p-4 transition hover:border-violet-300 hover:bg-violet-50/40">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $session->title }}</p>
                                    <p class="mt-1 text-xs text-gray-400">{{ $session->items_count }} item{{ $session->items_count === 1 ? '' : 's' }} · {{ $session->created_at->format('M j, Y g:i A') }}</p>
                                </div>
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                                    {{ \App\Models\ReviewSession::statusLabels()[$session->status] ?? ucfirst($session->status) }}
                                </span>
                            </div>
                        </a>
                    @empty
                        <p class="text-sm text-gray-400">No feedback sessions yet. Review Mode will start filling this in.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="space-y-6">
            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Pending Approvals</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($project->approvals as $approval)
                        <div class="rounded-2xl border border-gray-100 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $approval->label }}</p>
                                    @if ($approval->description)
                                        <p class="mt-1 text-sm text-gray-600">{{ $approval->description }}</p>
                                    @endif
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $approval->status === 'pending' ? 'bg-amber-50 text-amber-700' : ($approval->status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600') }}">
                                    {{ \App\Models\ProjectApproval::statusLabels()[$approval->status] ?? ucfirst($approval->status) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No approvals are waiting right now.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Activity Feed</h2>
                <div class="mt-4 space-y-4">
                    @forelse ($project->statusUpdates as $update)
                        <div class="border-l-2 border-violet-200 pl-4">
                            <p class="text-sm font-medium text-gray-900">{{ $update->title }}</p>
                            @if ($update->body)
                                <p class="mt-1 text-sm text-gray-600">{{ $update->body }}</p>
                            @endif
                            <p class="mt-2 text-xs text-gray-400">
                                {{ \App\Models\ProjectStatusUpdate::statusLabels()[$update->status] ?? ucfirst($update->status) }}
                                · {{ $update->created_at->format('M j, Y g:i A') }}
                                @if ($update->author?->name)
                                    · {{ $update->author->name }}
                                @endif
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No project updates yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

</x-review-layout>
