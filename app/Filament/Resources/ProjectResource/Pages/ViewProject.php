<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectComment;
use App\Models\ProjectMilestone;
use App\Models\ProjectStatusUpdate;
use Filament\Actions\ActionGroup;
use App\Modules\ProjectHub\Support\ProjectHubRoadmap;
use App\Support\AdminModules;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    public function getView(): string
    {
        return 'filament.resources.project-resource.pages.workspace-hub';
    }

    public function workspaceRecord(): Project
    {
        $reviewsEnabled = AdminModules::isEnabled('reviews');

        $relations = [
            'milestones',
            'approvals' => fn ($query) => $query->latest('requested_at')->latest(),
            'statusUpdates.author:id,name',
            'comments.user:id,name',
        ];

        if ($reviewsEnabled) {
            $relations['reviewSessions'] = fn ($query) => $query->withCount('items')->latest()->limit(8);
            $relations['reviewItems'] = fn ($query) => $query
                ->with(['session:id,title,project_id', 'createdBy:id,name'])
                ->latest()
                ->limit(8);
        }

        /** @var Project $project */
        $project = $this->getRecord()->load($relations);

        $counts = [
            'approvals as pending_approvals_count' => fn ($query) => $query->where('project_approvals.status', 'pending'),
            'milestones as completed_milestones_count' => fn ($query) => $query->whereIn('project_milestones.status', ['completed', 'approved']),
            'milestones as total_milestones_count',
        ];

        if ($reviewsEnabled) {
            $counts['reviewItems as open_review_items_count'] = fn ($query) => $query->whereIn('review_items.status', ['open', 'in_progress']);
            $counts['reviewItems as resolved_review_items_count'] = fn ($query) => $query->whereIn('review_items.status', ['fixed', 'approved']);
        }

        $project->loadCount($counts);

        return $project;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('addComment')
                    ->label('Add Comment')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->form([
                        Textarea::make('body')
                            ->label('Comment')
                            ->rows(4)
                            ->required()
                            ->maxLength(5000),
                    ])
                    ->action(function (array $data): void {
                        ProjectComment::create([
                            'project_id' => $this->record->id,
                            'user_id' => auth()->id(),
                            'body' => $data['body'],
                        ]);

                        Notification::make()->title('Comment added')->success()->send();
                    }),
                Action::make('addStatusUpdate')
                    ->label('Add Status Update')
                    ->icon('heroicon-o-megaphone')
                    ->form([
                        TextInput::make('title')->required()->maxLength(255),
                        Select::make('status')
                            ->options(ProjectStatusUpdate::statusLabels())
                            ->default('note')
                            ->required(),
                        Toggle::make('visible_to_client')
                            ->default(true),
                        Textarea::make('body')
                            ->rows(4),
                    ])
                    ->action(function (array $data): void {
                        ProjectStatusUpdate::create($data + [
                            'project_id' => $this->record->id,
                            'created_by' => auth()->id(),
                        ]);

                        Notification::make()->title('Status update added')->success()->send();
                    }),
                Action::make('addMilestone')
                    ->label('Add Milestone')
                    ->icon('heroicon-o-flag')
                    ->form([
                        TextInput::make('title')->required()->maxLength(255),
                        Select::make('status')
                            ->options(ProjectMilestone::statusLabels())
                            ->default('not_started')
                            ->required(),
                        DatePicker::make('due_date'),
                        Toggle::make('visible_to_client')
                            ->default(true),
                        Textarea::make('description')
                            ->rows(3),
                    ])
                    ->action(function (array $data): void {
                        ProjectMilestone::create($data + [
                            'project_id' => $this->record->id,
                            'sort_order' => (int) ProjectMilestone::where('project_id', $this->record->id)->max('sort_order') + 1,
                        ]);

                        Notification::make()->title('Milestone added')->success()->send();
                    }),
                Action::make('addApproval')
                    ->label('Add Approval')
                    ->icon('heroicon-o-check-badge')
                    ->form([
                        TextInput::make('label')->required()->maxLength(255),
                        Select::make('status')
                            ->options(ProjectApproval::statusLabels())
                            ->default('pending')
                            ->required(),
                        DateTimePicker::make('requested_at'),
                        Toggle::make('visible_to_client')
                            ->default(true),
                        Textarea::make('description')
                            ->rows(3),
                        Textarea::make('notes')
                            ->rows(3),
                    ])
                    ->action(function (array $data): void {
                        ProjectApproval::create($data + [
                            'project_id' => $this->record->id,
                        ]);

                        Notification::make()->title('Approval item added')->success()->send();
                    }),
            ])
                ->label('Quick Add')
                ->icon('heroicon-o-plus')
                ->button(),
            Action::make('applyRoadmapTemplate')
                ->label('Load Roadmap Template')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->action(function (): void {
                    ProjectHubRoadmap::apply($this->record);

                    Notification::make()
                        ->title('Roadmap template loaded')
                        ->body('Milestones, approvals, and progress updates were synced into this project hub.')
                        ->success()
                        ->send();
                }),
            EditAction::make(),
        ];
    }
}
