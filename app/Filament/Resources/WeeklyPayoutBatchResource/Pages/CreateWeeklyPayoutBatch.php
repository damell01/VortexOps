<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Resources\WeeklyPayoutBatchResource;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;
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
use Illuminate\Validation\ValidationException;

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
                    ->label('Any day in the pay week')
                    ->helperText(fn () => 'Pay weeks run '.\App\Support\PaySchedule::startDayName().' to '.\App\Support\PaySchedule::endDayName().' (change it under Rates & Adjustments). The run covers the pay week containing this date; VortexOps prevents overlapping Pay Runs.')
                    ->required()
                    ->default(fn () => \App\Support\PaySchedule::periodFor(now())[0]),

                Textarea::make('notes')
                    ->label('Notes (optional)')
                    ->rows(2)
                    ->placeholder('e.g. Holiday week, bonus included…')
                    ->columnSpanFull(),
            ]),

            Section::make('Team Members to Include')->schema([
                CheckboxList::make('streamer_ids')
                    ->label('Active Team Members')
                    ->helperText('All active team members are included by default. Every Pay Run is weekly; show-based payouts still have to pass the show/admin/fulfillment readiness gates before finalization.')
                    ->options(fn () => Streamer::where('status', 'active')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->default(fn () => Streamer::where('status', 'active')
                        ->pluck('id')
                        ->toArray())
                    ->columns(2)
                    ->bulkToggleable()
                    ->required(),
            ]),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        [$start, $end] = \App\Support\PaySchedule::periodFor($data['week_start']);
        $overlap = WeeklyPayoutBatch::overlapping($start->toDateString(), $end->toDateString());

        if ($overlap) {
            throw ValidationException::withMessages([
                'data.week_start' => 'That week already overlaps Pay Run #' . $overlap->id
                    . ' (' . $overlap->week_start->format('M j') . '–' . $overlap->week_end->format('M j, Y') . '). Open the existing Pay Run instead.',
            ]);
        }

        $batch = app(PayoutService::class)->createManualBatch(
            weekStart: $start->toDateString(),
            streamerIds: $data['streamer_ids'] ?? [],
            notes: $data['notes'] ?? null,
        );

        $count = $batch->payouts()->count();

        Notification::make()
            ->title("Pay run created — week of {$batch->week_start->format('M j, Y')}")
            ->body("{$count} payout " . ($count === 1 ? 'entry' : 'entries') . ' created. Validate or recalculate before finalizing to apply current show readiness rules.')
            ->success()
            ->send();

        return $batch;
    }

    protected function getRedirectUrl(): string
    {
        return WeeklyPayoutBatchResource::getUrl('view', ['record' => $this->record]);
    }
}
