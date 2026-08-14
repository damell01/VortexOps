<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;

    /** Per-request memo — the view reads the stats once, but renders are cheap to repeat. */
    protected ?array $statsMemo = null;

    public function getView(): string
    {
        return 'filament.resources.inventory-item-resource.pages.list-inventory-items';
    }

    public function getTitle(): string
    {
        return 'Inventory';
    }

    public function getSubheading(): ?string
    {
        return 'Manage and track all of your inventory items.';
    }

    /** The title says where you are; the crumb trail above it was one row of noise. */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * Headline counts for the tiles above the table.
     *
     * Scoped through the resource's own query so a streamer sees counts for
     * their locations only, matching the rows they can actually see.
     */
    public function getStats(): array
    {
        if ($this->statsMemo !== null) {
            return $this->statsMemo;
        }

        $items = InventoryItemResource::getEloquentQuery()
            ->get(['products.id', 'products.reorder_level']);

        $total  = $items->count();
        $out    = 0;
        $low    = 0;

        foreach ($items as $item) {
            $onHand = (float) ($item->stock_sum_quantity ?? 0);
            $reorder = $item->reorder_level;

            if ($onHand <= 0) {
                $out++;
            } elseif ($reorder !== null && $onHand <= (float) $reorder) {
                $low++;
            }
        }

        $inStock = $total - $out - $low;
        $share = fn (int $n) => $total > 0 ? round(($n / $total) * 100, 1) . '% of total' : '—';

        return $this->statsMemo = [
            [
                'label' => 'Total Items',
                'value' => number_format($total),
                'sub'   => 'All inventory items',
                'icon'  => 'heroicon-o-cube',
                'tone'  => 'purple',
            ],
            [
                'label' => 'In Stock',
                'value' => number_format($inStock),
                'sub'   => $share($inStock),
                'icon'  => 'heroicon-o-check-circle',
                'tone'  => 'green',
            ],
            [
                'label' => 'Low Stock',
                'value' => number_format($low),
                'sub'   => $share($low),
                'icon'  => 'heroicon-o-exclamation-circle',
                'tone'  => 'amber',
            ],
            [
                'label' => 'Out of Stock',
                'value' => number_format($out),
                'sub'   => $share($out),
                'icon'  => 'heroicon-o-x-circle',
                'tone'  => 'red',
            ],
        ];
    }

    protected function getHeaderActions(): array
    {
        $canExport = fn () => auth()->user()?->isAdmin() || auth()->user()?->isOwner();

        $canCreate = function () {
            $user = auth()->user();

            return ($user?->isAdmin() ?? false)
                || ($user?->isOwner() ?? false)
                || ($user?->isStreamer() ?? false);
        };

        return [
            // Three separate export buttons crowded the header; they're one
            // icon button now, matching the download control in the design.
            ActionGroup::make([
                Action::make('view-report')
                    ->label('View report')
                    ->icon('heroicon-o-eye')
                    ->url(route('export.inventory-pdf'))
                    ->openUrlInNewTab(),
                Action::make('export-pdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(route('export.inventory-pdf') . '?download=1')
                    ->openUrlInNewTab(),
                Action::make('export-excel')
                    ->label('Export to Excel')
                    ->icon('heroicon-o-table-cells')
                    ->url(route('export.inventory-items'))
                    ->openUrlInNewTab(),
            ])
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->button()
                ->color('gray')
                ->visible($canExport),

            Action::make('quick-add')
                ->label('Quick Add')
                ->icon('heroicon-o-bolt')
                ->color('gray')
                ->url(fn () => InventoryItemResource::getUrl('quick-add'))
                ->visible($canCreate),

            CreateAction::make()
                ->label('Add Item')
                ->icon('heroicon-m-plus')
                ->visible($canCreate),
        ];
    }
}
