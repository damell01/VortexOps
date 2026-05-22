<x-review-layout title="Project Hub">

    <div class="mb-8 flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Project Hub</h1>
            <p class="mt-1 text-sm text-gray-500">
                Track implementation progress, approvals, launch work, and{{ $reviewsEnabled ? ' review feedback' : '' }} in one place.
            </p>
        </div>
    </div>

    @forelse ($projects as $project)
        @php
            $statusColors = [
                'planning' => 'bg-gray-100 text-gray-600 ring-gray-200',
                'implementation' => 'bg-blue-50 text-blue-700 ring-blue-200',
                'review' => 'bg-amber-50 text-amber-700 ring-amber-200',
                'blocked' => 'bg-red-50 text-red-700 ring-red-200',
                'ready_to_launch' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                'launched' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                'archived' => 'bg-gray-100 text-gray-500 ring-gray-200',
            ];
            $pill = $statusColors[$project->status] ?? 'bg-gray-100 text-gray-500 ring-gray-200';
            $milestoneSummary = $project->total_milestones_count
                ? $project->completed_milestones_count . '/' . $project->total_milestones_count . ' milestones'
                : 'No milestones yet';
        @endphp

        <a href="{{ route('review.project', $project) }}"
           class="mb-4 block rounded-3xl border border-gray-200 bg-white p-6 shadow-sm transition hover:border-violet-300 hover:shadow-md">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-xl font-semibold text-gray-900">{{ $project->name }}</h2>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 {{ $pill }}">
                            {{ \App\Models\Project::statusLabels()[$project->status] ?? ucfirst($project->status) }}
                        </span>
                        @if ($project->phase)
                            <span class="rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-medium text-violet-700">
                                {{ $project->phase }}
                            </span>
                        @endif
                    </div>

                    @if ($project->summary)
                        <p class="mt-2 max-w-3xl text-sm text-gray-600">{{ $project->summary }}</p>
                    @endif

                    <div class="mt-4">
                        <div class="mb-2 flex items-center justify-between text-xs font-medium text-gray-500">
                            <span>Progress</span>
                            <span>{{ $project->progress_percent }}%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-violet-600" style="width: {{ max(0, min(100, $project->progress_percent)) }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="grid min-w-[280px] grid-cols-2 gap-3 lg:w-[340px]">
                    @if ($reviewsEnabled)
                        <div class="rounded-2xl bg-violet-50 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-violet-500">Open Review Items</p>
                            <p class="mt-1 text-2xl font-bold text-violet-900">{{ $project->open_review_items_count }}</p>
                        </div>
                    @endif
                    <div class="rounded-2xl bg-amber-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-600">Pending Approvals</p>
                        <p class="mt-1 text-2xl font-bold text-amber-900">{{ $project->pending_approvals_count }}</p>
                    </div>
                    @if ($reviewsEnabled)
                        <div class="rounded-2xl bg-emerald-50 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-600">Resolved Feedback</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-900">{{ $project->resolved_review_items_count }}</p>
                        </div>
                    @endif
                    <div class="rounded-2xl bg-slate-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Milestones</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $milestoneSummary }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-gray-500">
                @if ($project->launch_date)
                    <span>Launch ETA: <span class="font-medium text-gray-700">{{ $project->launch_date->format('F j, Y') }}</span></span>
                @endif
                @if ($project->manager?->name)
                    <span>PM: <span class="font-medium text-gray-700">{{ $project->manager->name }}</span></span>
                @endif
                @if ($project->owner?->name)
                    <span>Client Lead: <span class="font-medium text-gray-700">{{ $project->owner->name }}</span></span>
                @endif
            </div>
        </a>
    @empty
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white py-16 text-center">
            <p class="text-sm font-medium text-gray-400">No active projects yet.</p>
            <p class="mt-1 text-xs text-gray-300">Create a project in the admin panel to start using Project Hub.</p>
        </div>
    @endforelse

</x-review-layout>
