<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Resources\WeeklyPayoutBatchResource;
use App\Services\PayoutService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWeeklyPayoutBatches extends ListRecords
{
    protected static string $resource = WeeklyPayoutBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_batch')
                ->label('New Pay Run')
                ->icon('heroicon-o-plus')
                ->form([
                    DatePicker::make('week_start')
                        ->label('Week Start (Monday)')
                        ->required()
                        ->default(now()->startOfWeek()),
                ])
                ->action(function (array $data) {
                    $batch = app(PayoutService::class)->createWeeklyBatch($data['week_start']);

                    Notification::make()
                        ->title("Pay run created: week of {$batch->week_start->format('M j')}.")
                        ->body("{$batch->payouts()->count()} payout(s) attached. Total: \$" . number_format($batch->total_payout, 2))
                        ->success()
                        ->send();
                }),
        ];
    }
}
