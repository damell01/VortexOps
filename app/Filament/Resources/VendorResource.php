<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\VendorResource\Pages;
use App\Models\Vendor;
use App\Support\AdminModules;
use App\Support\StatusColor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class VendorResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug  = 'purchasing';

    protected static ?string $model = Vendor::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-building-storefront';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('purchasing');
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'contact_name'];
    }

    /**
     * Soft-deletable and every FK to vendors (pallets, product_identities,
     * receiving_sessions) is nullOnDelete — no data is destroyed either way.
     * Still block while it has pallets so historical PO vendor attribution
     * isn't silently orphaned by a stray delete.
     */
    public static function canDelete(Model $record): bool
    {
        return (auth()->user()?->isAdmin() ?? false) && ! $record->pallets()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->name;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Vendor Details')
                ->description('Supplier information and contact details')
                ->columnSpanFull()->schema([
                Grid::make(3)->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('contact_name')
                        ->label('Contact Name')
                        ->maxLength(255),
                    TextInput::make('email')
                        ->email()
                        ->maxLength(255),
                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(50),
                    TextInput::make('website')
                        ->url()
                        ->maxLength(255),
                    TextInput::make('account_number')
                        ->label('Account #')
                        ->maxLength(100),
                    TextInput::make('lead_time_days')
                        ->label('Lead Time (days)')
                        ->helperText('Typical days from order to delivery — feeds reorder suggestions on Product Insights.')
                        ->numeric()
                        ->minValue(0),
                    Select::make('status')
                        ->options(Vendor::statusLabels())
                        ->default('active')
                        ->required(),
                ]),
                Textarea::make('notes')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('contact_name')
                    ->label('Contact')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => StatusColor::for($state)),
                TextColumn::make('pallets_count')
                    ->counts('pallets')
                    ->label('Pallets')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateIcon('heroicon-o-building-storefront')
            ->emptyStateHeading('No vendors yet')
            ->emptyStateDescription('Add the suppliers you buy inventory from so you can track pallets and costs against them.')
            ->emptyStateActions([
                \Filament\Actions\CreateAction::make()->label('Add a vendor'),
            ])
            ->filters([
                SelectFilter::make('status')->options(Vendor::statusLabels()),
            ])
            ->actions([
                ViewAction::make()
                    ->size('sm')
                    ->iconButton(),
                EditAction::make()
                    ->size('sm')
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (Vendor $record) => static::canDelete($record))
                    ->tooltip(fn (Vendor $record) => static::canDelete($record) ? null : 'Has pallets on record — can\'t be deleted while those exist.'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            $deletable = $records->filter(fn (Vendor $record) => static::canDelete($record));
                            $blocked   = $records->count() - $deletable->count();

                            $deletable->each->delete();

                            if ($blocked > 0) {
                                Notification::make()
                                    ->title($deletable->count() . ' vendor(s) deleted')
                                    ->body("{$blocked} skipped — still have pallets on record.")
                                    ->warning()
                                    ->send();
                            } else {
                                Notification::make()->title($deletable->count() . ' vendor(s) deleted')->success()->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('name')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVendors::route('/'),
            'create' => Pages\CreateVendor::route('/create'),
            'view'   => Pages\ViewVendor::route('/{record}'),
            'edit'   => Pages\EditVendor::route('/{record}/edit'),
        ];
    }
}
