<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StreamerLogResource\Pages;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Services\FeatureFlagService;
use App\Services\ShippingSurchargeService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StreamerLogResource extends Resource
{
    protected static ?string $model = StreamerLogEntry::class;

    protected static ?string $navigationLabel = 'Streamer Log';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Streams';
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return FeatureFlagService::enabled('streamer_log')
            && ($user?->isAdmin() || $user?->isOwner());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Show Info')->schema([
                Grid::make(2)->schema([
                    Toggle::make('hard_copy')
                        ->label('Hard Copy'),
                    TextInput::make('hours_streamed')
                        ->label('Hours Streamed')
                        ->numeric()
                        ->step(0.25),
                    TextInput::make('number_of_shipments')
                        ->label('Number of Shipments')
                        ->integer(),
                    TextInput::make('number_of_packages_over_500')
                        ->label('Packages Over $500')
                        ->integer(),
                ]),
            ]),

            Section::make('Pay Breakdown')->schema([
                Grid::make(2)->schema([
                    TextInput::make('pwe_pay')
                        ->label('PWE Pay')
                        ->numeric()
                        ->prefix('$'),
                    TextInput::make('hourly_pay')
                        ->label('Hourly Pay')
                        ->numeric()
                        ->prefix('$'),
                    TextInput::make('profit_share_amount')
                        ->label('Profit Share Amount')
                        ->numeric()
                        ->prefix('$'),
                    Toggle::make('profit_share_paid')
                        ->label('Profit Share Paid'),
                    TextInput::make('tips_paid')
                        ->label('Tips Paid')
                        ->numeric()
                        ->prefix('$'),
                    TextInput::make('total_due')
                        ->label('Total Due')
                        ->numeric()
                        ->prefix('$'),
                    TextInput::make('total_paid')
                        ->label('Total Paid')
                        ->numeric()
                        ->prefix('$'),
                    TextInput::make('business_net_rev')
                        ->label('Business Net Rev')
                        ->numeric()
                        ->prefix('$'),
                ]),
            ]),

            Section::make('Notes')->schema([
                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('show.show_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('show.title')
                    ->label('Show')
                    ->limit(35)
                    ->searchable(),
                TextColumn::make('streamer.name')
                    ->label('Streamer')
                    ->searchable(),
                IconColumn::make('hard_copy')
                    ->label('Hard Copy')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('number_of_shipments')
                    ->label('Shipments')
                    ->numeric(),
                TextColumn::make('hours_streamed')
                    ->label('Hours')
                    ->numeric(),
                TextColumn::make('total_due')
                    ->label('Total Due')
                    ->money('USD'),
                TextColumn::make('total_paid')
                    ->label('Total Paid')
                    ->money('USD'),
                TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Reviewed' : 'Pending')
                    ->color(fn ($state) => $state ? 'success' : 'warning'),
            ])
            ->filters([
                SelectFilter::make('reviewed')
                    ->options(['yes' => 'Reviewed', 'no' => 'Pending'])
                    ->query(function (Builder $query, array $data): Builder {
                        if (($data['value'] ?? null) === 'yes') {
                            return $query->whereNotNull('reviewed_at');
                        }
                        if (($data['value'] ?? null) === 'no') {
                            return $query->whereNull('reviewed_at');
                        }
                        return $query;
                    }),
                SelectFilter::make('streamer_id')
                    ->label('Streamer')
                    ->options(Streamer::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                Filter::make('show_date')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'],  fn ($q) => $q->whereHas('show', fn ($s) => $s->where('show_date', '>=', $data['from'])))
                            ->when($data['until'], fn ($q) => $q->whereHas('show', fn ($s) => $s->where('show_date', '<=', $data['until'])));
                    }),
            ])
            ->actions([
                Action::make('mark_reviewed')
                    ->label('Mark Reviewed')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (StreamerLogEntry $record) => $record->reviewed_at === null)
                    ->action(function (StreamerLogEntry $record): void {
                        $record->reviewed_by = auth()->id();
                        $record->reviewed_at = now();
                        $record->save();

                        Notification::make()
                            ->title('Log entry marked as reviewed')
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->after(function (StreamerLogEntry $record): void {
                        // If packages over 500 were set and show has a primary streamer, create/update surcharge
                        if (
                            ($record->number_of_packages_over_500 ?? 0) > 0
                            && $record->show
                            && $record->streamer
                        ) {
                            app(ShippingSurchargeService::class)->createForShow(
                                $record->show,
                                $record->streamer,
                                $record->number_of_packages_over_500,
                                "Auto from streamer log #{$record->id}"
                            );
                        }
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->deferLoading()
            ->persistFiltersInSession();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['show', 'streamer']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStreamerLogEntries::route('/'),
            'edit'  => Pages\EditStreamerLogEntry::route('/{record}/edit'),
        ];
    }
}
