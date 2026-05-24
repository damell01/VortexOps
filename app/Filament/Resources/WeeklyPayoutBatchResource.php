<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WeeklyPayoutBatchResource\Pages;
use App\Models\WeeklyPayoutBatch;
use App\Services\PayoutService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WeeklyPayoutBatchResource extends Resource
{
    protected static ?string $model = WeeklyPayoutBatch::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-calendar-days';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Payouts & Pay Runs';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getModelLabel(): string
    {
        return 'Pay Run';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pay Runs';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('finalizedBy');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pay Run Details')->columns(2)->schema([
                DatePicker::make('week_start')
                    ->label('Week Start (Monday)')
                    ->required(),

                DatePicker::make('week_end')
                    ->label('Week End (Sunday)')
                    ->required(),

                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('week_start')
                    ->label('Week Of')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('week_end')
                    ->label('Week End')
                    ->date('M j, Y'),

                TextColumn::make('payouts_count')
                    ->counts('payouts')
                    ->label('Streamers'),

                TextColumn::make('total_payout')
                    ->label('Total Payout')
                    ->money('USD')
                    ->weight('bold'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => WeeklyPayoutBatch::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'draft'            => 'gray',
                        'finalized'        => 'info',
                        'submitted_to_adp' => 'warning',
                        'paid'             => 'success',
                        default            => 'gray',
                    }),

                TextColumn::make('finalizedBy.name')
                    ->label('Finalized By')
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('finalized_at')
                    ->label('Finalized')
                    ->dateTime('M j g:ia')
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->striped()
            ->deferLoading()
            ->defaultSort('week_start', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(WeeklyPayoutBatch::statusLabels()),
            ])
            ->actions([
                ViewAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWeeklyPayoutBatches::route('/'),
            'create' => Pages\CreateWeeklyPayoutBatch::route('/create'),
            'view'   => Pages\ViewWeeklyPayoutBatch::route('/{record}'),
        ];
    }
}
