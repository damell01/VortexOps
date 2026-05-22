<x-review-layout title="Project Hub">

    <section class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-[rgba(8,15,29,0.78)] p-8 shadow-[0_30px_80px_rgba(2,6,23,0.45)] backdrop-blur-xl">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(34,211,238,0.14),transparent_28%),radial-gradient(circle_at_left,rgba(124,58,237,0.16),transparent_26%)]"></div>
        <div class="relative flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-300/80">Delivery Command Center</p>
                <h1 class="mt-4 text-4xl font-semibold tracking-tight text-white">Project Hub</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300">
                    Track implementation progress, approvals, launch readiness, and{{ $reviewsEnabled ? ' review feedback' : '' }} from one enterprise-style client workspace.
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Projects</p>
                    <p class="mt-2 text-3xl font-semibold text-white">{{ $projects->count() }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Portal Mode</p>
                    <p class="mt-2 text-sm font-semibold text-white">{{ $reviewsEnabled ? 'Workspace + Feedback' : 'Workspace Only' }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Access</p>
                    <p class="mt-2 text-sm font-semibold text-white">{{ auth()->user()?->isSuperAdmin() ? 'Internal Admin' : 'Client / Reviewer' }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="mt-8 space-y-5">
        @forelse ($projects as $project)
            @php
                $statusColors = [
                    'planning' => 'text-slate-300 bg-slate-400/10 border-slate-300/20',
                    'implementation' => 'text-sky-300 bg-sky-400/10 border-sky-300/20',
                    'review' => 'text-amber-300 bg-amber-400/10 border-amber-300/20',
                    'blocked' => 'text-rose-300 bg-rose-400/10 border-rose-300/20',
                    'ready_to_launch' => 'text-emerald-300 bg-emerald-400/10 border-emerald-300/20',
                    'launched' => 'text-emerald-300 bg-emerald-400/10 border-emerald-300/20',
                    'archived' => 'text-slate-400 bg-slate-400/10 border-slate-400/20',
                ];
                $pill = $statusColors[$project->status] ?? 'text-slate-300 bg-slate-400/10 border-slate-300/20';
                $milestoneSummary = $project->total_milestones_count
                    ? $project->completed_milestones_count . '/' . $project->total_milestones_count . ' milestones'
                    : 'No milestones yet';
            @endphp

            <a href="{{ route('review.project', $project) }}"
               class="group block overflow-hidden rounded-[1.75rem] border border-white/10 bg-[rgba(9,16,31,0.78)] p-6 shadow-[0_24px_60px_rgba(2,6,23,0.32)] backdrop-blur-xl transition duration-200 hover:border-cyan-300/30 hover:bg-[rgba(11,19,37,0.92)]">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-2xl font-semibold tracking-tight text-white">{{ $project->name }}</h2>
                            <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] {{ $pill }}">
                                {{ \App\Models\Project::statusLabels()[$project->status] ?? ucfirst($project->status) }}
                            </span>
                            @if ($project->phase)
                                <span class="rounded-full border border-violet-300/20 bg-violet-400/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-violet-200">
                                    {{ $project->phase }}
                                </span>
                            @endif
                        </div>

                        @if ($project->summary)
                            <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-300">{{ $project->summary }}</p>
                        @endif

                        <div class="mt-6 max-w-2xl">
                            <div class="mb-2 flex items-center justify-between text-xs font-medium text-slate-400">
                                <span>Delivery Progress</span>
                                <span>{{ $project->progress_percent }}%</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-800">
                                <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 via-sky-500 to-violet-500 transition-all duration-500" style="width: {{ max(0, min(100, $project->progress_percent)) }}%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="grid min-w-[300px] gap-3 sm:grid-cols-2 xl:w-[360px]">
                        @if ($reviewsEnabled)
                            <div class="rounded-2xl border border-violet-300/15 bg-violet-400/10 p-4">
                                <p class="text-[11px] uppercase tracking-[0.18em] text-violet-200/80">Open Review Items</p>
                                <p class="mt-2 text-2xl font-semibold text-white">{{ $project->open_review_items_count }}</p>
                            </div>
                        @endif
                        <div class="rounded-2xl border border-amber-300/15 bg-amber-400/10 p-4">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-amber-200/80">Pending Approvals</p>
                            <p class="mt-2 text-2xl font-semibold text-white">{{ $project->pending_approvals_count }}</p>
                        </div>
                        @if ($reviewsEnabled)
                            <div class="rounded-2xl border border-emerald-300/15 bg-emerald-400/10 p-4">
                                <p class="text-[11px] uppercase tracking-[0.18em] text-emerald-200/80">Resolved Feedback</p>
                                <p class="mt-2 text-2xl font-semibold text-white">{{ $project->resolved_review_items_count }}</p>
                            </div>
                        @endif
                        <div class="rounded-2xl border border-slate-300/15 bg-slate-400/10 p-4">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-slate-300/80">Milestones</p>
                            <p class="mt-2 text-sm font-semibold text-white">{{ $milestoneSummary }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-white/8 pt-4 text-xs text-slate-400">
                    @if ($project->launch_date)
                        <span>Launch ETA <span class="font-medium text-slate-200">{{ $project->launch_date->format('F j, Y') }}</span></span>
                    @endif
                    @if ($project->manager?->name)
                        <span>PM <span class="font-medium text-slate-200">{{ $project->manager->name }}</span></span>
                    @endif
                    @if ($project->owner?->name)
                        <span>Client Lead <span class="font-medium text-slate-200">{{ $project->owner->name }}</span></span>
                    @endif
                </div>
            </a>
        @empty
            <div class="rounded-[1.75rem] border border-dashed border-white/15 bg-[rgba(9,16,31,0.78)] px-6 py-20 text-center text-slate-400 backdrop-blur-xl">
                <p class="text-base font-medium text-slate-200">No active projects yet.</p>
                <p class="mt-2 text-sm">Create a project in the admin panel to start using Project Hub.</p>
            </div>
        @endforelse
    </div>

</x-review-layout>
