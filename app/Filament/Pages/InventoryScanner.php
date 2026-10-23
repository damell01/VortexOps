<?php

namespace App\Filament\Pages;

// Restored temporarily - full file uploaded in next commit if needed.
// See Production branch for complete InventoryScanner.
class InventoryScanner extends \Filament\Pages\Page
{
    protected static ?string $title = 'Inventory Scanner';

    public function getView(): string
    {
        return 'filament.pages.inventory-scanner-enhanced';
    }
}
