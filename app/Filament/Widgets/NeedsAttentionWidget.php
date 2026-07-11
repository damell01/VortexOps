<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\SystemHealth;
use App\Filament\Resources\DeductionRequestResource;
use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\ShowResource;
use App\Filament\Resources\StreamerLogResource;
use App\Models\DeductionRequest;
use App\Models\InventoryItem;
use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Support\AdminModules;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One place that answers "what needs me today?" — a compact list of actionable
 * items with counts and deep links. Each row only appears when its count > 0,
 * so a healthy install shows a friendly all-clear.
 */
class NeedsAttentionWidget extends Widget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Needs Attention';
    protected string $view = 'filament.widgets.needs-attention';

    public static function canView(): bool
    {
        return (auth()->user()?->isAdmin() || auth()->user()?->isOwner()) ?? false;
    }

    protected function getViewData(): array
    {
        return ['items' => $this->items()];
    }

    /** @return array<int,array<string,mixed>> */
    private function items(): array
    {
        $items = [];

        $add = function (bool $when, int $count, string $label, string $icon, string $color, string $url) use (&$items) {
            if ($when && $count > 0) {
                $items[] = compact('count', 'label', 'icon', 'color', 'url');
            }
        };

        try {
            $add(
                AdminModules::isEnabled('streamer_log') && Schema::hasTable('streamer_log_entries'),
                StreamerLogEntry::where('status', 'pending')->count(),
                'streamer logs awaiting review',
                'heroicon-o-clipboard-document-list',
                'warning',
                StreamerLogResource::getUrl(),
            );

            $add(
                AdminModules::isEnabled('streams') && Schema::hasColumn('shows', 'channel_attribution_suspect'),
                Show::where('channel_attribution_suspect', true)->count(),
                'shows need a channel confirmed',
                'heroicon-o-check-badge',
                'warning',
                ShowResource::getUrl('index', ['tableFilters[channel_attribution_suspect][value]' => '1']),
            );

            $add(
                Schema::hasTable('deduction_requests'),
                DeductionRequest::where('status', 'pending')->count(),
                'deduction requests pending approval',
                'heroicon-o-clipboard-document-check',
                'info',
                DeductionRequestResource::getUrl(),
            );

            $add(
                AdminModules::isEnabled('inventory') && Schema::hasTable('inventory_stock'),
                $this->lowStockCount(),
                'items at or below reorder level',
                'heroicon-o-cube',
                'danger',
                InventoryItemResource::getUrl(),
            );

            $add(
                Schema::hasTable('failed_jobs'),
                (int) DB::table('failed_jobs')->count(),
                'failed background jobs',
                'heroicon-o-exclamation-triangle',
                'danger',
                SystemHealth::getUrl(),
            );
        } catch (\Throwable) {
            // On a partially-migrated install just show what we could gather.
        }

        return $items;
    }

    private function lowStockCount(): int
    {
        try {
            return InventoryItem::query()
                ->where('is_active', true)
                ->whereNotNull('reorder_level')
                ->whereExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('inventory_stock')
                        ->whereColumn('inventory_stock.inventory_item_id', 'products.id')
                        ->groupBy('inventory_stock.inventory_item_id')
                        ->havingRaw('SUM(quantity) <= products.reorder_level');
                })
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
