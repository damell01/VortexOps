<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Resources\WeeklyPayoutBatchResource;
use App\Models\WeeklyPayoutBatch;
use Carbon\Carbon;
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
                    ->columnSpanFull(),

                \Filament\Forms\Components\Select::make('pay_run_type_id')
                    ->label('Pay run type')
                    ->options(fn () => \App\Models\PayRunType::where('active', true)->orderBy('name')->pluck('name', 'id'))
                    ->placeholder('One-off custom run')
                    ->helperText('Choose a reusable type or set up a one-off run. Manage types under Rates & Adjustments.')
                    ->live()
                    ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                        $type = filled($state) ? \App\Models\PayRunType::where('active', true)->find($state) : null;
                        $set('kind', $type?->kind ?? '');
                        $set('dates_mode', 'custom');
                    }),
                \Filament\Forms\Components\Select::make('kind')
                    ->label('What this run pays')
                    ->options(['' => 'Wages + profit share'] + \App\Support\PayRunSchedules::KINDS)
                    ->default('')
                    ->selectablePlaceholder(false)
                    ->disabled(fn (\Filament\Schemas\Components\Utilities\Get $get) => filled($get('pay_run_type_id')))
                    ->dehydrated()
                    ->helperText('Wages and profit share are always separate lines.'),
                \Filament\Forms\Components\Radio::make('dates_mode')
                    ->label('Dates')
                    ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                        $type = \App\Models\PayRunType::find($get('pay_run_type_id'));
                        return $type?->schedule_enabled ? ['custom' => 'Custom dates', 'schedule' => 'Use type schedule'] : ['custom' => 'Custom dates'];
                    })
                    ->default('custom')->inline()->live(),
                DatePicker::make('week_start')
                    ->label(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('dates_mode') === 'schedule' ? 'Any day in the scheduled period' : 'Start date')
                    ->required()->default(fn () => now()->toDateString()),
                DatePicker::make('week_end')
                    ->label('End date')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('dates_mode') !== 'schedule')
                    ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('dates_mode') !== 'schedule')
                    ->default(fn () => now()->toDateString())
                    ->afterOrEqual('week_start'),

                Textarea::make('notes')
                    ->label('Notes (optional)')
                    ->rows(2)
                    ->placeholder('e.g. Holiday week, bonus included…')
                    ->columnSpanFull(),
            ]),

        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $businessId = filled($data['business_id'] ?? null) ? (int) $data['business_id'] : null;
            $type = filled($data['pay_run_type_id'] ?? null)
                ? \App\Models\PayRunType::where('active', true)->find($data['pay_run_type_id']) : null;
            if (filled($data['pay_run_type_id'] ?? null) && ! $type) {
                throw ValidationException::withMessages(['data.pay_run_type_id' => 'Choose an available pay run type.']);
            }
            $kind = $type ? $type->kind : (filled($data['kind'] ?? null) ? $data['kind'] : null);
            if ($kind !== null && ! array_key_exists($kind, \App\Support\PayRunSchedules::KINDS)) {
                throw ValidationException::withMessages(['data.kind' => 'Choose what this run pays.']);
            }
            if (($data['dates_mode'] ?? 'custom') === 'schedule') {
                if (! $type?->schedule_enabled) {
                    throw ValidationException::withMessages(['data.dates_mode' => 'This type has no schedule. Choose custom dates.']);
                }
                [$start, $end] = \App\Support\PayRunSchedules::standardPeriod($kind ?? 'wages', $data['week_start'], $type->scheduleConfig());
            } else {
                $start = Carbon::parse($data['week_start'])->startOfDay();
                $end = Carbon::parse($data['week_end'])->startOfDay();
            }
            if ($end->lt($start)) {
                throw ValidationException::withMessages(['data.week_end' => 'End date must be on or after start date.']);
            }
            $overlap = WeeklyPayoutBatch::overlapping($start->toDateString(), $end->toDateString(), null, $businessId, $kind);
            if ($overlap) {
                throw ValidationException::withMessages(['data.week_start' => 'These dates overlap ' . $overlap->kindLabel() . ' Pay Run #' . $overlap->id . '. Open that run or choose different dates.']);
            }
            $batch = WeeklyPayoutBatch::create([
                'business_id' => $businessId, 'kind' => $kind,
                'pay_run_type_id' => $type?->id, 'pay_run_type_name' => $type?->name,
                'week_start' => $start->toDateString(), 'week_end' => $end->toDateString(),
                'status' => 'draft', 'notes' => $data['notes'] ?? null, 'created_by' => auth()->id(),
            ]);
            $result = app(\App\Services\PayRunAutomationService::class)->syncWeek($start, true, $businessId, $kind);
            Notification::make()->title($batch->kindLabel() . ' pay run created')
                ->body($result['payouts_attached'] . ' pay line(s) added.' . ($result['warnings'] ? ' Review items needing attention on the run.' : ''))
                ->success()->send();
            return $result['batch'];
        });
    }

    protected function getRedirectUrl(): string
    {
        return WeeklyPayoutBatchResource::getUrl('view', ['record' => $this->record]);
    }
}
