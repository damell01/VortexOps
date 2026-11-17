<?php

namespace App\Support;

use App\Models\Shipment;
use App\Models\Show;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shipment tracking is a read workspace, not the fulfillment packing queue.
 * Imported shipments can exist before a streamer report is approved, so
 * FulfillmentResource::getEloquentQuery() must not gate tracking details.
 */
final class ShipmentTrackingAccess
{
    public static function canView(?User $user): bool
    {
        return (bool) ($user && ($user->isOwner() || $user->isAdmin() || $user->isFulfillmentAdmin()));
    }

    /** @return Builder<Show> */
    public static function shows(): Builder
    {
        return Show::query()->inChannelContext();
    }

    /** @return Builder<Shipment> */
    public static function shipments(): Builder
    {
        return Shipment::query()->whereHas('show', fn (Builder $query) => $query->inChannelContext());
    }
}
