<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\NotificationRouter;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendLowStockNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $itemId) {}

    public function handle(NotificationRouter $router): void
    {
        $item = Product::with('stock')->find($this->itemId);

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

    public function failed(Throwable $e): void
    {
        Log::error('SendLowStockNotification failed', [
            'item_id' => $this->itemId,
            'error'   => $e->getMessage(),
        ]);
    }
}
