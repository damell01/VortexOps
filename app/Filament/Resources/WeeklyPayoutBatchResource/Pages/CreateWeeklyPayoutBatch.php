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
                    ->label('Week Start (Monday)')
                    ->helperText('Any selected date is normalized to Monday. VortexOps prevents overlapping weekly Pay Runs.')
                    ->required()
                    ->default(now()->startOfWeek(Carbon::MONDAY)),

                Textarea::make('notes')
                    ->label('Notes (optional)')
                    ->rows(2)
                    ->placeholder('e.g. Holiday week, bonus included…')
                    ->columnSpanFull(),
            ]),

            Section::make('Team Members to Include')->schema([
                CheckboxList::make('streamer_ids')
                    ->label('Active Team Members')
                    ->helperText('Monthly-cadence members are unchecked by default. Show-based payouts still have to pass the show/admin/fulfillment readiness gates before finalization.')
                    ->options(fn () => Streamer::where('status', 'active')
                        ->orderBy('payout_cadence')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn ($s) => [
                            $s->id => $s->name . ' — ' . (Streamer::payoutTypeLabels()[$s->payout_type] ?? $s->payout_type)
                                . ' (' . (Streamer::payoutCadenceLabels()[$s->payout_cadence] ?? $s->payout_cadence) . ')',
                        ])
                        ->toArray())
                    ->default(fn () => Streamer::where('status', 'active')
                        ->where('payout_cadence', 'weekly')
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
        $start = Carbon::parse($data['week_start'])->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);
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
