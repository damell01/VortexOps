<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryItem extends EditRecord
{
    protected static string $resource = InventoryItemResource::class;

    /** Organize the edit screen into jump-to sections without changing save behavior. */
    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Tabs::make('Product editor')->tabs([
                \Filament\Schemas\Components\Tabs\Tab::make('Basic Info')
                    ->icon('heroicon-o-identification')
                    ->schema([InventoryItemResource::itemIdentificationSection()]),
                \Filament\Schemas\Components\Tabs\Tab::make('Pricing')
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([InventoryItemResource::pricingSection()]),
                \Filament\Schemas\Components\Tabs\Tab::make('Product Details')
                    ->icon('heroicon-o-tag')
                    ->schema([InventoryItemResource::classificationSection(), InventoryItemResource::notesSection()]),
                \Filament\Schemas\Components\Tabs\Tab::make('Case / Container')
                    ->icon('heroicon-o-cube')
                    ->schema([InventoryItemResource::containerSettingsSection()]),
                \Filament\Schemas\Components\Tabs\Tab::make('Stock')
                    ->icon('heroicon-o-archive-box')
                    ->schema([InventoryItemResource::initialStockSection(), InventoryItemResource::stockByLocationSection()]),
            ])->columnSpanFull(),
        ]);
    }

    public function getView(): string
    {
        return 'filament.resources.inventory-item-resource.pages.edit-inventory-item';
    }

    public function getSubheading(): ?string
    {
        return 'Edit product details, barcode, case/container contents, sourcing, pricing, and reorder settings. Use Move or correct stock for quantity changes.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('View Item')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => InventoryItemResource::getUrl('view', ['record' => $this->record])),

            Action::make('stock')
                ->label('Move / Correct Stock')
                ->icon('heroicon-o-arrows-right-left')
                ->color('warning')
                ->url(fn () => InventoryItemResource::getUrl('stock', ['record' => $this->record])),

            InventoryItemResource::deleteItemAction(),
        ];
    }
}
