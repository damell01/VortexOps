<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Services\InventoryExportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;

    public function getSubheading(): ?string
    {
        return 'The product catalogue — SKUs, barcodes, reorder levels, and current stock. Cost updates automatically (weighted average) every time you receive inventory.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view-report')
                ->label('👁 View Report')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(route('export.inventory-pdf'))
                ->openUrlInNewTab()
                ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isOwner()),
            Action::make('export-pdf')
                ->label('📄 Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('warning')
                ->url(route('export.inventory-pdf') . '?download=1')
                ->openUrlInNewTab()
                ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isOwner()),
            Action::make('export-excel')
                ->label('📊 Export Excel')
                ->icon('heroicon-o-table-cells')
                ->color('primary')
                ->url(route('export.inventory-items'))
                ->openUrlInNewTab()
                ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isOwner()),
            Action::make('quick-add')
                ->label('Quick Add')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->url(fn () => InventoryItemResource::getUrl('quick-add'))
                ->visible(function () {
                    $user = auth()->user();
                    return ($user?->isAdmin() ?? false) || ($user?->isOwner() ?? false) || ($user?->isStreamer() ?? false);
                }),
            CreateAction::make()
                ->label('+ Full Form')
                ->color('gray')
                ->visible(function () {
                    $user = auth()->user();
                    return ($user?->isAdmin() ?? false) || ($user?->isOwner() ?? false) || ($user?->isStreamer() ?? false);
                })
        ];
    }

}
