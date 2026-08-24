<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasAdminNavVisibility;
use App\Filament\Resources\ProductIdentityResource\Pages;
use App\Models\InventoryItem;
use App\Models\ProductIdentity;
use App\Support\AdminModules;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class ProductIdentityResource extends Resource
{
    use HasAdminNavVisibility;
    protected static ?string $model = ProductIdentity::class;

    protected static ?string $navigationLabel = 'Alias Manager';

    protected static ?string $label = 'Alias';

    protected static ?string $pluralLabel = 'Aliases';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-tag';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('streams');
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function canAccess(): bool
    {
        // An explicit grant on Roles & Permissions is the answer; the rules
        // below are the fallback for roles that have no explicit list.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        return (auth()->user()?->isAdmin() || auth()->user()?->isOwner())
            && AdminModules::isEnabled('streams');
    }

    // No shouldRegisterNavigation() override: it deferred to canAccess(), but
    // this resource writes its own canAccess() which does not consult
    // NavVisibility — so the link ignored the hide setting on Roles &
    // Permissions. The trait checks both.

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->modifyQueryUsing(fn ($query) => $query->where('type', ProductIdentity::TYPE_ALIAS)->with(['product', 'vendor', 'confirmedByUser']))
            ->columns([
                TextColumn::make('value')
                    ->label('Alias (Vendor Description)')
                    ->searchable()
                    ->wrap()
                    ->weight('semibold')
                    ->description(fn (ProductIdentity $record) => $record->vendor?->name),

                TextColumn::make('product.name')
                    ->label('Maps To (Product)')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('auto_confidence')
                    ->label('Confidence')
                    ->formatStateUsing(fn ($state) => $state !== null ? round((float) $state * 100) . '%' : '—')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === null           => 'gray',
                        (float) $state >= 0.90    => 'success',
                        (float) $state >= 0.70    => 'warning',
                        default                   => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('times_confirmed')
                    ->label('Confirmed')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                TextColumn::make('confirmedByUser.name')
                    ->label('Last By')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_confirmed_at')
                    ->label('Last Confirmed')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Learned')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('vendor')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->searchable(),
            ])
            ->actions([
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('reassign_to_product')
                        ->label('Reassign to Product')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('info')
                        ->form([
                            Select::make('product_id')
                                ->label('Move selected aliases to')
                                ->searchable()
                                ->required()
                                ->getSearchResultsUsing(fn (string $search) => InventoryItem::where(fn ($q) => $q
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('sku', 'like', "%{$search}%"))
                                    ->orderBy('name')
                                    ->limit(30)
                                    ->pluck('name', 'id')
                                    ->toArray())
                                ->getOptionLabelUsing(fn ($value) => InventoryItem::find($value)?->name ?? $value),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update(['product_id' => $data['product_id']]);
                            Notification::make()
                                ->title($records->count() . ' alias(es) reassigned')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading('No aliases learned yet')
            ->emptyStateDescription('Aliases are created automatically when AI matches are confirmed — either by Fast-Track Approve or by manually correcting an item in a deduction request.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductIdentities::route('/'),
        ];
    }
}
