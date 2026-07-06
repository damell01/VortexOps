<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Resources\WeeklyPayoutBatchResource;
use App\Models\Streamer;
use App\Services\PayoutService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
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
                ->modalHeading('Create Pay Run')
                ->modalDescription('Set the pay period and choose which streamers to include. Existing draft payouts from shows in the selected week will be attached automatically; streamers with no shows get a $0 manual entry you can update on the next screen.')
                ->modalWidth('2xl')
                ->form([
                    DatePicker::make('week_start')
                        ->label('Pay Period Start')
                        ->helperText('Week end (Sunday) is set automatically.')
                        ->required()
                        ->default(now()->startOfWeek()),

                    CheckboxList::make('streamer_ids')
                        ->label('Streamers to Include')
                        ->options(fn () => Streamer::where('status', 'active')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn ($s) => [
                                $s->id => $s->name . ' (' . (Streamer::payoutTypeLabels()[$s->payout_type] ?? $s->payout_type) . ')',
                            ])
                            ->toArray())
                        ->columns(2)
                        ->required()
                        ->minItems(1),

                    Textarea::make('notes')
                        ->label('Notes (optional)')
                        ->rows(2)
                        ->placeholder('e.g. Holiday week, bonus included…'),
                ])
                ->action(function (array $data) {
                    $batch = app(PayoutService::class)->createManualBatch(
                        weekStart: $data['week_start'],
                        streamerIds: $data['streamer_ids'],
                        notes: $data['notes'] ?? null,
                    );

                    $count = $batch->payouts()->count();

                    Notification::make()
                        ->title("Pay run created — week of {$batch->week_start->format('M j, Y')}")
                        ->body("{$count} payout " . ($count === 1 ? 'entry' : 'entries') . ' created. Review and adjust amounts below.')
                        ->success()
                        ->send();

                    return redirect(WeeklyPayoutBatchResource::getUrl('view', ['record' => $batch]));
                }),
        ];
    }
}
