<x-review-layout title="Project Hub">

    <section class="review-hero relative overflow-hidden rounded-[2rem] p-8">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(34,211,238,0.12),transparent_28%),radial-gradient(circle_at_left,rgba(124,58,237,0.08),transparent_26%)]"></div>
        <div class="relative flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="review-kicker text-xs font-semibold uppercase tracking-[0.24em]">Delivery Command Center</p>
                <h1 class="mt-4 text-4xl font-semibold tracking-tight text-slate-950">Project Hub</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-600">
                    Track implementation progress, approvals, launch readiness, and{{ $reviewsEnabled ? ' review feedback' : '' }} from one enterprise-style client workspace.
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="review-muted-card rounded-2xl px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Projects</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $projects->count() }}</p>
                </div>
                <div class="review-muted-card rounded-2xl px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Portal Mode</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $reviewsEnabled ? 'Workspace + Feedback' : 'Workspace Only' }}</p>
                </div>
                <div class="review-muted-card rounded-2xl px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Access</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ auth()->user()?->isSuperAdmin() ? 'Internal Admin' : 'Client / Reviewer' }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="mt-8 space-y-5">
        @forelse ($projects as $project)
            @php
                $statusColors = [
                    'planning' => 'text-slate-600 bg-slate-100 border-slate-200',
                    'implementation' => 'text-sky-700 bg-sky-50 border-sky-200',
                    'review' => 'text-amber-700 bg-amber-50 border-amber-200',
                    'blocked' => 'text-rose-700 bg-rose-50 border-rose-200',
                    'ready_to_launch' => 'text-emerald-700 bg-emerald-50 border-emerald-200',
                    'launched' => 'text-emerald-700 bg-emerald-50 border-emerald-200',
                    'archived' => 'text-slate-500 bg-slate-100 border-slate-200',
                ];
                $pill = $statusColors[$project->status] ?? 'text-slate-600 bg-slate-100 border-slate-200';
                $milestoneSummary = $project->total_milestones_count
                    ? $project->completed_milestones_count . '/' . $project->total_milestones_count . ' milestones'
                    : 'No milestones yet';
            @endphp

            <a href="{{ route('review.project', $project) }}"
               class="review-surface group block overflow-hidden rounded-[1.75rem] p-6 transition duration-200 hover:border-cyan-300/40 hover:bg-white">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-2xl font-semibold tracking-tight text-slate-950">{{ $project->name }}</h2>
                            <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] {{ $pill }}">
                                {{ \App\Models\Project::statusLabels()[$project->status] ?? ucfirst($project->status) }}
                            </span>
                            @if ($project->phase)
                                <span class="rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-cyan-700">
                                    {{ $project->phase }}
                                </span>
                            @endif
                        </div>

                        @if ($project->summary)
                            <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-600">{{ $project->summary }}</p>
                        @endif

                        <div class="mt-6 max-w-2xl">
                            <div class="mb-2 flex items-center justify-between text-xs font-medium text-slate-500">
                                <span>Delivery Progress</span>
                                <span>{{ $project->progress_percent }}%</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 via-sky-500 to-violet-500 transition-all duration-500" style="width: {{ max(0, min(100, $project->progress_percent)) }}%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="grid min-w-[300px] gap-3 sm:grid-cols-2 xl:w-[360px]">
                        @if ($reviewsEnabled)
                            <div class="review-muted-card rounded-2xl p-4">
                                <p class="text-[11px] uppercase tracking-[0.18em] text-violet-700/80">Open Review Items</p>
                                <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $project->open_review_items_count }}</p>
                            </div>
                        @endif
                        <div class="review-muted-card rounded-2xl p-4">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-amber-700/80">Pending Approvals</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $project->pending_approvals_count }}</p>
                        </div>
                        @if ($reviewsEnabled)
                            <div class="review-muted-card rounded-2xl p-4">
                                <p class="text-[11px] uppercase tracking-[0.18em] text-emerald-700/80">Resolved Feedback</p>
                                <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $project->resolved_review_items_count }}</p>
                            </div>
                        @endif
                        <div class="review-muted-card rounded-2xl p-4">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Milestones</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $milestoneSummary }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-slate-200 pt-4 text-xs text-slate-500">
                    @if ($project->launch_date)
                        <span>Launch ETA <span class="font-medium text-slate-800">{{ $project->launch_date->format('F j, Y') }}</span></span>
                    @endif
                    @if ($project->manager?->name)
                        <span>PM <span class="font-medium text-slate-800">{{ $project->manager->name }}</span></span>
                    @endif
                    @if ($project->owner?->name)
                        <span>Client Lead <span class="font-medium text-slate-800">{{ $project->owner->name }}</span></span>
                    @endif
                </div>
            </a>
        @empty
            <div class="review-surface rounded-[1.75rem] border-dashed px-6 py-20 text-center text-slate-500">
                <p class="text-base font-medium text-slate-900">No active projects yet.</p>
                <p class="mt-2 text-sm">Create a project in the admin panel to start using Project Hub.</p>
            </div>
        @endforelse
    </div>

</x-review-layout>
