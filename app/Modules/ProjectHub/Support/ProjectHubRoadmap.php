<?php

namespace App\Modules\ProjectHub\Support;

use App\Models\Project;
use App\Models\User;

class ProjectHubRoadmap
{
    public static function ensureWorkspace(?User $user = null): Project
    {
        $existing = Project::query()
            ->orderByDesc('is_active')
            ->orderByDesc('updated_at')
            ->first();

        if ($existing) {
            static::apply($existing);

            return $existing;
        }

        $project = Project::create([
            'name' => 'Vortex Breaks Project Hub',
            'slug' => 'vortex-breaks-project-hub',
            'status' => 'planning',
            'phase' => 'Operational Discovery',
            'progress_percent' => 5,
            'owner_user_id' => $user?->id,
            'manager_user_id' => $user?->isAdmin() ? $user->id : null,
            'is_active' => true,
            'client_visible' => true,
        ]);

        static::apply($project);

        return $project;
    }

    public static function apply(Project $project): void
    {
        $roadmap = static::template();

        foreach ($roadmap['milestones'] as $index => $milestone) {
            $project->milestones()->firstOrCreate(
                ['title' => $milestone['title']],
                $milestone + ['sort_order' => $index + 1]
            );
        }

        foreach ($roadmap['status_updates'] as $update) {
            $project->statusUpdates()->firstOrCreate(
                ['title' => $update['title']],
                $update + ['created_by' => auth()->id()]
            );
        }

        foreach ($roadmap['approvals'] as $approval) {
            $project->approvals()->firstOrCreate(
                ['label' => $approval['label']],
                $approval
            );
        }

        $project->fill(array_filter([
            'summary' => blank($project->summary) ? $roadmap['summary'] : null,
            'current_focus' => blank($project->current_focus) ? $roadmap['current_focus'] : null,
            'client_needs' => blank($project->client_needs) ? $roadmap['client_needs'] : null,
            'phase' => blank($project->phase) ? 'Operational Discovery' : null,
            'status' => blank($project->status) ? 'planning' : null,
        ], fn ($value) => $value !== null));

        $project->save();
    }

    public static function template(): array
    {
        return [
            'summary' => 'Internal implementation workspace for the Vortex Breaks operational platform rollout, covering inventory, stream tracking, reconciliation, payouts, reporting, and workflow automation.',
            'current_focus' => implode("\n", [
                '- Operational discovery and spreadsheet review',
                '- Workflow mapping across inventory, stream tracking, and payout prep',
                '- Centralized platform architecture and reporting design',
            ]),
            'client_needs' => implode("\n", [
                '- Validate workflow assumptions discovered during operational mapping',
                '- Review progress updates and milestone movement each week',
                '- Flag blockers, missing data, or process changes in the conversation thread',
            ]),
            'milestones' => [
                [
                    'title' => 'Week 1: Operational Discovery & Workflow Mapping',
                    'description' => 'Spreadsheet review, workflow mapping, reporting analysis, platform architecture planning, and operational process evaluation.',
                    'status' => 'in_progress',
                    'visible_to_client' => true,
                ],
                [
                    'title' => 'Weeks 2–6: Core Operational Foundation',
                    'description' => 'Inventory workflows, stream tracking systems, dashboard foundations, reporting systems, seller management workflows, and centralized operational tooling.',
                    'status' => 'not_started',
                    'visible_to_client' => true,
                ],
                [
                    'title' => 'Weeks 6–12: Reconciliation & Payout Stabilization',
                    'description' => 'Reconciliation workflows, payout preparation systems, reporting enhancements, administrative tooling, workflow refinement, stabilization, and operational optimization.',
                    'status' => 'not_started',
                    'visible_to_client' => true,
                ],
                [
                    'title' => 'Months 3–4: Workflow Automation & Optimization',
                    'description' => 'Workflow automation systems, process streamlining, advanced reporting, notifications, efficiency improvements, and internal workflow optimization.',
                    'status' => 'not_started',
                    'visible_to_client' => true,
                ],
                [
                    'title' => 'Months 4–6: Platform Maturity & Scaling',
                    'description' => 'Platform maturity improvements, scaling enhancements, integration expansion, automation refinement, advanced tooling, and long-term operational growth initiatives.',
                    'status' => 'not_started',
                    'visible_to_client' => true,
                ],
                [
                    'title' => 'Ongoing: Evolution & Partnership Support',
                    'description' => 'Continued platform evolution, future enhancements, optimization work, and ongoing operational support as business needs change.',
                    'status' => 'not_started',
                    'visible_to_client' => true,
                ],
            ],
            'status_updates' => [
                [
                    'title' => 'Roadmap loaded into Project Hub',
                    'body' => 'This workspace now tracks the operational platform rollout against the agreed roadmap phases, from discovery through scaling and long-term support.',
                    'status' => 'note',
                    'visible_to_client' => true,
                ],
                [
                    'title' => 'Primary operational goals established',
                    'body' => implode("\n", [
                        'Reduce spreadsheet dependency and manual operational tracking.',
                        'Centralize inventory, stream, reporting, and operational workflows.',
                        'Improve payout preparation and reconciliation accuracy.',
                        'Increase operational visibility through dashboards and reporting.',
                        'Improve workflow efficiency through automation and tooling.',
                        'Create a scalable operational platform that evolves with business growth.',
                    ]),
                    'status' => 'completed',
                    'visible_to_client' => true,
                ],
                [
                    'title' => 'Timeline disclaimer recorded',
                    'body' => 'Timeline estimates and priorities may evolve based on operational discoveries, workflow refinement, business growth, feedback cycles, and technical realities during implementation.',
                    'status' => 'note',
                    'visible_to_client' => true,
                ],
            ],
            'approvals' => [
                [
                    'label' => 'Approve workflow mapping assumptions',
                    'description' => 'Confirm that the documented inventory, stream, reporting, and payout workflows reflect how operations should run going forward.',
                    'status' => 'pending',
                    'visible_to_client' => true,
                ],
                [
                    'label' => 'Approve launch-readiness criteria',
                    'description' => 'Agree on the minimum reporting, reconciliation, payout, and operational criteria required before this platform is considered launch ready.',
                    'status' => 'pending',
                    'visible_to_client' => true,
                ],
            ],
        ];
    }
}
