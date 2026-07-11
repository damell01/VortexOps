<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductIdentityResource\Pages;
use App\Models\ProductIdentity;
use App\Support\AdminModules;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductIdentityResource extends Resource
{
    protected static ?string $model = ProductIdentity::class;

    protected static ?string $navigationLabel = 'Alias Manager';

    protected static ?string $navigationParentItem = 'Shows';

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
        return (auth()->user()?->isAdmin() || auth()->user()?->isOwner())
            && AdminModules::isEnabled('streams');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function table(Table $table): Table
    {
        return $table
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
                BulkActionGroup::make([DeleteBulkAction::make()]),
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
