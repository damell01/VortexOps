<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Concerns\HasAdminNavVisibility;
use App\Filament\Resources\StreamerResource\Pages;
use App\Filament\Resources\StreamerResource\RelationManagers\LoansRelationManager;
use App\Models\Streamer;
use App\Support\AdminModules;
use Filament\Forms\Components\Repeater;
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
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StreamerResource extends Resource
{
    use HasModuleAccess, HasAdminNavVisibility;

    protected static string $moduleSlug  = 'operations';
    protected static string $featureSlug = 'streamers';

    protected static ?string $model = Streamer::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-user-group';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('operations');
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
                    // PWE + Labels fields
                    TextInput::make('pwe_rate')
                        ->label('PWE Rate ($ per package)')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0)
                        ->visible(fn ($get) => $get('payout_type') === 'pwe_labels'),
                    TextInput::make('label_rate')
                        ->label('Label Rate ($ per label)')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0)
                        ->visible(fn ($get) => $get('payout_type') === 'pwe_labels'),

                    // Hybrid fields (also uses hourly_rate and payout_percentage)
                    TextInput::make('hourly_rate')
                        ->numeric()
                        ->prefix('$')
                        ->suffix('/hr')
                        ->visible(fn ($get) => in_array($get('payout_type'), ['hourly', 'hybrid', 'pwe_labels'])),
                    TextInput::make('payout_percentage')
                        ->numeric()
                        ->suffix('%')
                        ->label(fn ($get) => $get('payout_type') === 'hybrid' ? 'Profit Share %' : 'Payout %')
                        ->visible(fn ($get) => in_array($get('payout_type'), ['profit_share', 'hybrid'])),
                    TextInput::make('package_rate')
                        ->numeric()
                        ->prefix('$')
                        ->required(fn ($get) => in_array($get('payout_type'), ['package', 'flat_rate']))
                        ->visible(fn ($get) => in_array($get('payout_type'), ['package', 'flat_rate'])),
                    Textarea::make('custom_payout_formula')
                        ->label('Custom Formula')
                        ->rows(4)
                        ->placeholder('streamer_share_net * 0.35 + tip_share')
                        ->helperText('Supported variables: gross_revenue, whatnot_net, streamer_share_net, units_sold, show_duration_hours, show_duration_minutes, tips, tip_share, payout_percentage, package_rate, hourly_rate, pwe_rate, label_rate. Operators: + - * / and parentheses.')
                        ->visible(fn ($get) => $get('payout_type') === 'custom_formula')
                        ->columnSpanFull(),
                    Toggle::make('include_tips')
                        ->default(true),
                    TextInput::make('adp_employee_id')
                        ->label('ADP Employee ID')
                        ->maxLength(100),
                ]),
            ]),

            Section::make('Burden Rate')
                ->description('Applied to base pay before tips/profit share in Hybrid model. Optional on all models.')
                ->collapsed()
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('burden_rate_type')
                            ->label('Burden Rate Type')
                            ->options(['percentage' => 'Percentage (%)', 'flat' => 'Flat Amount ($)'])
                            ->placeholder('No burden rate')
                            ->nullable()
                            ->live(),
                        TextInput::make('burden_rate_value')
                            ->label(fn ($get) => $get('burden_rate_type') === 'flat' ? 'Burden Amount ($)' : 'Burden Percentage (%)')
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn ($get) => ! empty($get('burden_rate_type'))),
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

            Section::make('Channel Routing')
                ->description('Map each channel to a specific bank account for payout splits. The routing_bank_label on each payout is set from this table.')
                ->collapsed()
                ->schema([
                    Repeater::make('channel_routing_rules')
                        ->label('')
                        ->schema([
                            TextInput::make('channel')
                                ->label('Channel Name')
                                ->placeholder('e.g. Breaks')
                                ->required()
                                ->maxLength(100),
                            TextInput::make('bank_label')
                                ->label('Bank / Account Label')
                                ->placeholder('e.g. Chase Business x1234')
                                ->required()
                                ->maxLength(255),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->cloneable()
                        ->addActionLabel('Add routing rule')
                        ->itemLabel(fn (array $state): ?string =>
                            ($state['channel'] ?? null)
                                ? ($state['channel'] . ' → ' . ($state['bank_label'] ?? '?'))
                                : null
                        )
                        ->collapsible()
                        ->columnSpanFull()
                        ->defaultItems(0),
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
            ->deferLoading()
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
                        'profit_share'   => 'success',
                        'package'        => 'info',
                        'hourly'         => 'warning',
                        'flat_rate'      => 'gray',
                        'pwe_labels'     => 'info',
                        'hybrid'         => 'primary',
                        'custom_formula' => 'primary',
                        default          => 'gray',
                    }),
                TextColumn::make('total_earnings_due')
                    ->label('Due')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_earnings_paid')
                    ->label('Paid')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading('No streamers yet')
            ->emptyStateDescription('Add the people you run breaks with — their payout terms, channel routing, and inventory location.')
            ->emptyStateActions([
                \Filament\Actions\CreateAction::make()->label('Add your first streamer'),
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
                    ExportBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->persistFiltersInSession()
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
