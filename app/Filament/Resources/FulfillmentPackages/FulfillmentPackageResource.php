<?php

namespace App\Filament\Resources\FulfillmentPackages;

use App\Filament\Resources\FulfillmentPackages\Pages\CreateFulfillmentPackage;
use App\Filament\Resources\FulfillmentPackages\Pages\EditFulfillmentPackage;
use App\Filament\Resources\FulfillmentPackages\Pages\ListFulfillmentPackages;
use App\Filament\Resources\FulfillmentPackages\Schemas\FulfillmentPackageForm;
use App\Filament\Resources\FulfillmentPackages\Tables\FulfillmentPackagesTable;
use App\Models\FulfillmentPackage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FulfillmentPackageResource extends Resource
{
    protected static ?string $model = FulfillmentPackage::class;

    protected static ?string $navigationLabel = 'Fulfillment Packages';

    protected static ?string $navigationGroup = 'Fulfillment';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return FulfillmentPackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FulfillmentPackagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFulfillmentPackages::route('/'),
            'create' => CreateFulfillmentPackage::route('/create'),
            'edit' => EditFulfillmentPackage::route('/{record}/edit'),
        ];
    }
}
