<?php

namespace App\Support;

use App\Models\InventoryLocation;
use App\Models\User;

/**
 * Who may change stock where — add, adjust, count, mark damaged, or move it
 * out of a location. Seeing stock is InventoryVisibility's question; this one
 * is about changing it, and the two differ on purpose.
 *
 *  - Main and other shared locations (no streamer, whether for everyone or
 *    for certain businesses) are run by the business: admins, the owner and
 *    staff the owner has given inventory pages to (a warehouse role) — but
 *    not streamers or streamer assistants.
 *  - Owners and non-streamer admins may manage every location, including
 *    streamer shelves, to receive and correct stock for the team. Streamers
 *    cannot change Main or another streamer's shelf.
 *
 * Moving stock is judged by where it comes from: sending Main stock to a
 * streamer is an admin's call, sending their own stock back is theirs. The
 * gate covers what people do on screens; system work (show report
 * deductions, pallet receiving, setup jobs) goes through the services
 * directly and is not stopped here.
 */
class StockGate
{
    public static function canChange(?User $user, InventoryLocation|int|null $location): bool
    {
        if ($user === null || $location === null || InventoryVisibility::isReadOnlyStreamer($user)) {
            return false;
        }

        $location = $location instanceof InventoryLocation ? $location : InventoryLocation::find($location);
        if ($location === null) {
            return false;
        }

        $visible = InventoryVisibility::locationIdsFor($user);
        if ($visible !== null && ! in_array((int) $location->id, $visible, true)) return false;

        if ($location->streamer_id !== null) {
            if ($user->isOwner() || ($user->isAdmin() && ! InventoryVisibility::isReadOnlyStreamer($user))) {
                return true;
            }
            return $user->streamer?->id !== null && (int) $user->streamer->id === (int) $location->streamer_id;
        }

        return static::runsSharedLocations($user);
    }

    /** Admins, the owner, or staff with no streamer profile who are not assistants. */
    private static function runsSharedLocations(User $user): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        // A streamer is a streamer even with an admin role on top: they run
        // their own shelf, not Main.
        if ($user->streamer?->isStreamer() || $user->isStreamerAssistant() || $user->hasRole('streamer')) {
            return false;
        }

        return true;
    }

    /** @return list<int> Location ids this user may change stock in. */
    public static function changeableIds(?User $user): array
    {
        if ($user === null || InventoryVisibility::isReadOnlyStreamer($user)) {
            return [];
        }

        $own = $user->streamer?->id;
        $manager = static::runsSharedLocations($user);
        if ($user->isOwner() || ($user->isAdmin() && ! InventoryVisibility::isReadOnlyStreamer($user))) {
            return InventoryLocation::query()->visibleTo($user)
                ->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return InventoryLocation::query()->visibleTo($user)
            ->where(function ($q) use ($own, $manager) {
                if ($manager) {
                    $q->orWhereNull('streamer_id');
                }
                if ($own) {
                    $q->orWhere('streamer_id', $own);
                }
                if (! $manager && ! $own) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Whether this user runs any location at all — the condition for every stock-changing screen. */
    public static function canChangeAny(?User $user = null): bool
    {
        $user ??= auth()->user();
        if ($user === null || InventoryVisibility::isReadOnlyStreamer($user)) {
            return false;
        }

        // Staff run Main even before a location exists; a streamer needs a shelf.
        return static::runsSharedLocations($user) || static::changeableIds($user) !== [];
    }

    /**
     * Keep only the options (id => label) this user may change.
     *
     * @param  array<int|string, string>  $options
     * @return array<int|string, string>
     */
    public static function filter(array $options, ?User $user = null): array
    {
        $allowed = array_flip(static::changeableIds($user ?? auth()->user()));

        return array_filter($options, fn ($key) => isset($allowed[(int) $key]), ARRAY_FILTER_USE_KEY);
    }

    /** Stop the request with a plain reason when the location is not theirs to change. */
    public static function authorize(InventoryLocation|int|null $location, ?User $user = null): void
    {
        $user ??= auth()->user();
        if (static::canChange($user, $location)) {
            return;
        }

        $location = $location instanceof InventoryLocation ? $location : InventoryLocation::find($location);
        abort(403, $location?->streamer_id !== null
            ? 'Only ' . ($location->streamer?->name ?? 'that streamer') . ' can change stock at ' . $location->name . '.'
            : 'Only admins can change stock at ' . ($location?->name ?? 'that location') . '.');
    }
}
