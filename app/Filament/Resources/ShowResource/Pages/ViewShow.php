<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Filament\Resources\DeductionRequestResource;
use App\Jobs\RunShowAiMappingJob;
use App\Models\AiTask;
use App\Support\AdminModules;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewShow extends ViewRecord
{
    protected static string $resource = ShowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run_ai_mapping')
                ->label('Run AI Mapping')
                ->icon('heroicon-o-sparkles')
                ->color('violet')
                ->visible(fn () => $this->record->status === 'pending_review' && AdminModules::isEnabled('ai'))
                ->requiresConfirmation()
                ->modalHeading('Queue AI Mapping')
                ->modalDescription('This will queue a background job to match each sold item to your inventory catalogue using Ollama. You\'ll receive a notification when it\'s done. The show will move to Pending Approval automatically.')
                ->action(function () {
                    $task = AiTask::create([
                        'type'         => 'show_ai_mapping',
                        'status'       => 'pending',
                        'taskable_type' => \App\Models\Show::class,
                        'taskable_id'   => $this->record->id,
                        'triggered_by'  => auth()->id(),
                        'input'         => ['show_id' => $this->record->id, 'show_title' => $this->record->title],
                    ]);

                    RunShowAiMappingJob::dispatch($this->record->id, $task->id)->onQueue('ai');

                    Notification::make()
                        ->title('AI Mapping queued')
                        ->body('You\'ll be notified when the job completes.')
                        ->info()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Action::make('review_approval')
                ->label('Review Approval')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('info')
                ->visible(fn () => in_array($this->record->status, ['pending_approval', 'reconciled', 'closed']))
                ->url(fn () => DeductionRequestResource::getUrl('index', ['tableFilters[show_id][value]' => $this->record->id])),

            EditAction::make(),
        ];
    }
}
