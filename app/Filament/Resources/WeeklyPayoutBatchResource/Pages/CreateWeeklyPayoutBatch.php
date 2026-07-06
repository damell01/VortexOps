<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Resources\WeeklyPayoutBatchResource;
use App\Models\Streamer;
use App\Services\PayoutService;
use Carbon\Carbon;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateWeeklyPayoutBatch extends CreateRecord
{
    protected static string $resource = WeeklyPayoutBatchResource::class;

    public function getTitle(): string
    {
        return 'New Pay Run';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pay Period')->columns(2)->schema([
                DatePicker::make('week_start')
                    ->label('Week Start (Monday)')
                    ->helperText('Week end (Sunday) is calculated automatically.')
                    ->required()
                    ->default(now()->startOfWeek(Carbon::MONDAY)),

                Textarea::make('notes')
                    ->label('Notes (optional)')
                    ->rows(2)
                    ->placeholder('e.g. Holiday week, bonus included…')
                    ->columnSpanFull(),
            ]),

            Section::make('Streamers to Include')->schema([
                CheckboxList::make('streamer_ids')
                    ->label('Active Streamers')
                    ->options(fn () => Streamer::where('status', 'active')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn ($s) => [
                            $s->id => $s->name . ' — ' . (Streamer::payoutTypeLabels()[$s->payout_type] ?? $s->payout_type),
                        ])
                        ->toArray())
                    ->columns(2)
                    ->bulkToggleable()
                    ->required(),
            ]),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $batch = app(PayoutService::class)->createManualBatch(
            weekStart: $data['week_start'],
            streamerIds: $data['streamer_ids'] ?? [],
            notes: $data['notes'] ?? null,
        );

        $count = $batch->payouts()->count();

        Notification::make()
            ->title("Pay run created — week of {$batch->week_start->format('M j, Y')}")
            ->body("{$count} payout " . ($count === 1 ? 'entry' : 'entries') . ' created.')
            ->success()
            ->send();

        return $batch;
    }

    protected function getRedirectUrl(): string
    {
        return WeeklyPayoutBatchResource::getUrl('view', ['record' => $this->record]);
    }
}
