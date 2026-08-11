<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\MissingItemReportResource\Pages;
use App\Models\MissingItemReport;
use App\Models\Product;
use App\Support\AdminModules;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MissingItemReportResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'purchasing';

    protected static ?string $model = MissingItemReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = 'Missing Items';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('purchasing');
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['pallet.vendor', 'inventoryItem', 'reportedBy'])
            ->orderByDesc('created_at');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Report Details')
                ->columns(2)
                ->schema([
                    Select::make('pallet_id')
                        ->label('Pallet')
                        ->relationship('pallet', 'reference')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('inventory_item_id')
                        ->label('Missing Item')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Product::where('is_active', true)
                            ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                            ->orderBy('name')
                            ->limit(30)
                            ->pluck('name', 'id')
                            ->toArray())
                        ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name)
                        ->required(),
                    TextInput::make('expected_quantity')
                        ->label('Expected Quantity')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->columnSpan(1),
                    TextInput::make('unit_cost')
                        ->label('Unit Cost ($)')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0)
                        ->columnSpan(1),
                ]),

            Section::make('Notes')
                ->columnSpanFull()
                ->schema([
                    Textarea::make('notes')
                        ->label('Reason for Missing Items')
                        ->placeholder('e.g., Damaged in shipping, Quantity shortage, Billing mismatch...')
                        ->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pallet.reference')
                    ->label('Pallet Ref')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pallet.vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('inventoryItem.name')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('inventoryItem.sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expected_quantity')
                    ->label('Qty Missing')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('total_value')
                    ->label('Total Value')
                    ->state(function (MissingItemReport $record) {
                        $qty  = $record->expected_quantity ?? 0;
                        $cost = (float) ($record->unit_cost ?? 0);
                        return $qty * $cost;
                    })
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 2))
                    ->sortable(),
                TextColumn::make('reportedBy.name')
                    ->label('Reported By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Reported At')
                    ->dateTime('M d, Y g:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->actions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMissingItemReports::route('/'),
            'create' => Pages\CreateMissingItemReport::route('/create'),
            'edit'   => Pages\EditMissingItemReport::route('/{record}/edit'),
        ];
    }
}
