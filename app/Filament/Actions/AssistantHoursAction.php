<?php
namespace App\Filament\Actions;

use App\Models\WeeklyPayoutBatch;
use App\Services\AssistantPayoutService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

final class AssistantHoursAction
{
    public static function make(WeeklyPayoutBatch $batch): Action
    {
        return Action::make('sync_assistant_hours')
            ->label('Add Assistant Hours')
            ->visible(fn () => $batch->fresh()->status === 'draft')
            ->requiresConfirmation()
            ->action(function () use ($batch): void {
                $count = app(AssistantPayoutService::class)->sync($batch);
                Notification::make()->title($count.' assistant payouts added')->success()->send();
            });
    }
}
