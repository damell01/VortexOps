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
                \Filament\Forms\Components\Select::make('business_id')
                    ->label('Business')
                    ->options(fn () => \App\Support\BusinessContext::available()->pluck('name', 'id')->all())
                    ->placeholder('All businesses (one run for everyone)')
                    ->default(fn () => \App\Support\BusinessContext::currentId())
                    ->required(fn () => \App\Support\BusinessContext::inUse())
                    ->helperText('Each business runs its own payroll. Only people in this business are paid in this run.')
                    ->live()
                    ->afterStateUpdated(fn ($state, \Filament\Schemas\Components\Utilities\Set $set) => $set('streamer_ids', self::memberQuery($state)->pluck('id')->all()))
                    ->columnSpanFull(),

                \Filament\Forms\Components\Select::make('kind')
                    ->label('What this run pays')
                    ->options(fn () => ['' => 'All pay'] + \App\Support\PayRunSchedules::KINDS)
                    ->default(fn () => \App\Support\PayRunSchedules::separate() ? 'wages' : '')
                    ->selectablePlaceholder(false)
                    ->helperText('Profit share and wages can run on their own schedules — set them under Rates & Adjustments → Pay run schedules.')
                    ->live(),

                \Filament\Forms\Components\Radio::make('dates_mode')
                    ->label('Dates')
                    ->options(['schedule' => 'The scheduled period', 'custom' => 'Custom dates'])
                    ->default('schedule')
                    ->inline()
                    ->live(),

                DatePicker::make('week_start')
                    ->label(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('dates_mode') === 'custom' ? 'Start date' : 'Any day in the period')
                    ->helperText(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('dates_mode') === 'custom'
                        ? 'The run covers exactly these dates. Runs that pay the same thing cannot overlap.'
                        : 'The run covers the scheduled period containing this date ('.(\App\Support\PayRunSchedules::FREQUENCIES[filled($get('kind')) ? \App\Support\PayRunSchedules::config()[$get('kind')]['frequency'] : 'weekly'] ?? 'Every week').'). Runs that pay the same thing cannot overlap.')
                    ->required()
                    ->default(fn () => \App\Support\PaySchedule::periodFor(now())[0]),

                DatePicker::make('week_end')
                    ->label('End date')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('dates_mode') === 'custom')
                    ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('dates_mode') === 'custom')
                    ->afterOrEqual('week_start'),

                Textarea::make('notes')
                    ->label('Notes (optional)')
                    ->rows(2)
                    ->placeholder('e.g. Holiday week, bonus included…')
                    ->columnSpanFull(),
            ]),

            Section::make('Team Members to Include')->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => blank($get('kind')) && $get('dates_mode') !== 'custom')->schema([
                CheckboxList::make('streamer_ids')
                    ->label('Active Team Members')
                    ->helperText('All active team members are included by default. Show-based payouts still have to pass the show/admin/fulfillment readiness gates before finalization.')
                    ->options(fn (\Filament\Schemas\Components\Utilities\Get $get) => self::memberQuery($get('business_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->default(fn () => self::memberQuery(\App\Support\BusinessContext::currentId())
                        ->pluck('id')
                        ->toArray())
                    ->columns(2)
                    ->bulkToggleable()
                    ->required(),
            ]),
        ]);
    }

    private static function memberQuery(mixed $businessId): \Illuminate\Database\Eloquent\Builder
    {
        return Streamer::where('status', 'active')
            ->when(filled($businessId), fn ($q) => $q->where('business_id', (int) $businessId));
    }

    protected function handleRecordCreation(array $data): Model
    {
        $businessId = filled($data['business_id'] ?? null) ? (int) $data['business_id'] : null;
        $kind = filled($data['kind'] ?? null) && array_key_exists($data['kind'], \App\Support\PayRunSchedules::KINDS) ? $data['kind'] : null;
        $custom = ($data['dates_mode'] ?? 'schedule') === 'custom';

        [$start, $end] = $custom
            ? [\Carbon\Carbon::parse($data['week_start'])->startOfDay(), \Carbon\Carbon::parse($data['week_end'])->startOfDay()]
            : \App\Support\PayRunSchedules::periodFor($kind, $data['week_start'], $businessId);
        $overlap = WeeklyPayoutBatch::overlapping($start->toDateString(), $end->toDateString(), null, $businessId, $kind);

        if ($overlap) {
            throw ValidationException::withMessages([
                'data.week_start' => 'Those dates overlap ' . $overlap->kindLabel() . ' Pay Run #' . $overlap->id
                    . ' (' . $overlap->week_start->format('M j') . '–' . $overlap->week_end->format('M j, Y') . '). Open the existing Pay Run instead.',
            ]);
        }

        // A typed run, or one with custom dates, is filled the way the
        // schedule fills runs: every ready pay line of that type in its dates.
        if ($kind !== null || $custom) {
            $batch = WeeklyPayoutBatch::create([
                'business_id' => $businessId,
                'kind' => $kind,
                'week_start' => $start->toDateString(),
                'week_end' => $end->toDateString(),
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $result = app(\App\Services\PayRunAutomationService::class)->syncWeek($start, true, $businessId, $kind);
            $batch = $result['batch'];

            Notification::make()
                ->title($batch->kindLabel() . ' pay run created — ' . $batch->week_start->format('M j') . ' to ' . $batch->week_end->format('M j, Y'))
                ->body($result['payouts_attached'] . ' pay line(s) added.' . ($result['warnings'] ? ' ' . count($result['warnings']) . ' item(s) need attention on the run.' : ''))
                ->success()
                ->send();

            return $batch;
        }

        $batch = app(PayoutService::class)->createManualBatch(
            weekStart: $start->toDateString(),
            streamerIds: $data['streamer_ids'] ?? [],
            notes: $data['notes'] ?? null,
            businessId: $businessId,
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
