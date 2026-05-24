<?php

namespace App\Jobs;

use App\Models\InventoryItem;
use App\Services\NotificationRouter;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendLowStockNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $itemId) {}

    public function handle(NotificationRouter $router): void
    {
        $item = InventoryItem::with('stock')->find($this->itemId);

        if (! $item || ! $item->isLowStock()) {
            return;
        }

        $qty = $item->totalQuantity();

        Notification::make()
            ->title('Low Stock: ' . $item->name)
            ->body(number_format($qty) . ' units remaining (reorder at ' . number_format((float) $item->reorder_level) . ')')
            ->warning()
            ->icon('heroicon-o-exclamation-triangle')
            ->sendToDatabase($router->getRecipients('low_stock'));
    }
}
