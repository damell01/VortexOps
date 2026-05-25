<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StreamerResource\Pages;
use App\Filament\Resources\StreamerResource\RelationManagers\LoansRelationManager;
use App\Models\Streamer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StreamerResource extends Resource
{
    protected static ?string $model = Streamer::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-user-group';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Operations';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'legal_name', 'email'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            'Email'       => $record->email,
            'Payout Type' => Streamer::payoutTypeLabels()[$record->payout_type] ?? $record->payout_type,
        ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withCount('inventoryLocations');
    }

    // Streamers cannot manage other streamers
    public static function canCreate(): bool    { return auth()->user()?->isAdmin() ?? false; }
    public static function canEdit($r): bool    { return auth()->user()?->isAdmin() ?? false; }
    public static function canDelete($r): bool  { return auth()->user()?->isAdmin() ?? false; }
    public static function canDeleteAny(): bool { return auth()->user()?->isAdmin() ?? false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Basic Information')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('legal_name')
                        ->maxLength(255),
                    TextInput::make('email')
                        ->email()
                        ->maxLength(255),
                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(50),
                ]),
            ]),

            Section::make('Payout Configuration')->schema([
                Grid::make(2)->schema([
                    Select::make('payout_type')
                        ->options(Streamer::payoutTypeLabels())
                        ->required()
                        ->live(),
                    TextInput::make('payout_percentage')
                        ->numeric()
                        ->suffix('%')
                        ->visible(fn ($get) => $get('payout_type') === 'profit_share'),
                    TextInput::make('package_rate')
                        ->numeric()
                        ->prefix('$')
                        ->visible(fn ($get) => $get('payout_type') === 'package'),
                    TextInput::make('hourly_rate')
                        ->numeric()
                        ->prefix('$')
                        ->suffix('/hr')
                        ->visible(fn ($get) => $get('payout_type') === 'hourly'),
                    Toggle::make('include_tips')
                        ->default(true),
                    TextInput::make('adp_employee_id')
                        ->label('ADP Employee ID')
                        ->maxLength(100),
                ]),
            ]),

            Section::make('Owner Fee')->schema([
                Grid::make(3)->schema([
                    Select::make('owner_fee_type')
                        ->label('Fee Type')
                        ->options(Streamer::ownerFeeTypeLabels())
                        ->placeholder('No owner fee')
                        ->nullable()
                        ->live(),
                    TextInput::make('owner_fee_value')
                        ->label(fn ($get) => $get('owner_fee_type') === 'flat' ? 'Fee Amount ($)' : 'Fee Percentage (%)')
                        ->numeric()
                        ->minValue(0)
                        ->nullable()
                        ->visible(fn ($get) => ! empty($get('owner_fee_type'))),
                    Toggle::make('owner_fee_deduct_from_payout')
                        ->label('Deduct from payout')
                        ->helperText('On: reduces calculated payout. Off: tracked separately.')
                        ->visible(fn ($get) => ! empty($get('owner_fee_type'))),
                ]),
            ]),

            Section::make('Status & Notes')->schema([
                Grid::make(2)->schema([
                    Select::make('status')
                        ->options(Streamer::statusLabels())
                        ->required()
                        ->default('active'),
                ]),
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('payout_type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Streamer::payoutTypeLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'profit_share' => 'success',
                        'package' => 'info',
                        'hourly' => 'warning',
                        'flat_rate' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Streamer::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'on_leave' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('include_tips')
                    ->boolean()
                    ->label('Tips'),
                TextColumn::make('inventoryLocations_count')
                    ->counts('inventoryLocations')
                    ->label('Locations'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Streamer::statusLabels()),
                SelectFilter::make('payout_type')
                    ->options(Streamer::payoutTypeLabels()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->persistFiltersInSession()
            ->stackedOnMobile()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            LoansRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStreamers::route('/'),
            'create' => Pages\CreateStreamer::route('/create'),
            'view' => Pages\ViewStreamer::route('/{record}'),
            'edit' => Pages\EditStreamer::route('/{record}/edit'),
        ];
    }
}
