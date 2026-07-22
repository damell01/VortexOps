<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\FulfillmentResource;
use App\Filament\Resources\StreamerLogResource;
use App\Models\StreamerLogEntry;
use App\Models\WhatnotShowOrder;
use App\Support\ChannelContext;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * The fulfillment/fulfillment_admin equivalent of NeedsAttentionWidget (which
 * is admin/owner-only) — "what needs me today" for the shipping side: orders
 * still waiting on a label/pack/ship step, and PWE+Labels streamer logs
 * waiting on the fulfillment sign-off those roles are the only ones who can
 * action (see StreamerLogResource::canAccess()).
 */
class FulfillmentNeedsAttentionWidget extends Widget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static bool $isLazy = true;
    protected string $view = 'filament.widgets.needs-attention';

    public static function canView(): bool
    {
        $user = auth()->user();

        return ($user?->isFulfillment() || $user?->isFulfillmentAdmin()) ?? false;
    }

    protected function getViewData(): array
    {
        return [
            'heading' => 'Needs Fulfillment',
            'items'   => $this->items(),
        ];
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
                Schema::hasTable('whatnot_show_orders'),
                $this->unshippedOrdersCount(),
                'orders still need a label, pack, or ship step',
                'heroicon-o-truck',
                'warning',
                FulfillmentResource::getUrl(),
            );

            $add(
                Schema::hasTable('streamer_log_entries'),
                $this->fulfillmentReviewCount(),
                'streamer logs waiting on fulfillment review',
                'heroicon-o-clipboard-document-check',
                'info',
                StreamerLogResource::getUrl(),
            );
        } catch (\Throwable) {
            // On a partially-migrated install just show what we could gather.
        }

        return $items;
    }

    private function unshippedOrdersCount(): int
    {
        $user = auth()->user();

        return WhatnotShowOrder::query()
            ->where(fn (Builder $q) => $q->whereNull('shipping_status')
                ->orWhereIn('shipping_status', ['pending', 'label_created', 'packed']))
            ->inChannelContext()
            ->when($user && $user->isFulfillment() && ! $user->isFulfillmentAdmin(), fn (Builder $q) => $q
                ->whereHas('show', fn (Builder $s) => $s->whereHas('fulfillmentUsers', fn (Builder $u) => $u->where('users.id', $user->id))))
            ->count();
    }

    private function fulfillmentReviewCount(): int
    {
        return StreamerLogEntry::query()
            ->with('streamer')
            ->when(ChannelContext::isScoped(), fn (Builder $q) => $q
                ->whereHas('show', fn (Builder $s) => $s->where('whatnot_channel_id', ChannelContext::currentId())))
            ->get()
            ->filter(fn (StreamerLogEntry $entry) => $entry->needsFulfillmentReview())
            ->count();
    }
}
