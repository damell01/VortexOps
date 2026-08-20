<?php

namespace App\Support;

use App\Models\InventoryLocation;
use App\Models\Setting;
use App\Models\User;

/**
 * Which locations a person is allowed to see stock in.
 *
 * Streamers need to look at main inventory — they cannot ask for a case to be
 * sent to them if they cannot see one exists. But "main inventory" is not the
 * same as "everything": another streamer's shelf, the damaged bin and the
 * returns pile are none of their business, and showing them turns a useful
 * screen into a list to scroll past.
 *
 * So the answer is configured rather than guessed, in one place, and every
 * screen that shows stock asks here instead of writing its own rule. The
 * alternative — each list deciding for itself — is how one page ends up
 * stricter than another and nobody can say which is right.
 *
 * A streamer always sees their own location whatever the setting says. It is
 * theirs; hiding it would leave them unable to see what they were sent.
 */
class InventoryVisibility
{
    /** Locations chosen in Settings as visible to streamers. */
    public const SETTING_KEY = 'streamer_visible_location_ids';

    /**
     * Location ids this user may see stock in, or null for "no limit".
     *
     * Null rather than "every id" so callers can skip the filter entirely for
     * an admin: a whereIn against every row is a slower way of saying nothing.
     *
     * @return array<int, int>|null
     */
    public static function locationIdsFor(?User $user): ?array
    {
        if ($user === null) {
            return [];
        }

        if ($user->isAdmin() || $user->isOwner()) {
            return null;
        }

        if (! $user->isStreamer()) {
            return null;
        }

        return array_values(array_unique([
            ...static::configuredForStreamers(),
            ...static::ownLocationIds($user),
        ]));
    }

    /** Whether this user's view of stock is limited at all. */
    public static function isLimited(?User $user): bool
    {
        return static::locationIdsFor($user) !== null;
    }

    /**
     * The locations Settings makes visible to streamers.
     *
     * Unset is not the same as empty. Nothing configured means nobody has made
     * the decision yet, and the sensible reading of that is "the main store" —
     * which is what a streamer is looking at when they want stock. An explicit
     * empty selection is a decision, and it is honoured: streamers then see
     * only their own shelf.
     *
     * @return array<int, int>
     */
    public static function configuredForStreamers(): array
    {
        $raw = Setting::get(static::SETTING_KEY);

        if ($raw === null || $raw === '') {
            return array_keys(InventoryLocation::activeOptionsByType('main_storage'));
        }

        $ids = is_array($raw) ? $raw : json_decode($raw, true);

        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    /**
     * The streamer's own shelves.
     *
     * @return array<int, int>
     */
    public static function ownLocationIds(User $user): array
    {
        $streamerId = $user->streamer?->id;

        if (! $streamerId) {
            return [];
        }

        return InventoryLocation::where('streamer_id', $streamerId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Where this user can send stock to — their own inventory.
     *
     * A streamer with no location of their own has nowhere to send anything,
     * which is a setup problem rather than a permission one, so it comes back
     * as null and the caller says so.
     */
    public static function destinationFor(?User $user): ?InventoryLocation
    {
        if (! $user?->streamer?->id) {
            return null;
        }

        return InventoryLocation::where('streamer_id', $user->streamer->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();
    }

    /**
     * Locations this user can move stock *out of*.
     *
     * Their own is excluded: moving stock from your shelf to your shelf is not
     * a transfer, and offering it invites a movement that nets to nothing but
     * still lands in the audit trail.
     *
     * @return array<int, string>
     */
    public static function sourceOptionsFor(?User $user): array
    {
        $visible = static::locationIdsFor($user);
        $own     = $user ? static::ownLocationIds($user) : [];

        $query = InventoryLocation::where('status', 'active');

        if ($visible !== null) {
            $query->whereIn('id', $visible);
        }

        if ($own !== []) {
            $query->whereNotIn('id', $own);
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Options for the Settings control, so the list matches what exists. */
    public static function selectableLocations(): array
    {
        return InventoryLocation::where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
