<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\InventoryLocationResource\Pages;
use App\Models\InventoryLocation;
use App\Support\AdminModules;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InventoryLocationResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';

    protected static ?string $model = InventoryLocation::class;

    // Streamers see only their own locations + shared locations (no streamer assigned)
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['streamer']);
        $user  = auth()->user();

        if ($user && $user->isStreamer() && ! $user->isAdmin()) {
            $streamerId = $user->streamer?->id;
            $query->where(function (Builder $q) use ($streamerId): void {
                $q->whereNull('streamer_id')
                  ->orWhere('streamer_id', $streamerId);
            });
        }

        return $query;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-map-pin';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            'Type'     => InventoryLocation::typeLabels()[$record->type] ?? $record->type,
            'Streamer' => $record->streamer?->name,
        ]);
    }

    public static function getNavigationLabel(): string
    {
        return 'Locations';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Location Details')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Select::make('type')
                        ->options(InventoryLocation::typeLabels())
                        ->required()
                        ->live(),
                    Select::make('streamer_id')
                        ->label('Assigned Streamer')
                        ->relationship('streamer', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->visible(fn ($get) => $get('type') === 'streamer_inventory'),
                    Select::make('status')
                        ->options(InventoryLocation::statusLabels())
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
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => InventoryLocation::typeLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'main_storage' => 'info',
                        'streamer_inventory' => 'success',
                        'returned' => 'warning',
                        'damaged' => 'danger',
                        'fulfillment' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('streamer.name')
                    ->label('Streamer')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('stock_count')
                    ->label('SKUs')
                    ->counts('stock'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => InventoryLocation::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(InventoryLocation::typeLabels()),
                SelectFilter::make('status')
                    ->options(InventoryLocation::statusLabels()),
                SelectFilter::make('streamer_id')
                    ->label('Streamer')
                    ->relationship('streamer', 'name'),
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
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryLocations::route('/'),
            'create' => Pages\CreateInventoryLocation::route('/create'),
            'view' => Pages\ViewInventoryLocation::route('/{record}'),
            'edit' => Pages\EditInventoryLocation::route('/{record}/edit'),
        ];
    }
}
